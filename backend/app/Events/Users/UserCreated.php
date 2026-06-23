<?php

namespace App\Events\Users;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $userName,
        public string $userEmail,
        public ?int $actorId = null,
        public ?string $occurredAt = null,
    ) {
    }
}

