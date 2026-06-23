<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use Illuminate\Support\Facades\Route;

class RbacMaintenanceService
{
    /**
     * @return array<string, int>
     */
    public function permissionStats(): array
    {
        return [
            'permissions_total' => Permission::query()->count(),
            'assigned_to_roles' => Permission::query()->whereHas('roles')->count(),
            'assigned_direct_to_users' => Permission::query()->whereHas('users')->count(),
            'unassigned' => Permission::query()->doesntHave('roles')->doesntHave('users')->count(),
        ];
    }

    /**
     * Sync permissions from route middleware declarations.
     *
     * @return array{created:int,existing:int,total:int}
     */
    public function syncPermissionsFromRoutes(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            foreach ((array) $route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    $raw = trim(substr($middleware, strlen('permission:')));
                    if ($raw !== '') {
                        $names[$raw] = true;
                    }
                }
            }
        }

        $created = 0;
        $existing = 0;

        foreach (array_keys($names) as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['description' => 'Auto-synced from route middleware.']
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'total' => count($names),
        ];
    }
}
