<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EloquentProductRepository
 *
 * Implements ProductRepositoryInterface using Laravel's Eloquent ORM.
 * All query logic lives here — controllers and services stay clean.
 *
 * Binding (add to AppServiceProvider::register):
 *   $this->app->bind(
 *       \App\Repositories\Contracts\ProductRepositoryInterface::class,
 *       \App\Repositories\Eloquent\EloquentProductRepository::class,
 *   );
 */
class EloquentProductRepository implements ProductRepositoryInterface
{
    /**
     * Cursor-paginated product listing with full multi-filter support.
     *
     * Uses cursor pagination instead of offset pagination because:
     *  - It is O(log n) stable — performance doesn't degrade on page 50+
     *  - Prevents duplicate rows when new products are inserted mid-browse
     *  - Ideal for infinite scroll / "load more" UIs
     *
     * Eager-loads: category, brand, featuredImage (avoids N+1 on listings).
     */
    public function paginateWithFilters(array $filters): CursorPaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 100); // Cap at 100
        $sort    = $filters['sort'] ?? 'newest';

        return Product::query()
            ->with(['category', 'brand', 'images' => fn ($q) => $q->where('is_featured', true)])
            ->active()
            ->filterBy($filters)
            ->sortBy($sort)
            ->cursorPaginate($perPage);
    }

    /**
     * Full detail load for a single product page.
     * Eager-loads everything needed for the product detail view.
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::with([
            'category',
            'brand',
            'vendor:id,name,store_name,store_slug,store_logo',
            'images',
            'variantOptions.values',
            'variants.optionValues.variantOption',
            'variants.images',
            'reviews' => fn ($q) => $q->with('user:id,name,avatar')->latest()->limit(10),
        ])
        ->active()
        ->where('slug', $slug)
        ->first();
    }

    /**
     * Find by PK — used internally (e.g. cart/order validation).
     */
    public function findOrFail(int $id): Product
    {
        return Product::findOrFail($id);
    }

    /**
     * Atomic view increment — avoids loading the full model for a counter bump.
     * Rate-limiting / deduplication should be handled at the HTTP layer.
     */
    public function incrementViews(int $productId): void
    {
        Product::where('id', $productId)->increment('views');
    }

    /**
     * Recalculate denormalized rating stats from live review data.
     * Called by ReviewObserver after any review state change.
     */
    public function recalculateRating(int $productId): void
    {
        $stats = DB::table('reviews')
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        Product::where('id', $productId)->update([
            'average_rating' => round($stats->avg_rating ?? 0, 2),
            'review_count'   => $stats->review_count ?? 0,
        ]);
    }

    /**
     * Homepage featured products — active, ordered by views descending.
     */
    public function getFeatured(int $limit = 12): Collection
    {
        return Product::with(['brand', 'images' => fn ($q) => $q->where('is_featured', true)])
            ->active()
            ->inStock()
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Related products: same category or same brand, excluding current.
     */
    public function getRelated(Product $product, int $limit = 8): Collection
    {
        return Product::with(['brand', 'images' => fn ($q) => $q->where('is_featured', true)])
            ->active()
            ->inStock()
            ->where('id', '!=', $product->id)
            ->where(fn ($q) =>
                $q->where('category_id', $product->category_id)
                  ->orWhere('brand_id', $product->brand_id)
            )
            ->orderBy('average_rating', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a new product record.
     */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * Update an existing product. Returns refreshed model.
     */
    public function update(int $id, array $data): Product
    {
        $product = $this->findOrFail($id);
        $product->update($data);
        return $product->refresh();
    }

    /**
     * Soft-delete a product.
     */
    public function delete(int $id): bool
    {
        return (bool) Product::where('id', $id)->delete();
    }
}
