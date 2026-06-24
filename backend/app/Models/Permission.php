<?php

namespace App\Models;

use App\Enums\Rbac\PermissionScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission model.
 *
 * Represents granular system permissions.
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'scope',
        'scope_reference',
        'description',
    ];

    protected $casts = [
        'scope' => PermissionScope::class,
    ];

    /**
     * Roles that have this permission.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Users with direct permission.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
