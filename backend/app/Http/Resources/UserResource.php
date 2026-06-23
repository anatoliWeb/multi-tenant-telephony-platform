<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User API resource.
 *
 * WHY THIS RESOURCE EXISTS:
 * API consumers (Vue admin, Angular dashboard, mobile clients) need a stable
 * and explicit contract that does not depend on internal model/service shape.
 *
 * WHY NOT RETURN RAW ELOQUENT:
 * Returning model instances directly can accidentally leak internal fields
 * and couples clients to backend implementation details.
 *
 * WHAT THIS RESOURCE CONTROLS:
 * It explicitly defines which user fields are exposed and keeps output
 * frontend-friendly and version-safe.
 */
class UserResource extends JsonResource
{
    /**
     * Transform a user payload into a stable API structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roles = array_values(data_get($this->resource, 'roles', []));
        $permissions = array_values(data_get($this->resource, 'permissions', []));
        $deniedPermissions = array_values(data_get($this->resource, 'denied_permissions', []));

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'email' => data_get($this->resource, 'email'),
            'roles' => $roles,
            'roles_labels' => collect($roles)
                ->mapWithKeys(fn (string $roleName) => [$roleName => $this->resolveRoleLabel($roleName)])
                ->all(),
            'permissions' => $permissions,
            'permissions_labels' => collect($permissions)
                ->mapWithKeys(fn (string $permissionName) => [$permissionName => $this->resolvePermissionLabel($permissionName)])
                ->all(),
            'denied_permissions' => $deniedPermissions,
            'denied_permissions_labels' => collect($deniedPermissions)
                ->mapWithKeys(fn (string $permissionName) => [$permissionName => $this->resolvePermissionLabel($permissionName)])
                ->all(),
            'labels' => [
                'name' => $this->translateWithFallback('users.columns.name', 'Name'),
                'email' => $this->translateWithFallback('users.columns.email', 'Email'),
                'roles' => $this->translateWithFallback('users.columns.roles', 'Roles'),
                'permissions' => $this->translateWithFallback('users.columns.permissions', 'Permissions'),
                'created_at' => $this->translateWithFallback('users.columns.created_at', 'Created'),
            ],
            'created_at' => data_get($this->resource, 'created_at'),
        ];
    }

    protected function resolveRoleLabel(string $name): string
    {
        foreach (['roles.' . $name, 'roles.role.' . $name] as $key) {
            $translated = dt($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $name;
    }

    protected function resolvePermissionLabel(string $name): string
    {
        foreach (['permissions.' . $name, 'permissions.permission.' . $name] as $key) {
            $translated = dt($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $name;
    }

    protected function translateWithFallback(string $key, string $fallback): string
    {
        $translated = dt($key);
        return $translated === $key ? $fallback : $translated;
    }
}
