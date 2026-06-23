<?php

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $tokenId,
        public string $tokenName,
        public int $tokenableId,
        public ?int $actorId = null,
        public ?string $revokeReason = null,
        public ?string $occurredAt = null,
    ) {
    }
}
