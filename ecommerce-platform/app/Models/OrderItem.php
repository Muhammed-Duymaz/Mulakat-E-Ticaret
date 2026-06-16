<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem Model
 *
 * A single line in an order. All product/variant information is
 * snapshotted at purchase time so deletions and price changes
 * never mutate historical order data.
 *
 * @property int    $id
 * @property int    $order_id
 * @property int    $vendor_id         (nullable)
 * @property int    $product_id        (nullable — set null on product delete)
 * @property int    $variant_id        (nullable)
 * @property string $product_name      snapshot
 * @property string $product_sku       snapshot
 * @property string $variant_label     snapshot e.g. "Color: Red | Size: XL"
 * @property float  $unit_price        snapshot
 * @property float  $discount_price    snapshot (nullable)
 * @property int    $quantity
 * @property float  $line_total        snapshot
 * @property string $status
 * @property float  $commission_rate   vendor commission at purchase time
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'vendor_id',
        'product_id', 'variant_id',
        'product_name', 'product_sku', 'variant_label', 'product_image',
        'unit_price', 'discount_price', 'quantity', 'line_total',
        'status', 'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'      => 'decimal:2',
            'discount_price'  => 'decimal:2',
            'line_total'      => 'decimal:2',
            'commission_rate' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The live product record (may be null if product was hard-deleted).
     * Never rely on this for pricing — use the snapshot columns instead.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * The live variant record (may be null).
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /** The vendor who fulfilled this item. */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /** Review the customer left for this item. */
    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Review::class, 'order_item_id');
    }

    // ── Computed Attributes ──────────────────────────────────────────────

    /**
     * The effective price used for this item (discount price if available).
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->unit_price;
    }

    /**
     * Platform commission earned from this item.
     */
    public function getPlatformCommissionAttribute(): float
    {
        return round($this->line_total * ($this->commission_rate / 100), 2);
    }

    /**
     * Amount paid out to the vendor (after platform commission).
     */
    public function getVendorPayoutAttribute(): float
    {
        return round($this->line_total - $this->platform_commission, 2);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
