<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Role Middleware
 *
 * Ensures that the authenticated user has at least one of the required roles.
 *
 * Usage examples:
 * - role:admin
 * - role:admin|manager
 *
 * This middleware is typically used to protect admin routes or features.
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

        // WHY:
        // Middleware should explicitly guard access.
        // If user is not authenticated, return 401 (Unauthorized).
        if (!$user) {
            abort(401);
        }

        // Convert "admin|manager" → ['admin', 'manager']
        $roles = explode('|', $roles);

        // WHY:
        // We allow access if user has ANY of the required roles.
        // This keeps middleware flexible for multiple-role access control.
        if (!$user->hasAnyRole($roles)) {
            abort(403); // Forbidden
        }

        return $next($request);
    }
}
