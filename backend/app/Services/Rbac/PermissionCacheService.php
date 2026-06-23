<?php

namespace App\Services\Rbac;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class PermissionCacheService
{
    private const GLOBAL_VERSION_KEY = 'rbac:effective_permissions:version';
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
        if (! $this->cacheEnabled()) {
            return $this->resolveEffectivePermissions($user);
        }

        $cacheKey = $this->keyForUserId((int) $user->id);

        return $this->cacheStore()->remember(
            $cacheKey,
            now()->addSeconds($this->ttlSeconds()),
            fn () => $this->resolveEffectivePermissions($user)
        );
    }

    public function forgetForUser(User $user): void
    {
        $this->forgetForUserId((int) $user->id);
    }

    public function forgetForUserId(int $userId): void
    {
        // WHY:
        // Bump a small user-scoped version key instead of deleting wildcard keys.
        // This keeps invalidation O(1) and avoids global cache churn.
        $key = $this->userVersionKey($userId);
        $this->cacheStore()->add($key, 1, now()->addDays(7));
        $this->cacheStore()->increment($key);
    }

    public function forgetAll(): void
    {
        // WHY:
        // Global version bump invalidates all effective permission cache keys
        // without performing a full cache-store flush that would evict unrelated keys.
        $this->cacheStore()->add(self::GLOBAL_VERSION_KEY, 1, now()->addDays(7));
        $this->cacheStore()->increment(self::GLOBAL_VERSION_KEY);
    }

    public function globalVersion(): int
    {
        return (int) $this->cacheStore()->get(self::GLOBAL_VERSION_KEY, 1);
    }

    public function userVersion(int $userId): int
    {
        return (int) $this->cacheStore()->get($this->userVersionKey($userId), 1);
    }

    protected function keyForUserId(int $userId): string
    {
        return sprintf(
            'rbac:user:%d:effective_permissions:v%d:%d',
            $userId,
            $this->globalVersion(),
            $this->userVersion($userId)
        );
    }

    protected function userVersionKey(int $userId): string
    {
        return self::USER_VERSION_KEY_PREFIX.$userId;
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
        $freshUser = User::query()
            ->with(['roles.permissions', 'permissions', 'deniedPermissions'])
            ->find($user->id);

        if (!$freshUser) {
            return [];
        }

        $rolePermissions = $freshUser->roles->flatMap(fn ($role) => $role->permissions);
        $directPermissions = $freshUser->permissions;
        $denied = $freshUser->deniedPermissions ?? collect();
        $deniedIds = $denied->pluck('id')->map(fn ($id) => (int) $id)->all();

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

    protected function cacheEnabled(): bool
    {
        return (bool) config('performance.cache.enabled', true);
    }
}
