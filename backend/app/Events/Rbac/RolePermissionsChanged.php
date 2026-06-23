<?php

namespace App\Events\Rbac;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RolePermissionsChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, string> $permissionNames
     */
    public function __construct(
        public int $roleId,
        public string $roleName,
        public array $permissionNames = [],
        public ?int $actorId = null,
        public ?string $occurredAt = null,
    ) {
    }
}
