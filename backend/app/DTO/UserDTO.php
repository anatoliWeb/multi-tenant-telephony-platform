<?php

namespace App\DTO;

/**
 * Data Transfer Object for User.
 *
 * Defines the structure of user data
 * passed from service layer to UI/API.
 */
class UserDTO
{
    /**
     * User identifier.
     */
    public int $id;

    /**
     * User name.
     */
    public string $name;

    /**
     * User email.
     */
    public string $email;

    /**
     * User roles.
     *
     * @var array<int, string>
     */
    public array $roles;

    /**
     * Direct user permissions.
     *
     * @var array<int, string>
     */
    public array $permissions;

    /**
     * Explicitly denied permissions.
     *
     * @var array<int, string>
     */
    public array $denied_permissions;

    /**
     * User creation timestamp (ISO-8601).
     */
    public ?string $created_at;

    /**
     * Create new UserDTO instance.
     *
     * @param int $id
     * @param string $name
     * @param string $email
     * @param array<int, string> $roles
     * @param array<int, string> $permissions
     * @param array<int, string> $deniedPermissions
     * @param string|null $createdAt
     */
    public function __construct(
        int $id,
        string $name,
        string $email,
        array $roles = [],
        array $permissions = [],
        array $deniedPermissions = [],
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->roles = $roles;
        $this->permissions = $permissions;
        $this->denied_permissions = $deniedPermissions;
        $this->created_at = $createdAt;
    }

    /**
     * Convert DTO to array.
     *
     * Used for JSON/API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
            'denied_permissions' => $this->denied_permissions,
            'created_at' => $this->created_at,
        ];
    }
}
