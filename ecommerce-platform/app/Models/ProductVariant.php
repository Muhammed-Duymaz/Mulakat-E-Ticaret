<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * ProductVariant Model
 *
 * Represents one specific SKU-level combination of option values.
 * Example: Nike Air Max (Red, XL) → sku="NAM-RED-XL", stock=10, price=1299
 *
 * The variant's effective price is:
 *   discount_price ?? price ?? parent product price
 *
 * @property int    $id
 * @property int    $product_id
 * @property string $sku
 * @property float  $price
 * @property float  $discount_price
 * @property int    $stock
 * @property bool   $is_active
 */
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku',
        'price', 'discount_price',
        'stock', 'low_stock_threshold',
        'weight', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'discount_price' => 'decimal:2',
            'weight'         => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** The parent product this variant belongs to. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The option values that define this variant combination.
     * e.g. [Color=Red, Size=XL]
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            VariantOptionValue::class,
            'product_variant_option_value',
            'product_variant_id',
            'variant_option_value_id'
        )->with('variantOption'); // Always eager-load the option name
    }

    /** Images specifically tied to this variant. */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')
                    ->orderBy('sort_order');
    }

    // ── Computed Attributes ──────────────────────────────────────────────

    /**
     * Effective selling price: variant discount > variant price > product price.
     */
    public function getEffectivePriceAttribute(): string
    {
        if ($this->discount_price) {
            return $this->discount_price;
        }
        if ($this->price) {
            return $this->price;
        }
        // Fall back to parent product price
        return $this->product->price;
    }

    /**
     * Human-readable label summarising all option values.
     * Returns: "Color: Red | Size: XL"
     */
    public function getLabelAttribute(): string
    {
        return $this->optionValues
            ->map(fn ($v) => $v->variantOption->name . ': ' . $v->value)
            ->join(' | ');
    }

    /** True if this variant has any available stock. */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /** Is stock at or below the low-stock threshold? */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** Only active variants. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Only variants with stock available. */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /** Filter variants that carry a specific option value. */
    public function scopeHasOptionValue(Builder $query, int $optionValueId): Builder
    {
        return $query->whereHas('optionValues', fn ($q) =>
            $q->where('variant_option_values.id', $optionValueId)
        );
    }
}
