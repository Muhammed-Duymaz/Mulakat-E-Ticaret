<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * Supports three personas via role_id:
 *  1 = Admin    → full platform management
 *  2 = Vendor   → manages their own store/products
 *  3 = Customer → browses & purchases
 *
 * @property int    $id
 * @property int    $role_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $avatar
 * @property string $store_name
 * @property string $store_slug
 * @property float  $commission_rate
 * @property string $vendor_status
 * @property bool   $is_active
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // ── Mass Assignment ──────────────────────────────────────────────────
    protected $fillable = [
        'role_id', 'name', 'email', 'phone', 'avatar', 'password',
        'store_name', 'store_slug', 'store_description', 'store_logo',
        'commission_rate', 'vendor_status', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'commission_rate'   => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** The role this user belongs to (Admin / Vendor / Customer). */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Individual permission overrides for this user. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission')
                    ->withPivot('granted');
    }

    /** Products listed by this vendor. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    /** All orders placed by this customer. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** The user's persistent shopping cart. */
    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /** All saved shipping/billing addresses. */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** Reviews written by this user. */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    /** Check if user has a specific permission (via role or override). */
    public function hasPermission(string $permissionName): bool
    {
        // 1. Check individual override (explicit deny takes priority)
        $override = $this->permissions->firstWhere('name', $permissionName);
        if ($override !== null) {
            return (bool) $override->pivot->granted;
        }

        // 2. Fall back to role-level permissions
        return $this->role
            ->permissions()
            ->where('name', $permissionName)
            ->exists();
    }

    public function isAdmin(): bool   { return $this->role_id === 1; }
    public function isVendor(): bool  { return $this->role_id === 2; }
    public function isCustomer(): bool{ return $this->role_id === 3; }

    public function getDefaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVendors($query)
    {
        return $query->where('role_id', 2);
    }

    public function scopeApprovedVendors($query)
    {
        return $query->where('role_id', 2)
                     ->where('vendor_status', 'approved');
    }
}
