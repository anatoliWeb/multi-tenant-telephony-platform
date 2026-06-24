<?php

namespace App\DTO;

/**
 * Auth context transfer object.
 *
 * WHY:
 * Auth payload is consumed by both session and token flows.
 * Centralizing this shape in DTO keeps service contract explicit
 * without changing HTTP response structure.
 */
class AuthContextDTO
{
    /**
     * @param array<string, mixed>|null $user
     * @param array<int, string> $permissions
     * @param array<int, string> $platformPermissions
     * @param array<int, string> $tenantPermissions
     * @param array<int, string> $roles
     */
    public function __construct(
        public readonly ?array $user,
        public readonly array $permissions,
        public readonly array $platformPermissions,
        public readonly array $tenantPermissions,
        public readonly array $roles,
    ) {
    }

    /**
     * @return array{
     *   user: array<string, mixed>|null,
     *   permissions: array<int, string>,
     *   platform_permissions: array<int, string>,
     *   tenant_permissions: array<int, string>,
     *   roles: array<int, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'permissions' => $this->permissions,
            'platform_permissions' => $this->platformPermissions,
            'tenant_permissions' => $this->tenantPermissions,
            'roles' => $this->roles,
        ];
    }
}
