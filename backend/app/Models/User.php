<?php

namespace App\Models;

use App\Services\Rbac\PermissionCacheService;
use App\Enums\Rbac\RoleScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model.
 *
 * Represents authenticated system user.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Hidden attributes.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles assigned to user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class)->withPivot(['scope_reference', 'tenant_id']);
    }

    /**
     * Direct permissions assigned to user.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot([
                'id',
                'status',
                'invited_by',
                'invited_at',
                'accepted_at',
                'activated_at',
                'suspended_at',
                'created_at',
                'updated_at',
            ])
            ->withTimestamps();
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function activeTenantMemberships(): HasMany
    {
        return $this->tenantMemberships()->where('status', 'active');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class, 'assigned_user_id');
    }

    public function primaryPhoneNumber(): HasOne
    {
        return $this->hasOne(PhoneNumber::class, 'assigned_user_id')->where('is_primary', true);
    }

    public function assignedExtensions(): HasMany
    {
        return $this->hasMany(Extension::class, 'assigned_user_id');
    }

    public function callQueueMembers(): HasMany
    {
        return $this->hasMany(CallQueueMember::class, 'user_id');
    }

    public function callerCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'caller_user_id');
    }

    public function calleeCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'callee_user_id');
    }

    /**
     * Check if user has role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('name', $role)
            ->where('scope', RoleScope::Platform->value)
            ->exists();
    }

    /**
     * Check if user has any of roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('name', $roles)
            ->where('scope', RoleScope::Platform->value)
            ->exists();
    }

    /**
     * Check permission against effective RBAC permissions.
     *
     * WHY:
     * Authorization checks must match auth payload semantics:
     * role permissions + direct permissions - denied permissions.
     */
    public function hasPermission(string $permission): bool
    {
        /** @var PermissionCacheService $permissionCache */
        $permissionCache = app(PermissionCacheService::class);

        return in_array(
            $permission,
            $permissionCache->getEffectivePermissionsForUser($this),
            true
        );
    }

    public function hasAnyPermission(array $permissions): bool
    {
        /** @var PermissionCacheService $permissionCache */
        $permissionCache = app(PermissionCacheService::class);
        $effective = $permissionCache->getEffectivePermissionsForUser($this);

        return count(array_intersect($permissions, $effective)) > 0;
    }

    /**
     * Check if user is admin (via role).
     */
    public function isAdmin(): bool
    {
        return $this->isPlatformAdmin();
    }

    /**
     * Canonical platform-admin capability.
     *
     * WHY:
     * Tenant access must be granted only through the protected platform admin
     * role so platform permissions alone cannot bypass tenant isolation.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->roles()
            ->where('name', 'admin')
            ->where('scope', RoleScope::Platform->value)
            ->exists();
    }

    public function deniedPermissions()
    {
        return $this->belongsToMany(Permission::class, 'user_denied_permissions');
    }
}
