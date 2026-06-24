<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Check user permissions.
 *
 * Usage:
 * - permission:platform.users.view
 * - permission:tenant.chat.view
 * - permission:platform.users.view|platform.roles.view  (any)
 * - permission:platform.users.view,platform.users.edit (all)
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = auth()->user();

        if (!$user) {
            abort(401);
        }

        $cache = app(PermissionCacheService::class);
        $tenantContext = app(TenantContext::class);

        if (str_contains($permissions, ',')) {
            $required = explode(',', $permissions);

            foreach ($required as $permission) {
                if (!$this->canForScope($user, trim($permission), $cache, $tenantContext)) {
                    abort(403);
                }
            }

            return $next($request);
        }

        if (str_contains($permissions, '|')) {
            $required = explode('|', $permissions);

            foreach ($required as $permission) {
                if ($this->canForScope($user, trim($permission), $cache, $tenantContext)) {
                    return $next($request);
                }
            }

            abort(403);
        }

        if (!$this->canForScope($user, trim($permissions), $cache, $tenantContext)) {
            abort(403);
        }

        return $next($request);
    }

    protected function canForScope(User $user, string $permission, PermissionCacheService $cache, TenantContext $tenantContext): bool
    {
        [$scope, $name] = $this->splitScopedPermission($permission);

        return match ($scope) {
            'tenant' => $tenantContext->hasTenant()
                && in_array($name, $cache->getTenantPermissionsForUser($user, $tenantContext->requireTenant()), true),
            'platform' => in_array($name, $cache->getPlatformPermissionsForUser($user), true),
            default => in_array($name, $cache->getPlatformPermissionsForUser($user), true),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitScopedPermission(string $permission): array
    {
        foreach (['platform.', 'tenant.'] as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                return [rtrim($prefix, '.'), substr($permission, strlen($prefix))];
            }
        }

        return ['platform', $permission];
    }
}

