<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Category Model
 *
 * Self-referential tree using parent_id, depth, and materialized path.
 *
 * Infinite subcategory nesting is supported. The `path` column stores
 * ancestor IDs (e.g. "1/4/12") enabling O(1) subtree lookups:
 *   Category::where('path', 'like', $ancestor->path . '%')->get()
 *
 * @property int    $id
 * @property int    $parent_id
 * @property int    $depth
 * @property string $path
 * @property string $name
 * @property string $slug
 * @property bool   $is_active
 * @property int    $sort_order
 */
class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'depth', 'path', 'name', 'slug', 'description',
        'image', 'icon', 'meta_title', 'meta_description',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'depth'      => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ── Auto Slug & Path Generation ──────────────────────────────────────

    /**
     * booted() is called once when the model class is first loaded.
     * We hook into creating/updating events to automate slug + path.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            // Auto-generate slug from name if not provided
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
            // Compute depth and path from parent
            static::computeHierarchy($category);
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
            if ($category->isDirty('parent_id')) {
                static::computeHierarchy($category);
            }
        });
    }

    /**
     * Calculate depth and materialized path based on parent.
     */
    private static function computeHierarchy(Category $category): void
    {
        if ($category->parent_id === null) {
            $category->depth = 0;
            $category->path  = null;
        } else {
            $parent          = Category::find($category->parent_id);
            $category->depth = ($parent->depth ?? 0) + 1;
            $parentPath      = $parent->path ? $parent->path . '/' . $parent->id
                                             : (string) $parent->id;
            $category->path  = $parentPath;
        }
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** Direct parent category (null for root). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Direct children (one level down). */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    /** Active children only. */
    public function activeChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    /** Products in this specific category. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** Only root categories (no parent). */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /** Only active categories. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Ordered by sort_order. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    /**
     * Get all descendants of this category using the materialized path.
     * This is a single SQL query — no recursion needed.
     */
    public function descendants(): \Illuminate\Database\Eloquent\Builder
    {
        $prefix = $this->path
            ? $this->path . '/' . $this->id
            : (string) $this->id;

        return static::where('path', 'like', $prefix . '%')
                     ->orWhere(function ($q) {
                         $q->where('parent_id', $this->id);
                     });
    }

    /**
     * Returns this category and all its descendant IDs.
     * Used for product filtering: "show all products in Electronics + subcategories"
     */
    public function getAllDescendantIds(): array
    {
        return $this->descendants()->pluck('id')->prepend($this->id)->toArray();
    }

    /**
     * Full breadcrumb trail from root to this category.
     */
    public function getBreadcrumb(): \Illuminate\Support\Collection
    {
        $ancestors = collect();

        if ($this->path) {
            $ids = array_filter(explode('/', $this->path));
            if (!empty($ids)) {
                $ancestors = Category::whereIn('id', $ids)
                                     ->orderBy('depth')
                                     ->get();
            }
        }

        return $ancestors->push($this);
    }

    /** URL-friendly accessor. */
    public function getUrlAttribute(): string
    {
        return '/category/' . $this->slug;
    }
}
