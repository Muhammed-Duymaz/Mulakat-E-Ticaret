<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cart Model
 *
 * One active cart per authenticated user.
 * Guest carts are keyed by session_id (merged into user cart on login).
 *
 * @property int    $id
 * @property int    $user_id       (nullable for guests)
 * @property string $session_id    (nullable for authenticated users)
 * @property string $coupon_code
 * @property float  $coupon_discount
 */
class Cart extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'coupon_code', 'coupon_discount',
    ];

    protected function casts(): array
    {
        return ['coupon_discount' => 'decimal:2'];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All active (not saved-for-later) items in the cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)
                    ->where('saved_for_later', false);
    }

    /**
     * Items the customer saved for later (wishlist-style).
     */
    public function savedItems(): HasMany
    {
        return $this->hasMany(CartItem::class)
                    ->where('saved_for_later', true);
    }

    /**
     * All items regardless of saved state.
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // ── Computed Attributes ──────────────────────────────────────────────

    /**
     * Subtotal: sum of (unit_price × quantity) for active items.
     * Uses the already-loaded items collection to avoid extra queries.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    /** Total number of distinct items in the cart. */
    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** Find a cart for an authenticated user. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Find a guest cart by session token. */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->whereNull('user_id')->where('session_id', $sessionId);
    }
}
