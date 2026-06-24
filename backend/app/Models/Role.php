<?php

namespace App\Models;

use App\Enums\Rbac\RoleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Role model.
 *
 * Represents user roles (admin, user, etc.)
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'scope',
        'scope_reference',
        'tenant_id',
        'description',
        'is_system',
        'is_protected',
    ];

    protected $casts = [
        'scope' => RoleScope::class,
        'is_system' => 'boolean',
        'is_protected' => 'boolean',
    ];

    /**
     * Users belonging to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['scope_reference', 'tenant_id']);
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
