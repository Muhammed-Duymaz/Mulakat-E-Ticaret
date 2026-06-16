<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Product Model
 *
 * The central entity of the platform. Supports both simple (no-variant)
 * and variant-based products (has_variants = true).
 *
 * KEY DESIGN DECISIONS:
 *  - Price/stock at the product level is the "base" or fallback.
 *  - When has_variants = true, real stock is tracked on ProductVariant.
 *  - average_rating and review_count are denormalized for query speed.
 *  - `views` is incremented via DB increment() — no full model hydration.
 *
 * @property int    $id
 * @property int    $vendor_id
 * @property int    $category_id
 * @property int    $brand_id
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property float  $price
 * @property float  $discount_price
 * @property int    $stock
 * @property bool   $has_variants
 * @property string $status    draft|active|archived
 * @property int    $views
 * @property float  $average_rating
 * @property int    $review_count
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'category_id', 'brand_id',
        'name', 'slug', 'sku', 'short_description', 'description',
        'price', 'discount_price', 'cost_price',
        'stock', 'has_variants', 'low_stock_threshold',
        'weight', 'length', 'width', 'height',
        'meta_title', 'meta_description', 'tags',
        'status', 'views', 'average_rating', 'review_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'discount_price'  => 'decimal:2',
            'cost_price'      => 'decimal:2',
            'weight'          => 'decimal:2',
            'average_rating'  => 'decimal:2',
            'has_variants'    => 'boolean',
            'tags'            => 'array',
            'published_at'    => 'datetime',
        ];
    }

    // ── Auto Slug Generation ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
            // Auto-generate SKU if not provided
            if (empty($product->sku)) {
                $product->sku = strtoupper(Str::random(3)) . '-' . time();
            }
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }
        });
    }

    /**
     * Generate a unique slug, appending a counter if the base slug exists.
     */
    private static function generateUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base    = Str::slug($name);
        $slug    = $base;
        $counter = 1;

        while (
            static::where('slug', $slug)
                  ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                  ->exists()
        ) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** The vendor (User with role=vendor) who lists this product. */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /** Primary category. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Additional categories via many-to-many. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    /** Brand. */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * All gallery images (product-level + variant-level combined).
     * Use featuredImage() for the primary thumbnail.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** Only the featured (hero) image. */
    public function featuredImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_featured', true);
    }

    /**
     * Variant option types for this product (e.g. "Color", "Size").
     */
    public function variantOptions(): HasMany
    {
        return $this->hasMany(VariantOption::class)->orderBy('sort_order');
    }

    /**
     * All concrete variant combinations (SKU-level rows).
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Only in-stock, active variants. */
    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
                    ->where('is_active', true)
                    ->where('stock', '>', 0);
    }

    /**
     * All variant option values reachable through variants.
     * Useful for building filter facets.
     */
    public function variantOptionValues(): HasManyThrough
    {
        return $this->hasManyThrough(
            VariantOptionValue::class,
            VariantOption::class,
            'product_id',         // FK on variant_options
            'variant_option_id',  // FK on variant_option_values
        );
    }

    /** Approved, visible reviews for this product. */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)
                    ->where('status', 'approved')
                    ->latest();
    }

    /** All reviews (admin access). */
    public function allReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ── Computed / Virtual Attributes ────────────────────────────────────

    /**
     * Effective selling price: discount_price if active, else regular price.
     */
    public function getEffectivePriceAttribute(): string
    {
        return $this->discount_price ?? $this->price;
    }

    /**
     * Discount percentage (returns 0 if no discount).
     */
    public function getDiscountPercentageAttribute(): int
    {
        if (!$this->discount_price || $this->discount_price >= $this->price) {
            return 0;
        }
        return (int) round((($this->price - $this->discount_price) / $this->price) * 100);
    }

    /**
     * Is the product currently in stock?
     * For variant products, delegate to variant-level stock.
     */
    public function getIsInStockAttribute(): bool
    {
        if ($this->has_variants) {
            return $this->variants()->where('stock', '>', 0)->where('is_active', true)->exists();
        }
        return $this->stock > 0;
    }

    /**
     * Featured image URL (or null).
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        $image = $this->images->firstWhere('is_featured', true) ?? $this->images->first();
        return $image ? asset('storage/' . $image->path) : null;
    }

    // ── Local Scopes ─────────────────────────────────────────────────────

    /**
     * Only published, active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Products with available stock.
     * For non-variant products: stock > 0.
     * For variant products: at least one variant has stock > 0.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            // Base products with stock
            $q->where('has_variants', false)
              ->where('stock', '>', 0);
        })->orWhere(function (Builder $q) {
            // Variant products — check via subquery
            $q->where('has_variants', true)
              ->whereHas('variants', fn ($v) =>
                  $v->where('stock', '>', 0)->where('is_active', true)
              );
        });
    }

    /**
     * Flexible filter scope combining category (with subtree support),
     * brand, price range, and variant option values.
     *
     * Usage:
     *   Product::active()->filterBy($request->only([
     *       'category_id', 'brand_id', 'min_price', 'max_price',
     *       'option_value_ids', 'vendor_id',
     *   ]))->get();
     *
     * @param array{
     *   category_id?: int,
     *   brand_id?: int,
     *   min_price?: float,
     *   max_price?: float,
     *   option_value_ids?: int[],
     *   vendor_id?: int,
     * } $filters
     */
    public function scopeFilterBy(Builder $query, array $filters): Builder
    {
        // ── Category (include all descendants) ───────────────────────────
        if (!empty($filters['category_id'])) {
            $category = Category::find($filters['category_id']);
            if ($category) {
                $ids = $category->getAllDescendantIds();
                $query->whereIn('category_id', $ids);
            }
        }

        // ── Brand ─────────────────────────────────────────────────────────
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        // ── Price Range ───────────────────────────────────────────────────
        if (!empty($filters['min_price'])) {
            $query->where(fn ($q) =>
                $q->whereRaw('COALESCE(discount_price, price) >= ?', [$filters['min_price']])
            );
        }
        if (!empty($filters['max_price'])) {
            $query->where(fn ($q) =>
                $q->whereRaw('COALESCE(discount_price, price) <= ?', [$filters['max_price']])
            );
        }

        // ── Variant Option Values ─────────────────────────────────────────
        // Filter products that have ALL specified option value IDs available
        if (!empty($filters['option_value_ids'])) {
            $valueIds = (array) $filters['option_value_ids'];
            foreach ($valueIds as $valueId) {
                $query->whereHas('variants', fn ($v) =>
                    $v->where('is_active', true)
                      ->whereHas('optionValues', fn ($ov) =>
                          $ov->where('variant_option_values.id', $valueId)
                      )
                );
            }
        }

        // ── Vendor ────────────────────────────────────────────────────────
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        // ── Search ────────────────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) =>
                $q->where('name', 'like', $term)
                  ->orWhere('description', 'like', $term)
                  ->orWhere('sku', 'like', $term)
            );
        }

        return $query;
    }

    /**
     * Apply sorting to the query.
     *
     * @param string $sort  cheapest|expensive|newest|oldest|best_sellers|top_rated
     */
    public function scopeSortBy(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'cheapest'     => $query->orderByRaw('COALESCE(discount_price, price) ASC'),
            'expensive'    => $query->orderByRaw('COALESCE(discount_price, price) DESC'),
            'newest'       => $query->orderBy('published_at', 'desc'),
            'oldest'       => $query->orderBy('published_at', 'asc'),
            'best_sellers' => $query->orderBy('views', 'desc'),
            'top_rated'    => $query->orderBy('average_rating', 'desc')
                                    ->orderBy('review_count', 'desc'),
            default        => $query->orderBy('published_at', 'desc'),
        };
    }

    /** Scope to products belonging to a specific vendor. */
    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    /** Low-stock alert scope (for vendor/admin dashboards). */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('has_variants', false)
                     ->whereColumn('stock', '<=', 'low_stock_threshold');
    }
}
