<?php

namespace App\Events\Rbac;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $permissionId,
        public string $permissionName,
        public string $changeType,
        public ?int $actorId = null,
        public ?string $occurredAt = null,
    ) {
    }
}
