<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Address;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private readonly CartService $cartService) {}

    // ── Create Order From Cart ───────────────────────────────────────────

    public function createOrderFromCart(
        int    $userId,
        int    $shippingAddressId,
        string $paymentMethod    = 'cod',
        string $paymentReference = '',
        ?string $couponCode      = null,
        ?string $notes           = null,
        bool   $clearCart        = true,
    ): Order {

        $cart = Cart::where('user_id', $userId)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new CartException('Cannot create an order from an empty cart.', 'empty_cart');
        }

        $address = Address::where('id', $shippingAddressId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return DB::transaction(function () use (
            $cart, $address, $userId,
            $paymentMethod, $paymentReference, $couponCode, $notes, $clearCart
        ) {
            $this->validateAndLockStock($cart->items);
            $cartDetails = $this->cartService->getCartDetails($userId);

            if (!empty($cartDetails['out_of_stock_item_ids'])) {
                throw new CartException(
                    'Some items in your cart are no longer available.',
                    'out_of_stock'
                );
            }

            $orderNumber = $this->generateOrderNumber();

            $order = Order::create([
                'user_id'           => $userId,
                'order_number'      => $orderNumber,
                'status'            => 'pending',
                'shipping_address'  => $address->toSnapshot(),
                'subtotal'          => $cartDetails['subtotal'],
                'coupon_code'       => $cartDetails['coupon_code'],
                'coupon_discount'   => $cartDetails['coupon_discount'],
                'shipping_fee'      => $cartDetails['shipping_fee'],
                'tax_amount'        => $cartDetails['tax_amount'],
                'grand_total'       => $cartDetails['grand_total'],
                'currency_code'     => 'TRY',
                'payment_method'    => $paymentMethod,
                'payment_reference' => $paymentReference ?: null,
                'notes'             => $notes,
            ]);

            foreach ($cart->items as $cartItem) {
                $this->createOrderItem($order, $cartItem);
            }

            $this->decrementStocks($cart->items);

            // ── Clear the cart (if synchronous payment) ────────────────
            if ($clearCart) {
                $this->cartService->clearCart($userId);
            }

            Log::info('Order created successfully.', [
                'order_number' => $order->order_number,
                'user_id'      => $userId,
                'grand_total'  => $order->grand_total,
            ]);

            return $order->load('items');
        });
    }

    // ── Mark As Paid (Complete Payment) ──────────────────────────────────

    public function completePayment(Order $order, string $paymentReference): Order
    {
        return DB::transaction(function () use ($order, $paymentReference) {
            $order->update([
                'status'            => 'paid',
                'payment_reference' => $paymentReference,
                'paid_at'           => now(),
            ]);

            $order->items()->update(['status' => 'processing']);

            // Now that payment is confirmed, clear the user's cart
            $this->cartService->clearCart($order->user_id);

            // Dispatch Notification
            $order->user->notify(new OrderPlacedNotification($order));

            Log::info('Order payment completed.', ['order_number' => $order->order_number]);

            return $order->refresh();
        });
    }

    // ── Mark As Failed (Fail Payment) ────────────────────────────────────

    public function failPayment(Order $order, string $reason): Order
    {
        Log::warning('Order payment failed. Cancelling order.', [
            'order_number' => $order->order_number,
            'reason'       => $reason,
        ]);

        return $this->cancelOrder($order, 'Payment Failed: ' . $reason);
    }

    // ── Cancel Order ─────────────────────────────────────────────────────

    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        if (!in_array($order->status, ['pending', 'paid', 'processing'])) {
            throw new CartException(
                "Order #{$order->order_number} cannot be cancelled (status: {$order->status}).",
                'cannot_cancel'
            );
        }

        return DB::transaction(function () use ($order, $reason) {
            foreach ($order->items as $item) {
                if ($item->variant_id) {
                    ProductVariant::where('id', $item->variant_id)
                        ->increment('stock', $item->quantity);
                } elseif ($item->product_id) {
                    Product::where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
                $item->update(['status' => 'cancelled']);
            }

            $order->transitionTo('cancelled');
            if ($reason) {
                $order->update(['admin_notes' => $reason]);
            }

            Log::info('Order cancelled, stock restored.', [
                'order_number' => $order->order_number,
            ]);

            return $order->refresh();
        });
    }

    // ── Mark As Shipped ──────────────────────────────────────────────────

    public function markAsShipped(Order $order, string $carrier, string $trackingCode): Order
    {
        $order->update([
            'status'           => 'shipped',
            'shipping_carrier' => $carrier,
            'tracking_code'    => $trackingCode,
            'shipped_at'       => now(),
        ]);

        $order->items()->update(['status' => 'shipped']);

        $order->user->notify(new OrderShippedNotification($order));

        return $order->refresh();
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    private function validateAndLockStock($items): void
    {
        foreach ($items as $cartItem) {
            if ($cartItem->variant_id) {
                $variant = ProductVariant::lockForUpdate()->find($cartItem->variant_id);

                if (!$variant || !$variant->is_active || $variant->stock < $cartItem->quantity) {
                    throw new InsufficientStockException(
                        productName: $cartItem->product->name . ' (' . ($variant?->label ?? 'variant') . ')',
                        requested:   $cartItem->quantity,
                        available:   $variant?->stock ?? 0,
                    );
                }
            } else {
                $product = Product::lockForUpdate()->find($cartItem->product_id);

                if (!$product || $product->status !== 'active' || $product->stock < $cartItem->quantity) {
                    throw new InsufficientStockException(
                        productName: $product?->name ?? "Product #{$cartItem->product_id}",
                        requested:   $cartItem->quantity,
                        available:   $product?->stock ?? 0,
                    );
                }
            }
        }
    }

    private function createOrderItem(Order $order, CartItem $cartItem): OrderItem
    {
        $product = $cartItem->product;
        $variant = $cartItem->variant;

        $unitPrice     = (float) $cartItem->unit_price;
        $discountPrice = null;

        if ($variant) {
            $discountPrice = $variant->discount_price ? (float) $variant->discount_price : null;
        } elseif ($product->discount_price) {
            $discountPrice = (float) $product->discount_price;
        }

        $effectivePrice = $discountPrice ?? $unitPrice;
        $lineTotal      = round($effectivePrice * $cartItem->quantity, 2);

        $variantLabel = $variant
            ? $variant->optionValues->map(fn ($v) => $v->variantOption->name . ': ' . $v->value)->join(' | ')
            : null;

        $commissionRate = $product->vendor?->commission_rate ?? 0.00;

        $featuredImage = $product->images->firstWhere('is_featured', true)?->path ?? $product->images->first()?->path;

        return OrderItem::create([
            'order_id'       => $order->id,
            'vendor_id'      => $product->vendor_id,
            'product_id'     => $product->id,
            'variant_id'     => $variant?->id,
            'product_name'   => $product->name,
            'product_sku'    => $variant?->sku ?? $product->sku,
            'variant_label'  => $variantLabel,
            'product_image'  => $featuredImage,
            'unit_price'     => $unitPrice,
            'discount_price' => $discountPrice,
            'quantity'       => $cartItem->quantity,
            'line_total'     => $lineTotal,
            'commission_rate'=> $commissionRate,
            'status'         => 'pending',
        ]);
    }

    private function decrementStocks($items): void
    {
        foreach ($items as $cartItem) {
            if ($cartItem->variant_id) {
                ProductVariant::where('id', $cartItem->variant_id)
                    ->decrement('stock', $cartItem->quantity);
            } else {
                Product::where('id', $cartItem->product_id)
                    ->decrement('stock', $cartItem->quantity);
            }
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
