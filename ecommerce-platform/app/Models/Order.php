<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order Model
 *
 * Represents a completed customer order. The shipping address is stored
 * as a JSON snapshot so address book changes never corrupt history.
 *
 * Status pipeline:
 *   pending → paid → processing → shipped → delivered
 *                                         ↘ cancelled / refunded
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $order_number    e.g. ORD-2024-000123
 * @property string $status
 * @property array  $shipping_address  JSON snapshot
 * @property float  $subtotal
 * @property float  $coupon_discount
 * @property float  $shipping_fee
 * @property float  $tax_amount
 * @property float  $grand_total
 * @property string $payment_method
 * @property string $payment_reference
 * @property string $tracking_code
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'order_number', 'status',
        'shipping_address',
        'subtotal', 'coupon_discount', 'shipping_fee', 'tax_amount', 'grand_total',
        'currency_code',
        'payment_method', 'payment_reference', 'paid_at',
        'shipping_carrier', 'tracking_code', 'shipped_at', 'delivered_at', 'cancelled_at',
        'coupon_code', 'notes', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',        // JSON ↔ PHP array auto-conversion
            'subtotal'         => 'decimal:2',
            'coupon_discount'  => 'decimal:2',
            'shipping_fee'     => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'grand_total'      => 'decimal:2',
            'paid_at'          => 'datetime',
            'shipped_at'       => 'datetime',
            'delivered_at'     => 'datetime',
            'cancelled_at'     => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** The customer who placed this order. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All line items in this order. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Status Helpers ───────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isPaid(): bool       { return $this->status === 'paid'; }
    public function isShipped(): bool    { return $this->status === 'shipped'; }
    public function isDelivered(): bool  { return $this->status === 'delivered'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
    public function isRefunded(): bool   { return $this->status === 'refunded'; }

    /**
     * Transition the order through its status pipeline.
     * Automatically sets the relevant timestamp.
     */
    public function transitionTo(string $newStatus): bool
    {
        $timestamps = [
            'paid'      => 'paid_at',
            'shipped'   => 'shipped_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
        ];

        $this->status = $newStatus;
        if (isset($timestamps[$newStatus])) {
            $this->{$timestamps[$newStatus]} = now();
        }

        return $this->save();
    }

    // ── Computed Attributes ──────────────────────────────────────────────

    /** How many total items (sum of quantities) are in this order. */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }
}
