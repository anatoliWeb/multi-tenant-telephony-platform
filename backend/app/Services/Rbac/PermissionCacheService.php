<?php

namespace App\Services\Rbac;

use App\Enums\Rbac\RoleScope;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionCacheService
{
    private const PLATFORM_VERSION_KEY = 'rbac:effective_permissions:platform:version';
    private const TENANT_VERSION_KEY_PREFIX = 'rbac:effective_permissions:tenant:version:';
    private const USER_VERSION_KEY_PREFIX = 'rbac:user:effective_permissions:version:';

    /**
     * Resolve and cache effective permission names for a user.
     *
     * WHY:
     * Auth payload and permission-aware UI hit this path frequently.
     * Caching reduces repeated relation resolution for unchanged RBAC state.
     *
     * @return array<int, string>
     */
    public function getEffectivePermissionsForUser(User $user): array
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant instanceof Tenant) {
            return $this->getTenantPermissionsForUser($user, $tenant);
        }

        return $this->getPlatformPermissionsForUser($user);
    }

    /**
     * Resolve and cache platform permissions for a user.
     *
     * @return array<int, string>
     */
    public function getPlatformPermissionsForUser(User $user): array
    {
        if (! $this->cacheEnabled()) {
            return $this->resolvePlatformPermissions($user);
        }

        $cacheKey = $this->platformKeyForUserId((int) $user->id);

        return $this->cacheStore()->remember(
            $cacheKey,
            now()->addSeconds($this->ttlSeconds()),
            fn () => $this->resolvePlatformPermissions($user)
        );
    }

    /**
     * Resolve and cache tenant permissions for a user in the active tenant.
     *
     * @return array<int, string>
     */
    public function getTenantPermissionsForUser(User $user, Tenant $tenant): array
    {
        if (! $this->cacheEnabled()) {
            return $this->resolveTenantPermissions($user, $tenant);
        }

        $cacheKey = $this->tenantKeyForUserId((int) $user->id, (string) $tenant->getKey());

        return $this->cacheStore()->remember(
            $cacheKey,
            now()->addSeconds($this->ttlSeconds()),
            fn () => $this->resolveTenantPermissions($user, $tenant)
        );
    }

    public function forgetForUser(User $user): void
    {
        $this->forgetForUserId((int) $user->id);
    }

    public function forgetForUserId(int $userId): void
    {
        $key = $this->userVersionKey($userId);
        $this->cacheStore()->add($key, 1, now()->addDays(7));
        $this->cacheStore()->increment($key);
    }

    public function forgetAll(): void
    {
        $this->bumpPlatformVersion();
    }

    public function globalVersion(): int
    {
        return $this->platformVersion();
    }

    public function userVersion(int $userId): int
    {
        return (int) $this->cacheStore()->get($this->userVersionKey($userId), 1);
    }

    public function forgetForTenant(string $tenantId): void
    {
        $this->bumpTenantVersion($tenantId);
    }

    public function forgetForUserTenant(int $userId, string $tenantId): void
    {
        $this->bumpTenantVersion($tenantId);
    }

    protected function ttlSeconds(): int
    {
        return (int) config('performance.cache.rbac_ttl', config('cache.rbac_permissions_ttl', 600));
    }

    protected function cacheStore(): CacheRepository
    {
        $store = config('performance.cache.store');
        if (! is_string($store) || $store === '') {
            return Cache::store();
        }

        return Cache::store($store);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveEffectivePermissions(User $user): array
    {
        return $this->resolvePlatformPermissions($user);
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePlatformPermissions(User $user): array
    {
        $freshUser = User::query()
            ->with(['roles.permissions', 'permissions', 'deniedPermissions'])
            ->find($user->id);

        if (! $freshUser) {
            return [];
        }

        $rolePermissions = $freshUser->roles
            ->filter(fn ($role) => $this->scopeValue(data_get($role, 'scope')) === RoleScope::Platform->value)
            ->flatMap(fn ($role) => $role->permissions)
            ->filter(fn ($permission) => $this->scopeValue(data_get($permission, 'scope')) === RoleScope::Platform->value);

        $directPermissions = $freshUser->permissions
            ->filter(fn ($permission) => $this->scopeValue(data_get($permission, 'scope')) === RoleScope::Platform->value);
        $denied = $freshUser->deniedPermissions ?? collect();
        $deniedIds = $denied
            ->filter(fn ($permission) => $this->scopeValue(data_get($permission, 'scope')) === RoleScope::Platform->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /** @var Collection<int, Permission> $permissions */
        $permissions = $rolePermissions
            ->merge($directPermissions)
            ->filter(fn ($permission) => $permission instanceof Permission);

        return $permissions
            ->unique('id')
            ->reject(fn (Permission $permission) => in_array((int) $permission->id, $deniedIds, true))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTenantPermissions(User $user, Tenant $tenant): array
    {
        if ($user->isPlatformAdmin()) {
            return Permission::query()
                ->where('scope', RoleScope::Tenant->value)
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();
        }

        $hasActiveMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->exists();

        if (!$hasActiveMembership) {
            return [];
        }

        $roleIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->getKey())
            ->where('roles.scope', RoleScope::Tenant->value)
            ->where('roles.tenant_id', $tenant->getKey())
            ->pluck('roles.id')
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permission_role.role_id', $roleIds)
            ->where('permissions.scope', RoleScope::Tenant->value)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->values()
            ->all();
    }

    protected function platformKeyForUserId(int $userId): string
    {
        return sprintf(
            'rbac:platform:user:%d:v%d:%d',
            $userId,
            $this->platformVersion(),
            $this->userVersion($userId)
        );
    }

    protected function tenantKeyForUserId(int $userId, string $tenantId): string
    {
        return sprintf(
            'rbac:tenant:%s:user:%d:v%d:%d',
            $tenantId,
            $userId,
            $this->tenantVersion($tenantId),
            $this->userVersion($userId)
        );
    }

    protected function platformVersion(): int
    {
        return (int) $this->cacheStore()->get(self::PLATFORM_VERSION_KEY, 1);
    }

    protected function tenantVersion(string $tenantId): int
    {
        return (int) $this->cacheStore()->get($this->tenantVersionKey($tenantId), 1);
    }

    protected function bumpPlatformVersion(): void
    {
        $this->cacheStore()->add(self::PLATFORM_VERSION_KEY, 1, now()->addDays(7));
        $this->cacheStore()->increment(self::PLATFORM_VERSION_KEY);
    }

    protected function bumpTenantVersion(string $tenantId): void
    {
        $key = $this->tenantVersionKey($tenantId);
        $this->cacheStore()->add($key, 1, now()->addDays(7));
        $this->cacheStore()->increment($key);
    }

    protected function tenantVersionKey(string $tenantId): string
    {
        return self::TENANT_VERSION_KEY_PREFIX.$tenantId;
    }

    protected function userVersionKey(int $userId): string
    {
        return self::USER_VERSION_KEY_PREFIX.$userId;
    }

    protected function scopeValue(mixed $scope): ?string
    {
        if ($scope instanceof \BackedEnum) {
            return $scope->value;
        }

        return is_string($scope) ? $scope : null;
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('performance.cache.enabled', true);
    }
}
