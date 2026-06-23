<?php

namespace App\Events\Users;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, string> $changedFields
     */
    public function __construct(
        public int $userId,
        public string $userName,
        public string $userEmail,
        public ?int $actorId = null,
        public array $changedFields = [],
        public ?string $occurredAt = null,
    ) {
    }
}
