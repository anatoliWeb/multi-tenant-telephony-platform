<?php

namespace App\Services;

use App\Events\Rbac\PermissionChanged;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Services\Localization\TranslationUpsertService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(
        protected TranslationUpsertService $translationUpsert
    ) {
    }

    /**
     * Get permissions list for API.
     *
     * WHY:
     * Query and transformation logic belongs to the service layer,
     * not to HTTP controllers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissionsForApi(): array
    {
        return Permission::query()
            ->with(['roles:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission): array => $this->transformPermission($permission))
            ->values()
            ->all();
    }

    /**
     * Create permission with translations.
     *
     * WHY:
     * Permission creation is a domain operation:
     * - create permission
     * - persist localized label/description
     * - load usage counters
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Permission
    {
        return DB::transaction(function () use ($data): Permission {
            $permission = Permission::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $this->persistTranslations(
                permissionName: $permission->name,
                translations: $data['translations'] ?? []
            );

            event(new PermissionChanged(
                permissionId: $permission->id,
                permissionName: $permission->name,
                changeType: 'created',
                actorId: auth()->id(),
                occurredAt: now()->toIso8601String(),
            ));

            return $permission->load(['roles:id,name'])->loadCount('roles');
        });
    }

    /**
     * Update permission with translations.
     *
     * WHY:
     * Technical permission key remains immutable for RBAC safety.
     *
     * @param array<string, mixed> $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        return DB::transaction(function () use ($permission, $data): Permission {
            $permission->update([
                'description' => $data['description'] ?? $permission->description,
            ]);

            $this->persistTranslations(
                permissionName: $permission->name,
                translations: $data['translations'] ?? []
            );

            event(new PermissionChanged(
                permissionId: $permission->id,
                permissionName: $permission->name,
                changeType: 'updated',
                actorId: auth()->id(),
                occurredAt: now()->toIso8601String(),
            ));

            return $permission->load(['roles:id,name'])->loadCount('roles');
        });
    }

    /**
     * Transform permission to current API response shape.
     *
     * WHY:
     * Keeps frontend contract unchanged while moving presentation metadata
     * out of the controller.
     *
     * @return array<string, mixed>
     */
    public function transformPermission(Permission $permission): array
    {
        $permission->loadMissing(['roles:id,name']);

        $type = $this->inferType($permission->name);
        $group = explode('.', $permission->name)[0] ?? 'system';

        return array_merge(
            (new PermissionResource($permission))->resolve(),
            [
                'module' => $group,
                'group_label' => $this->translateWithFallback(
                    'permissions.groups.' . $group,
                    ucfirst(str_replace('_', ' ', $group))
                ),
                'used_by_roles' => $permission->roles->pluck('name')->values()->all(),
                'type' => $type,
                'type_label' => $this->translateWithFallback(
                    'permissions.types.' . $type,
                    ucfirst($type)
                ),
                'usage' => $permission->roles->isNotEmpty() ? 'used' : 'unused',
                'created_at' => $permission->created_at?->toISOString(),
            ]
        );
    }

    /**
     * Persist localized permission labels and descriptions.
     *
     * @param array<string, array{label?: string|null, description?: string|null}> $translations
     */
    public function persistTranslations(string $permissionName, array $translations): void
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

        $this->translationUpsert->saveTranslations('permissions', $permissionName, $labels);
        $this->translationUpsert->saveTranslations('permission_descriptions', $permissionName, $descriptions);
    }

    /**
     * Infer permission type from technical permission name.
     *
     * WHY:
     * This keeps permission metadata consistent across API responses.
     */
    protected function inferType(string $permissionName): string
    {
        $suffix = collect(explode('.', $permissionName))->slice(1)->implode('.');

        if (str_contains($suffix, 'view') || str_contains($suffix, 'list') || str_contains($suffix, 'show')) {
            return 'read';
        }

        if (
            str_contains($suffix, 'create')
            || str_contains($suffix, 'edit')
            || str_contains($suffix, 'update')
            || str_contains($suffix, 'delete')
        ) {
            return 'write';
        }

        return 'manage';
    }

    /**
     * Translate label or return fallback.
     *
     * WHY:
     * API should provide safe human-readable labels even when translation
     * keys are missing.
     */
    protected function translateWithFallback(string $key, string $fallback): string
    {
        $translated = dt($key);

        return $translated === $key ? $fallback : $translated;
    }
}
