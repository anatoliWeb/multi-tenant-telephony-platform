<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Role Middleware
 *
 * Ensures that the authenticated user has at least one of the required roles.
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $roles Pipe-separated list of allowed roles
     */
    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = auth()->user();

        if (!$user) {
            abort(401);
        }

        $roles = explode('|', $roles);

        if (!$this->hasAnyScopedRole($user, $roles, app(TenantContext::class))) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * @param array<int, string> $roles
     */
    protected function hasAnyScopedRole(User $user, array $roles, TenantContext $tenantContext): bool
    {
        foreach ($roles as $role) {
            $role = trim($role);

            if ($role === '') {
                continue;
            }

            [$scope, $name] = str_contains($role, ':')
                ? explode(':', $role, 2)
                : ['platform', $role];

            if ($scope === 'tenant') {
                if ($tenantContext->hasTenant() && $user->roles()->where('name', $name)->where('scope', 'tenant')->exists()) {
                    return true;
                }

                continue;
            }

            if ($user->roles()->where('name', $name)->where('scope', 'platform')->exists()) {
                return true;
            }
        }

        return false;
    }
}

