<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductImage Model
 *
 * @property int    $id
 * @property int    $product_id
 * @property int    $variant_id   (nullable)
 * @property string $path
 * @property string $alt_text
 * @property bool   $is_featured
 * @property int    $sort_order
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id', 'variant_id', 'path', 'alt_text', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Full public URL via Laravel's storage helper. */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
