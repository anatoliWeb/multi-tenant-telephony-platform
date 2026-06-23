<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Role model.
 *
 * Represents user roles (admin, user, etc.)
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Users belonging to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}