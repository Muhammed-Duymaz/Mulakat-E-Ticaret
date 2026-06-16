<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use App\Services\SearchService;

/**
 * ProductApiController
 *
 * Handles public and authenticated product-related API endpoints.
 * All data access is delegated to ProductRepositoryInterface — no
 * Eloquent queries live in this controller.
 *
 * Endpoints:
 *   GET    /api/v1/products              → index (list + filter + paginate)
 *   GET    /api/v1/products/{slug}       → show (full detail)
 *   POST   /api/v1/products/{id}/view    → incrementView
 *   GET    /api/v1/products/{slug}/related → related
 *   POST   /api/v1/products              → store  [admin, vendor]
 *   PUT    /api/v1/products/{id}         → update [admin, vendor]
 *   DELETE /api/v1/products/{id}         → destroy [admin, vendor]
 */
class ProductApiController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SearchService $searchService,
    ) {}

    // ── Public Endpoints ─────────────────────────────────────────────────

    /**
     * GET /api/v1/products
     *
     * Returns a cursor-paginated list of active products.
     * Supports rich query string filtering.
     *
     * Query Parameters:
     *   category_id      int      Filter by category (includes all subcategories)
     *   brand_id         int      Filter by brand
     *   vendor_id        int      Filter by vendor/store
     *   min_price        numeric  Minimum effective price
     *   max_price        numeric  Maximum effective price
     *   option_value_ids int[]    Filter by variant option values (e.g. ?option_value_ids[]=12&option_value_ids[]=7)
     *   search           string   Full-text keyword search (name, description, SKU)
     *   sort             string   cheapest|expensive|newest|oldest|best_sellers|top_rated
     *   per_page         int      Items per page (default 20, max 100)
     *
     * @return AnonymousResourceCollection (cursor-paginated)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'           => ['nullable', 'integer', 'exists:brands,id'],
            'vendor_id'          => ['nullable', 'integer', 'exists:users,id'],
            'min_price'          => ['nullable', 'numeric', 'min:0'],
            'max_price'          => ['nullable', 'numeric', 'min:0'],
            'option_value_ids'   => ['nullable', 'array'],
            'option_value_ids.*' => ['integer', 'exists:variant_option_values,id'],
            'search'             => ['nullable', 'string', 'max:120'],
            'sort'               => ['nullable', Rule::in([
                                        'cheapest', 'expensive', 'newest',
                                        'oldest', 'best_sellers', 'top_rated',
                                    ])],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->products->paginateWithFilters($validated);

        return ProductResource::collection($paginator);
    }

    /**
     * GET /api/v1/products/autocomplete
     *
     * Returns ultra-fast suggestions for an autocomplete dropdown.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $term = $request->query('q', '');
        
        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $results = $this->searchService->engine()->autocomplete($term, 5);

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/v1/products/{slug}
     *
     * Returns the full product detail including variants, images, and reviews.
     * Also triggers an asynchronous (queued) view count increment.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        // Increment view count — uses a raw DB increment (no model hydration)
        $this->products->incrementViews($product->id);

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * GET /api/v1/products/{slug}/related
     *
     * Returns up to 8 related products (same category or brand).
     */
    public function related(string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $related = $this->products->getRelated($product, limit: 8);

        return response()->json([
            'data' => ProductResource::collection($related),
        ]);
    }

    /**
     * GET /api/v1/products/featured
     *
     * Returns featured products for the homepage.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit    = min((int) $request->get('limit', 12), 50);
        $products = $this->products->getFeatured($limit);

        return response()->json([
            'data' => ProductResource::collection($products),
        ]);
    }

    // ── Protected Endpoints (Admin / Vendor) ─────────────────────────────

    /**
     * POST /api/v1/products
     *
     * Create a new product. Vendors can only create for their own store.
     * Admins can create for any vendor.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],
            'name'              => ['required', 'string', 'max:255'],
            'sku'               => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description'       => ['nullable', 'string'],
            'price'             => ['required', 'numeric', 'min:0'],
            'discount_price'    => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock'             => ['required', 'integer', 'min:0'],
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'status'            => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'tags'              => ['nullable', 'array'],
            'tags.*'            => ['string', 'max:60'],
            'meta_title'        => ['nullable', 'string', 'max:200'],
            'meta_description'  => ['nullable', 'string', 'max:320'],
        ]);

        // Force vendor_id: vendors always create under their own account
        $validated['vendor_id'] = $user->isVendor() ? $user->id : $request->input('vendor_id');
        $validated['status']    = $validated['status'] ?? 'draft';

        $product = $this->products->create($validated);

        return response()->json([
            'message' => 'Product created successfully.',
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * PUT /api/v1/products/{id}
     *
     * Update an existing product. Vendors can only edit their own products.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $product = $this->products->findOrFail($id);

        // Vendors may only update their own products
        if ($user->isVendor() && $product->vendor_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'category_id'       => ['sometimes', 'integer', 'exists:categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],
            'name'              => ['sometimes', 'string', 'max:255'],
            'sku'               => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description'       => ['nullable', 'string'],
            'price'             => ['sometimes', 'numeric', 'min:0'],
            'discount_price'    => ['nullable', 'numeric', 'min:0'],
            'stock'             => ['sometimes', 'integer', 'min:0'],
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'status'            => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'tags'              => ['nullable', 'array'],
            'meta_title'        => ['nullable', 'string', 'max:200'],
            'meta_description'  => ['nullable', 'string', 'max:320'],
        ]);

        $updated = $this->products->update($id, $validated);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data'    => new ProductResource($updated),
        ]);
    }

    /**
     * DELETE /api/v1/products/{id}
     *
     * Soft-delete a product. Vendors can only delete their own.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $product = $this->products->findOrFail($id);

        if ($user->isVendor() && $product->vendor_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->products->delete($id);

        return response()->json(['message' => 'Product deleted successfully.'], 200);
    }
}
