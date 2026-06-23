<?php

namespace App\Services;

use App\Events\Rbac\RolePermissionsChanged;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Localization\TranslationUpsertService;
use App\Services\MetaCacheService;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(
        protected TranslationUpsertService $translationUpsert,
        protected PermissionCacheService $permissionCacheService,
        protected MetaCacheService $metaCacheService,
    ) {
    }

    /**
     * Get roles list for API.
     *
     * WHY:
     * Query logic belongs to service layer,
     * not to HTTP controllers.
     *
     * @return Collection<int, Role>
     */
    public function getRolesForApi(): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Create role with permissions and translations.
     *
     * WHY:
     * Role creation is a domain operation:
     * - create role
     * - sync permissions
     * - persist translations
     * - reload counters
     *
     * Keeping it in service prevents controller from becoming business logic soup.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            $this->persistTranslations(
                roleName: $role->name,
                translations: $data['translations'] ?? []
            );

            // WHY:
            // New role-permission assignments can affect many users through
            // role inheritance. Coarse invalidation is safer than partial misses.
            $this->permissionCacheService->forgetAll();
            $this->metaCacheService->bumpRbacVersion();

            return $this->loadApiState($role);
        });
    }

    /**
     * Update role with permissions and translations.
     *
     * WHY:
     * Technical role identifier remains immutable for stable RBAC contracts.
     *
     * @param array<string, mixed> $data
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role->update([
                'description' => $data['description'] ?? $role->description,
            ]);

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissions($role, $data['permissions'] ?? []);

                event(new RolePermissionsChanged(
                    roleId: $role->id,
                    roleName: $role->name,
                    permissionNames: array_values($data['permissions'] ?? []),
                    actorId: auth()->id(),
                    occurredAt: now()->toIso8601String(),
                ));
            }

            if (array_key_exists('translations', $data)) {
                $this->persistTranslations(
                    roleName: $role->name,
                    translations: $data['translations'] ?? []
                );
            }

            return $this->loadApiState($role);
        });
    }

    /**
     * Sync permissions by permission names.
     *
     * WHY:
     * Frontend sends permission names.
     * Database relations must be synchronized by IDs.
     *
     * @param array<int, string> $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): void
    {
        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    /**
     * Persist localized role labels and descriptions.
     *
     * @param array<string, array{label?: string|null, description?: string|null}> $translations
     */
    public function persistTranslations(string $roleName, array $translations): void
    {
        $labels = [];
        $descriptions = [];

        foreach ($translations as $locale => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $labels[$locale] = isset($entry['label']) ? (string) $entry['label'] : null;
            $descriptions[$locale] = isset($entry['description']) ? (string) $entry['description'] : null;
        }

        $this->translationUpsert->saveTranslations('roles', $roleName, $labels);
        $this->translationUpsert->saveTranslations('role_descriptions', $roleName, $descriptions);
    }

    /**
     * Load role state required by current API response.
     */
    public function loadApiState(Role $role): Role
    {
        return $role->loadCount(['permissions', 'users']);
    }

    /**
     * Build additional role metadata used by current frontend contract.
     *
     * WHY:
     * Keeps API response shape unchanged while moving calculation
     * out of controller methods.
     *
     * @return array<string, mixed>
     */
    public function buildApiMeta(Role $role): array
    {
        return [
            'permissions' => $role->permissions()->pluck('permissions.name')->values()->all(),
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
            'status' => 'active',
            'type' => in_array(strtolower($role->name), ['admin', 'manager', 'user'], true) ? 'system' : 'custom',
            'created_at' => $role->created_at?->toISOString(),
        ];
    }
}
