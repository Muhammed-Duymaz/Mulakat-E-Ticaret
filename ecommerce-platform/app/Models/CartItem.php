<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CartItem Model
 *
 * Represents a single line in a shopping cart.
 * Supports both variant-based and base products.
 *
 * unit_price is captured when the item is added/refreshed —
 * it prevents cart price drift when products change price mid-session.
 *
 * @property int   $id
 * @property int   $cart_id
 * @property int   $product_id
 * @property int   $variant_id        (nullable)
 * @property int   $quantity
 * @property float $unit_price        price at add-to-cart time
 * @property bool  $saved_for_later
 */
class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'product_id', 'variant_id',
        'quantity', 'unit_price', 'saved_for_later',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'      => 'decimal:2',
            'saved_for_later' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // ── Computed Attributes ──────────────────────────────────────────────

    /** Line total: unit_price × quantity. */
    public function getLineTotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 2);
    }

    /**
     * Verify current stock availability for this item.
     * Returns how many units are actually available right now.
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->variant_id && $this->variant) {
            return $this->variant->stock;
        }
        return $this->product->stock ?? 0;
    }

    /**
     * Is the requested quantity still satisfiable by current stock?
     */
    public function getIsStockSufficientAttribute(): bool
    {
        return $this->quantity <= $this->available_stock;
    }
}
