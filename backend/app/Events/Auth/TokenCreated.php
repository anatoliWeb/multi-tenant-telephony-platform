<?php

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, string> $abilities
     */
    public function __construct(
        public int $tokenId,
        public string $tokenName,
        public int $tokenableId,
        public ?int $actorId = null,
        public array $abilities = [],
        public ?string $occurredAt = null,
    ) {
    }
}
