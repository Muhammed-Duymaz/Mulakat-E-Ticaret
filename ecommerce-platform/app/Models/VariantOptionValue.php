<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * VariantOptionValue — e.g. "Red", "XL", "Cotton"
 *
 * Belongs to a VariantOption and is linked to ProductVariants
 * through the product_variant_option_value pivot table.
 */
class VariantOptionValue extends Model
{
    protected $fillable = [
        'variant_option_id', 'value', 'color_hex', 'image', 'sort_order',
    ];

    public function variantOption(): BelongsTo
    {
        return $this->belongsTo(VariantOption::class);
    }

    /** All product variants that include this value. */
    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_value',
            'variant_option_value_id',
            'product_variant_id'
        );
    }
}
