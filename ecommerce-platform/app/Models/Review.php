<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

/**
 * Review Model
 *
 * Customers can rate and review products they have purchased.
 * - Only verified purchasers (order_item_id is set) can review.
 * - One review per user per product (enforced by DB unique index).
 * - Reviews go through a moderation workflow before being public.
 * - After approval, Product.average_rating and review_count are updated
 *   via the ReviewObserver (wire up in AppServiceProvider).
 *
 * @property int    $id
 * @property int    $product_id
 * @property int    $user_id
 * @property int    $order_item_id  (nullable)
 * @property int    $rating         1–5
 * @property string $title
 * @property string $body
 * @property array  $images         JSON array of image paths
 * @property string $status         pending|approved|rejected
 * @property bool   $is_verified_purchase
 * @property int    $helpful_count
 */
class Review extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'user_id', 'order_item_id',
        'rating', 'title', 'body', 'images',
        'status', 'moderation_note',
        'helpful_count', 'is_verified_purchase',
    ];

    protected function casts(): array
    {
        return [
            'images'               => 'array',
            'is_verified_purchase' => 'boolean',
            'rating'               => 'integer',
            'helpful_count'        => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified_purchase', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function approve(): bool
    {
        $this->status = 'approved';
        return $this->save();
    }

    public function reject(string $reason = ''): bool
    {
        $this->status           = 'rejected';
        $this->moderation_note  = $reason;
        return $this->save();
    }
}
