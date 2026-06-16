<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource
 *
 * Transforms an Order model (with its items) into a structured JSON response.
 * Sensitive financial fields (cost_price, admin_notes) are hidden from customers.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status,
            'status_label'     => $this->getStatusLabel(),

            // ── Shipping ──────────────────────────────────────────────────
            'shipping_address' => $this->shipping_address, // Already a PHP array via cast
            'shipping_carrier' => $this->shipping_carrier,
            'tracking_code'    => $this->tracking_code,
            'shipped_at'       => $this->shipped_at?->toDateTimeString(),
            'delivered_at'     => $this->delivered_at?->toDateTimeString(),

            // ── Financials ────────────────────────────────────────────────
            'currency'         => $this->currency_code,
            'subtotal'         => (float) $this->subtotal,
            'coupon_code'      => $this->coupon_code,
            'coupon_discount'  => (float) $this->coupon_discount,
            'shipping_fee'     => (float) $this->shipping_fee,
            'tax_amount'       => (float) $this->tax_amount,
            'grand_total'      => (float) $this->grand_total,

            // ── Payment ───────────────────────────────────────────────────
            'payment_method'   => $this->payment_method,
            'paid_at'          => $this->paid_at?->toDateTimeString(),

            // Admin-only fields
            'payment_reference'=> $this->when(
                $user?->isAdmin(),
                $this->payment_reference
            ),
            'admin_notes'      => $this->when(
                $user?->isAdmin(),
                $this->admin_notes
            ),

            // ── Notes ─────────────────────────────────────────────────────
            'notes'            => $this->notes,

            // ── Items ─────────────────────────────────────────────────────
            'items'            => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->map(fn ($item) => [
                    'id'              => $item->id,
                    'product_id'      => $item->product_id,
                    'variant_id'      => $item->variant_id,
                    'product_name'    => $item->product_name,
                    'product_sku'     => $item->product_sku,
                    'variant_label'   => $item->variant_label,
                    'product_image'   => $item->product_image
                                            ? asset('storage/' . $item->product_image)
                                            : null,
                    'unit_price'      => (float) $item->unit_price,
                    'discount_price'  => $item->discount_price ? (float) $item->discount_price : null,
                    'effective_price' => (float) $item->effective_price,
                    'quantity'        => $item->quantity,
                    'line_total'      => (float) $item->line_total,
                    'status'          => $item->status,
                    // Vendor payout (admin/vendor eyes only)
                    'vendor_payout'   => $this->when(
                        $user?->isAdmin() || $user?->isVendor(),
                        (float) $item->vendor_payout
                    ),
                    'has_review'      => $item->relationLoaded('review')
                                             ? $item->review !== null
                                             : null,
                ])
            ),

            'total_items'      => $this->total_items,

            // ── Timestamps ────────────────────────────────────────────────
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
            'cancelled_at'     => $this->cancelled_at?->toDateTimeString(),
        ];
    }

    /**
     * Human-readable status label for frontend display.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending'    => 'Awaiting Payment',
            'paid'       => 'Payment Confirmed',
            'processing' => 'Being Prepared',
            'shipped'    => 'On Its Way',
            'delivered'  => 'Delivered',
            'cancelled'  => 'Cancelled',
            'refunded'   => 'Refunded',
            default      => ucfirst($this->status),
        };
    }
}
