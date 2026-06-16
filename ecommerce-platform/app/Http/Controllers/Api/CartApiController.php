<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * CartApiController
 *
 * All endpoints require authentication (auth:sanctum).
 * Business logic is entirely delegated to CartService.
 *
 * Endpoints:
 *   GET    /api/v1/cart                     → index   (full cart + totals)
 *   POST   /api/v1/cart/items               → addItem
 *   PUT    /api/v1/cart/items/{id}          → updateItem
 *   DELETE /api/v1/cart/items/{id}          → removeItem
 *   POST   /api/v1/cart/items/{id}/save     → toggleSaveForLater
 *   DELETE /api/v1/cart                     → clear
 */
class CartApiController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    // ── GET /api/v1/cart ──────────────────────────────────────────────────

    /**
     * Return the authenticated user's cart with all totals calculated.
     *
     * Response includes:
     *   - items (with product & variant details)
     *   - subtotal, coupon_discount, shipping_fee, tax_amount, grand_total
     *   - out_of_stock_item_ids (items that became unavailable since being added)
     *   - free_shipping_remaining (how much more to spend for free shipping)
     */
    public function index(Request $request): JsonResponse
    {
        $details = $this->cartService->getCartDetails($request->user()->id);

        return response()->json([
            'data' => [
                'items'                   => $this->formatItems($details['items']),
                'item_count'              => $details['item_count'],
                'subtotal'                => $details['subtotal'],
                'coupon_code'             => $details['coupon_code'],
                'coupon_discount'         => $details['coupon_discount'],
                'shipping_fee'            => $details['shipping_fee'],
                'tax_amount'              => $details['tax_amount'],
                'grand_total'             => $details['grand_total'],
                'out_of_stock_item_ids'   => $details['out_of_stock_item_ids'],
                'free_shipping_remaining' => $details['free_shipping_remaining'],
            ],
        ]);
    }

    // ── POST /api/v1/cart/items ────────────────────────────────────────────

    /**
     * Add a product (or specific variant) to the cart.
     *
     * Body:
     *   product_id  int   required
     *   variant_id  int   required when product has_variants = true
     *   quantity    int   required, min 1
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $item = $this->cartService->addToCart(
                userId:    $request->user()->id,
                productId: $validated['product_id'],
                quantity:  $validated['quantity'],
                variantId: $validated['variant_id'] ?? null,
            );

            return response()->json([
                'message' => 'Item added to cart.',
                'data'    => $this->formatSingleItem($item->load(['product', 'variant.optionValues.variantOption'])),
            ], 201);

        } catch (InsufficientStockException $e) {
            return response()->json($e->toArray(), 422);
        } catch (CartException $e) {
            return response()->json($e->toArray(), 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
    }

    // ── PUT /api/v1/cart/items/{id} ────────────────────────────────────────

    /**
     * Update the quantity of a specific cart item.
     *
     * Body:
     *   quantity  int  required, min 1
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $item = $this->cartService->updateQuantity(
                userId:      $request->user()->id,
                cartItemId:  $itemId,
                newQuantity: $validated['quantity'],
            );

            return response()->json([
                'message' => 'Cart item updated.',
                'data'    => $this->formatSingleItem($item->load(['product', 'variant.optionValues.variantOption'])),
            ]);

        } catch (InsufficientStockException $e) {
            return response()->json($e->toArray(), 422);
        } catch (CartException $e) {
            return response()->json($e->toArray(), 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Cart item not found.'], 404);
        }
    }

    // ── DELETE /api/v1/cart/items/{id} ────────────────────────────────────

    /**
     * Remove a single item from the cart.
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        try {
            $this->cartService->removeItem($request->user()->id, $itemId);

            return response()->json(['message' => 'Item removed from cart.']);

        } catch (CartException $e) {
            return response()->json($e->toArray(), 404);
        }
    }

    // ── POST /api/v1/cart/items/{id}/save ─────────────────────────────────

    /**
     * Toggle an item between active cart and "saved for later".
     */
    public function toggleSaveForLater(Request $request, int $itemId): JsonResponse
    {
        try {
            $item = $this->cartService->toggleSaveForLater($request->user()->id, $itemId);

            $action = $item->saved_for_later ? 'saved for later' : 'moved back to cart';

            return response()->json([
                'message'         => "Item {$action}.",
                'saved_for_later' => $item->saved_for_later,
            ]);

        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Cart item not found.'], 404);
        }
    }

    // ── DELETE /api/v1/cart ───────────────────────────────────────────────

    /**
     * Clear all active items from the user's cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clearCart($request->user()->id);

        return response()->json(['message' => 'Cart cleared.']);
    }

    // ── Private Formatters ────────────────────────────────────────────────

    /**
     * Format a collection of cart items for API output.
     */
    private function formatItems($items): array
    {
        return $items->map(fn ($item) => $this->formatSingleItem($item))->values()->toArray();
    }

    /**
     * Format a single CartItem model into an API-safe array.
     */
    private function formatSingleItem($item): array
    {
        $product = $item->product;
        $variant = $item->variant;

        return [
            'id'                   => $item->id,
            'quantity'             => $item->quantity,
            'unit_price'           => (float) $item->unit_price,
            'line_total'           => (float) $item->line_total,
            'saved_for_later'      => (bool) $item->saved_for_later,
            'available_stock'      => $item->available_stock,
            'is_stock_sufficient'  => $item->is_stock_sufficient,
            'product'              => $product ? [
                'id'               => $product->id,
                'name'             => $product->name,
                'slug'             => $product->slug,
                'featured_image'   => $product->featured_image_url,
                'status'           => $product->status,
            ] : null,
            'variant'              => $variant ? [
                'id'               => $variant->id,
                'sku'              => $variant->sku,
                'label'            => $variant->label,
                'image'            => $variant->image ? asset('storage/' . $variant->image) : null,
            ] : null,
        ];
    }
}
