<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MetaService
{
    public function __construct(
        protected PermissionCacheService $permissionCacheService,
        protected MetaCacheService $metaCacheService,
    ) {
    }

    /**
     * Get metadata required by frontend.
     */
    public function getMeta(): array
    {
        return array_merge(
            $this->getRbacMeta(),
            $this->getBootstrapMeta(),
        );
    }

    /**
     * Lightweight bootstrap payload for admin runtime.
     *
     * @return array<string, mixed>
     */
    public function getBootstrapMeta(): array
    {
        $authUser = auth()->user();
        $user = $authUser instanceof User ? $authUser : null;

        if (!$user) {
            return [
                'current_user' => null,
                'current_user_permissions' => [],
            ];
        }

        $rbacVersion = $this->metaCacheService->rbacVersion();
        $userVersion = $this->metaCacheService->userBootstrapVersion((int) $user->id);
        $cacheKey = sprintf('meta:bootstrap:user:%d:v%d:%d', $user->id, $rbacVersion, $userVersion);

        /** @var array<string, mixed> $payload */
        if (!$this->cacheEnabled()) {
            return [
                'current_user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $this->getCurrentUserRoles($user),
                ],
                'current_user_permissions' => $this->getUserPermissionNames($user),
            ];
        }

        $payload = $this->cacheStore()->remember($cacheKey, now()->addSeconds($this->metaTtlSeconds()), function () use ($user): array {
            return [
                'current_user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $this->getCurrentUserRoles($user),
                ],
                'current_user_permissions' => $this->getUserPermissionNames($user),
            ];
        });

        return $payload;
    }

    /**
     * RBAC metadata payload.
     *
     * @return array<string, mixed>
     */
    public function getRbacMeta(): array
    {
        $rbacVersion = $this->metaCacheService->rbacVersion();

        return [
            'roles' => $this->getSafeCachedModelList(
                cacheKey: sprintf('meta:rbac:roles:v%d', $rbacVersion),
                query: fn () => Role::query()->select('id', 'name', 'description')->orderBy('name')->get(),
                modelClass: Role::class,
            ),
            'permissions' => $this->getSafeCachedModelList(
                cacheKey: sprintf('meta:rbac:permissions:v%d', $rbacVersion),
                query: fn () => Permission::query()->select('id', 'name', 'description')->orderBy('name')->get(),
                modelClass: Permission::class,
            ),
            'role_permissions' => $this->getSafeCachedRolePermissionsMap(
                cacheKey: sprintf('meta:rbac:role_permissions:v%d', $rbacVersion),
            ),
        ];
    }

    /**
     * Ensure cached RBAC lists stay flat and model-typed.
     *
     * @param callable(): Collection<int, Model> $query
     * @return Collection<int, Model>
     */
    protected function getSafeCachedModelList(string $cacheKey, callable $query, string $modelClass): Collection
    {
        if (!$this->cacheEnabled()) {
            return collect($this->normalizeMetaArray($query()));
        }

        $cached = $this->cacheStore()->get($cacheKey);

        if (is_array($cached) && $this->isFlatMetaArray($cached, $modelClass)) {
            return collect($cached)->values();
        }

        $fresh = $this->normalizeMetaArray($query());
        $this->cacheStore()->put($cacheKey, $fresh, now()->addSeconds($this->rbacTtlSeconds()));

        return collect($fresh)->values();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function getSafeCachedRolePermissionsMap(string $cacheKey): array
    {
        if (!$this->cacheEnabled()) {
            return $this->getRolePermissionsMap();
        }

        $cached = $this->cacheStore()->get($cacheKey);

        if (is_array($cached) && $this->isValidRolePermissionMap($cached)) {
            return $cached;
        }

        $fresh = $this->getRolePermissionsMap();
        $this->cacheStore()->put($cacheKey, $fresh, now()->addSeconds($this->rbacTtlSeconds()));

        return $fresh;
    }

    /**
     * @param Collection<int, mixed> $items
     */
    protected function isFlatMetaArray(array $items, string $modelClass): bool
    {
        if ($items === []) {
            return true;
        }

        foreach ($items as $item) {
            if ($item instanceof $modelClass) {
                if (!empty((string) data_get($item, 'name')) && data_get($item, 'id') !== null) {
                    continue;
                }

                return false;
            }

            if (!is_array($item)) {
                return false;
            }

            if (!empty((string) data_get($item, 'name')) && data_get($item, 'id') !== null) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<mixed> $map
     */
    protected function isValidRolePermissionMap(array $map): bool
    {
        foreach ($map as $roleName => $permissionNames) {
            if (!is_string($roleName) || $roleName === '') {
                return false;
            }

            if (!is_array($permissionNames)) {
                return false;
            }

            foreach ($permissionNames as $permissionName) {
                if (!is_string($permissionName) || $permissionName === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function getUserPermissionNames(User $user): array
    {
        return $this->permissionCacheService->getEffectivePermissionsForUser($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function getRolePermissionsMap(): array
    {
        return Role::with('permissions:id,name')
            ->get()
            ->mapWithKeys(function (Role $role) {
                return [
                    $role->name => $role->permissions
                        ->pluck('name')
                        ->filter(fn ($name) => is_string($name) && $name !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    protected function metaTtlSeconds(): int
    {
        return (int) config('performance.cache.meta_ttl', 300);
    }

    protected function rbacTtlSeconds(): int
    {
        return (int) config('performance.cache.rbac_ttl', 600);
    }

    protected function cacheStore(): CacheRepository
    {
        $store = config('performance.cache.store');
        if (! is_string($store) || $store === '') {
            return Cache::store();
        }

        return Cache::store($store);
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('performance.cache.enabled', true);
    }

    /**
     * Normalize RBAC cache payload to plain arrays.
     *
     * @param Collection<int, Model|array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeMetaArray(Collection $items): array
    {
        return $items
            ->map(function (mixed $item): array {
                if ($item instanceof Role) {
                    return [
                        'id' => (int) $item->id,
                        'name' => (string) $item->name,
                        'description' => $item->description !== null ? (string) $item->description : null,
                    ];
                }

                if ($item instanceof Permission) {
                    return [
                        'id' => (int) $item->id,
                        'name' => (string) $item->name,
                        'description' => $item->description !== null ? (string) $item->description : null,
                    ];
                }

                if (is_array($item)) {
                    return [
                        'id' => data_get($item, 'id'),
                        'name' => data_get($item, 'name'),
                        'description' => data_get($item, 'description'),
                    ];
                }

                return [
                    'id' => null,
                    'name' => '',
                    'description' => null,
                ];
            })
            ->filter(fn (array $item): bool => data_get($item, 'id') !== null && is_string(data_get($item, 'name')) && data_get($item, 'name') !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    protected function getCurrentUserRoles(User $user): array
    {
        return $user->roles()
            ->select('roles.id', 'roles.name')
            ->orderBy('roles.name')
            ->get()
            ->map(static fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
            ])
            ->values()
            ->all();
    }
}
