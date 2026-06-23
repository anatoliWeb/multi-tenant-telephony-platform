<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiDocsPermissionService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function groups(): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = config('api-docs.groups', []);

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function groupForPath(string $path): ?array
    {
        $normalizedPath = '/'.ltrim($path, '/');

        foreach ($this->groups() as $key => $group) {
            $patterns = Arr::wrap($group['paths'] ?? []);
            foreach ($patterns as $pattern) {
                if (! is_string($pattern)) {
                    continue;
                }

                if (Str::is($pattern, $normalizedPath)) {
                    return ['key' => $key] + $group;
                }
            }
        }

        return null;
    }

    public function userCanSeeGroup(?User $user, string $groupKey): bool
    {
        $group = $this->groups()[$groupKey] ?? null;
        if (! is_array($group)) {
            return false;
        }

        if ($this->userHasFullDocsAccess($user)) {
            // WHY:
            // Full docs access keeps raw and filtered docs behavior consistent for privileged users.
            return true;
        }

        if (($group['public'] ?? false) === true) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $permissionsAny = Arr::wrap($group['permissions_any'] ?? []);
        if (count($permissionsAny) > 0 && $user->hasAnyPermission($permissionsAny)) {
            return true;
        }

        $permissionsAll = Arr::wrap($group['permissions_all'] ?? []);
        if (count($permissionsAll) > 0 && collect($permissionsAll)->every(fn (string $permission): bool => $user->hasPermission($permission))) {
            return true;
        }

        return false;
    }

    /**
     * Check whether a user can view a route path in filtered OpenAPI output.
     *
     * Unknown paths are denied by default unless the caller has full docs access.
     */
    public function userCanSeePath(?User $user, string $path): bool
    {
        $group = $this->groupForPath($path);
        if (! is_array($group)) {
            // WHY:
            // Unknown paths default to deny for regular users.
            // Only explicit full-docs access can bypass group mapping.
            return $this->userHasFullDocsAccess($user);
        }

        $key = (string) ($group['key'] ?? '');
        if ($key === '') {
            return false;
        }

        return $this->userCanSeeGroup($user, $key);
    }

    /**
     * Full docs access gate for raw Swagger/OpenAPI endpoints.
     */
    public function userHasFullDocsAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasPermission('api.docs.view.full')) {
            return true;
        }

        // WHY:
        // Admin fallback preserves operational access even when role-permission sync drifts.
        // Route middleware still protects access and no anonymous path is opened.
        return $user->hasRole('admin');
    }
}
