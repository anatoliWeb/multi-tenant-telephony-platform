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
     * @param array<int, string> $roles
     */
    public function __construct(
        public readonly ?array $user,
        public readonly array $permissions,
        public readonly array $roles,
    ) {
    }

    /**
     * @return array{
     *   user: array<string, mixed>|null,
     *   permissions: array<int, string>,
     *   roles: array<int, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'permissions' => $this->permissions,
            'roles' => $this->roles,
        ];
    }
}

