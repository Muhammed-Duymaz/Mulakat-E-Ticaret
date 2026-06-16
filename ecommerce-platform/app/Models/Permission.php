<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Model
 *
 * Represents a granular capability (e.g. products.create, orders.view).
 * Permissions are grouped (products, orders, users) for UI organization.
 */
class Permission extends Model
{
    protected $fillable = ['name', 'display_name', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permission')
                    ->withPivot('granted');
    }
}
