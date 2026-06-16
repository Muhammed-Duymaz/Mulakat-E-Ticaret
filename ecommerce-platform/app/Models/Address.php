<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Address Model
 *
 * Stores a user's saved shipping/billing addresses.
 * Orders snapshot the address as JSON at checkout time —
 * so changing this record never corrupts historical orders.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $title          e.g. "Home", "Office"
 * @property string $recipient_name
 * @property string $phone
 * @property string $address_line1
 * @property string $city
 * @property string $postal_code
 * @property string $country_code   ISO 3166-1 alpha-3
 * @property bool   $is_default
 */
class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'recipient_name', 'phone',
        'address_line1', 'address_line2', 'city', 'state',
        'postal_code', 'country_code', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Render a clean one-line address string.
     */
    public function toOneLine(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country_code,
        ]));
    }

    /**
     * Return a JSON-serialisable snapshot array for order storage.
     * This is stored in orders.shipping_address at checkout.
     */
    public function toSnapshot(): array
    {
        return [
            'title'          => $this->title,
            'recipient_name' => $this->recipient_name,
            'phone'          => $this->phone,
            'address_line1'  => $this->address_line1,
            'address_line2'  => $this->address_line2,
            'city'           => $this->city,
            'state'          => $this->state,
            'postal_code'    => $this->postal_code,
            'country_code'   => $this->country_code,
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
