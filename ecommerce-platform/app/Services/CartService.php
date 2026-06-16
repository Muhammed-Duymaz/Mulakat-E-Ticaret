<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * CartService
 *
 * Encapsulates all shopping cart business logic.
 * Controllers delegate entirely to this service — they never touch
 * Cart/CartItem models directly.
 *
 * Responsibilities:
 *  - Resolve or create the user's active cart
 *  - Validate stock before adding / updating items
 *  - Refresh unit prices on every cart view (price drift prevention)
 *  - Calculate subtotal, shipping, discounts, and grand total
 *  - Merge guest cart into user cart on login
 */
class CartService
{
    // ── Configuration ────────────────────────────────────────────────────

    /** Flat shipping fee threshold: orders above this ship free. */
    private const FREE_SHIPPING_THRESHOLD = 500.00;

    /** Default shipping fee when order is below threshold. */
    private const DEFAULT_SHIPPING_FEE = 29.99;

    /** VAT/tax rate as a percentage. */
    private const TAX_RATE = 0.00; // Set to e.g. 0.18 for 18% VAT

    // ── Cart Resolution ──────────────────────────────────────────────────

    /**
     * Get the active cart for a user, creating one if it doesn't exist.
     *
     * @param int $userId
     * @return Cart  (with items.product and items.variant eager-loaded)
     */
    public function getOrCreateCart(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    // ── Add To Cart ──────────────────────────────────────────────────────

    /**
     * Add a product (or variant) to the user's cart.
     *
     * Steps:
     *  1. Resolve the product and (optionally) the variant.
     *  2. Verify the product is active.
     *  3. Check that enough stock exists.
     *  4. If an identical item already exists in the cart, increment qty.
     *  5. Otherwise, create a new CartItem row.
     *  6. Capture the current effective price as unit_price.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws InsufficientStockException
     * @throws CartException
     */
    public function addToCart(int $userId, int $productId, int $quantity, ?int $variantId = null): CartItem
    {
        if ($quantity < 1) {
            throw new CartException('Quantity must be at least 1.', 'invalid_quantity');
        }

        // ── 1. Resolve product ────────────────────────────────────────────
        $product = Product::active()->with('variants')->findOrFail($productId);

        // ── 2. Resolve variant & determine effective stock + price ─────────
        [$availableStock, $unitPrice] = $this->resolveStockAndPrice($product, $variantId);

        // ── 3. Stock check ────────────────────────────────────────────────
        $cart = $this->getOrCreateCart($userId);

        // Count what's already in the cart for this item
        $existingItem = $cart->allItems()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        $alreadyInCart = $existingItem ? $existingItem->quantity : 0;
        $totalRequested = $alreadyInCart + $quantity;

        if ($totalRequested > $availableStock) {
            $productName = $variantId
                ? $product->name . ' (' . ($existingItem?->variant?->label ?? "Variant #{$variantId}") . ')'
                : $product->name;

            throw new InsufficientStockException(
                productName: $productName,
                requested:   $totalRequested,
                available:   $availableStock,
            );
        }

        // ── 4 & 5. Upsert cart item ───────────────────────────────────────
        if ($existingItem) {
            $existingItem->update([
                'quantity'   => $totalRequested,
                'unit_price' => $unitPrice, // Refresh price on re-add
            ]);
            return $existingItem->refresh();
        }

        return CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
        ]);
    }

    // ── Update Quantity ──────────────────────────────────────────────────

    /**
     * Update the quantity of an existing cart item.
     * Re-validates stock dynamically against the latest inventory.
     *
     * @throws CartException               if item not found in this user's cart
     * @throws InsufficientStockException  if new quantity exceeds stock
     */
    public function updateQuantity(int $userId, int $cartItemId, int $newQuantity): CartItem
    {
        if ($newQuantity < 1) {
            throw new CartException('Quantity must be at least 1.', 'invalid_quantity');
        }

        // Fetch the item and verify ownership
        $item = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $userId))
            ->with(['product', 'variant'])
            ->findOrFail($cartItemId);

        // Re-validate stock against current inventory
        [$availableStock, $freshPrice] = $this->resolveStockAndPrice(
            $item->product,
            $item->variant_id
        );

        if ($newQuantity > $availableStock) {
            throw new InsufficientStockException(
                productName: $item->product->name,
                requested:   $newQuantity,
                available:   $availableStock,
            );
        }

        $item->update([
            'quantity'   => $newQuantity,
            'unit_price' => $freshPrice, // Always refresh price on update
        ]);

        return $item->refresh();
    }

    // ── Remove Item ──────────────────────────────────────────────────────

    /**
     * Remove a single item from the cart.
     *
     * @throws CartException if item not found or doesn't belong to this user
     */
    public function removeItem(int $userId, int $cartItemId): bool
    {
        $deleted = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $userId))
            ->where('id', $cartItemId)
            ->delete();

        if (!$deleted) {
            throw new CartException('Cart item not found.', 'item_not_found');
        }

        return true;
    }

    // ── Save For Later ───────────────────────────────────────────────────

    /**
     * Toggle an item between active-cart and saved-for-later.
     */
    public function toggleSaveForLater(int $userId, int $cartItemId): CartItem
    {
        $item = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $userId))
            ->findOrFail($cartItemId);

        $item->update(['saved_for_later' => !$item->saved_for_later]);

        return $item->refresh();
    }

    // ── Clear Cart ───────────────────────────────────────────────────────

    /**
     * Remove all active items from the user's cart.
     * Called by OrderService after a successful order placement.
     */
    public function clearCart(int $userId): void
    {
        $cart = Cart::where('user_id', $userId)->first();

        if ($cart) {
            CartItem::where('cart_id', $cart->id)
                    ->where('saved_for_later', false)
                    ->delete();

            // Reset coupon
            $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);
        }
    }

    // ── Get Cart Details ─────────────────────────────────────────────────

    /**
     * Load the full cart with all totals calculated.
     *
     * This method:
     *  1. Eager-loads items with their product and variant.
     *  2. Refreshes unit_price against current live prices (drift prevention).
     *  3. Flags any items that have gone out-of-stock since being added.
     *  4. Calculates subtotal, shipping fee, coupon discount, tax, grand total.
     *
     * @return array{
     *   cart: Cart,
     *   items: \Illuminate\Support\Collection,
     *   subtotal: float,
     *   coupon_discount: float,
     *   shipping_fee: float,
     *   tax_amount: float,
     *   grand_total: float,
     *   out_of_stock_items: array,
     *   free_shipping_remaining: float,
     * }
     */
    public function getCartDetails(int $userId): array
    {
        $cart = Cart::where('user_id', $userId)
            ->with([
                'items.product.images' => fn ($q) => $q->where('is_featured', true),
                'items.variant.optionValues.variantOption',
            ])
            ->firstOrCreate(['user_id' => $userId]);

        $outOfStockItems = [];
        $subtotal        = 0.0;

        foreach ($cart->items as $item) {
            // Refresh live price
            [, $livePrice] = $this->resolveStockAndPrice($item->product, $item->variant_id);
            if ((float) $item->unit_price !== (float) $livePrice) {
                $item->update(['unit_price' => $livePrice]);
                $item->unit_price = $livePrice;
            }

            // Flag out-of-stock items
            if (!$item->is_stock_sufficient) {
                $outOfStockItems[] = $item->id;
            }

            $subtotal += $livePrice * $item->quantity;
        }

        // ── Financial Calculations ────────────────────────────────────────
        $subtotal       = round($subtotal, 2);
        $couponDiscount = round((float) $cart->coupon_discount, 2);
        $afterCoupon    = max(0, $subtotal - $couponDiscount);
        $shippingFee    = $afterCoupon >= self::FREE_SHIPPING_THRESHOLD
                              ? 0.00
                              : self::DEFAULT_SHIPPING_FEE;
        $taxAmount      = round($afterCoupon * self::TAX_RATE, 2);
        $grandTotal     = round($afterCoupon + $shippingFee + $taxAmount, 2);

        $freeShippingRemaining = max(
            0,
            self::FREE_SHIPPING_THRESHOLD - $afterCoupon
        );

        return [
            'cart'                    => $cart,
            'items'                   => $cart->items,
            'subtotal'                => $subtotal,
            'coupon_discount'         => $couponDiscount,
            'coupon_code'             => $cart->coupon_code,
            'shipping_fee'            => $shippingFee,
            'tax_amount'              => $taxAmount,
            'grand_total'             => $grandTotal,
            'out_of_stock_item_ids'   => $outOfStockItems,
            'free_shipping_remaining' => round($freeShippingRemaining, 2),
            'item_count'              => $cart->item_count,
        ];
    }

    // ── Guest Cart Merge ─────────────────────────────────────────────────

    /**
     * Merge a guest cart (by session_id) into the authenticated user's cart.
     * Called after a successful login. Duplicate items are merged by summing
     * quantities (capped at available stock).
     */
    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestCart = Cart::whereNull('user_id')
                         ->where('session_id', $sessionId)
                         ->with('allItems')
                         ->first();

        if (!$guestCart || $guestCart->allItems->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($guestCart, $userId) {
            foreach ($guestCart->allItems as $guestItem) {
                try {
                    $this->addToCart(
                        $userId,
                        $guestItem->product_id,
                        $guestItem->quantity,
                        $guestItem->variant_id,
                    );
                } catch (InsufficientStockException) {
                    // Silently skip items that can't be merged due to stock
                }
            }

            // Destroy the guest cart after merging
            $guestCart->delete();
        });
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    /**
     * Resolve the available stock and effective selling price for a product
     * (or variant). Returns [stockQty, unitPrice].
     *
     * @return array{0: int, 1: float}
     * @throws CartException if variant does not belong to the product
     */
    private function resolveStockAndPrice(Product $product, ?int $variantId): array
    {
        if ($variantId !== null) {
            $variant = ProductVariant::where('id', $variantId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (!$variant) {
                throw new CartException(
                    "Variant #{$variantId} is not available for product \"{$product->name}\".",
                    'invalid_variant'
                );
            }

            $stock = $variant->stock;
            $price = (float) ($variant->discount_price ?? $variant->price ?? $product->price);
        } else {
            if ($product->has_variants) {
                throw new CartException(
                    "Product \"{$product->name}\" requires a variant selection.",
                    'variant_required'
                );
            }
            $stock = $product->stock;
            $price = (float) ($product->discount_price ?? $product->price);
        }

        return [$stock, $price];
    }
}
