<?php

namespace App\DTO;

/**
 * Data Transfer Object for application statistics.
 *
 * Ensures consistent structure for dashboard data.
 */
class StatsDTO
{
    public function __construct(
        public readonly int $users,
        public readonly int $roles,
        public readonly int $permissions,
        public readonly int $activityLogs,
        public readonly int $admins,
        public readonly int $managers,
        public readonly int $tokens,
        public readonly int $usersWithDirectPermissions,
        /** @var array<int, array<string, mixed>> */
        public readonly array $recentActivity,
    ) {
    }

    /**
     * Convert DTO to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'users' => $this->users,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
            'activity_logs' => $this->activityLogs,
            'admins' => $this->admins,
            'managers' => $this->managers,
            'tokens' => $this->tokens,
            'users_with_direct_permissions' => $this->usersWithDirectPermissions,
            'recent_activity' => $this->recentActivity,
        ];
    }
}
