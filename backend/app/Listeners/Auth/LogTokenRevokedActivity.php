<?php

namespace App\Listeners\Auth;

use App\Events\Auth\TokenRevoked;
use App\Services\ActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LogTokenRevokedActivity implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function handle(TokenRevoked $event): void
    {
        $this->activityService->log(
            userId: $event->actorId,
            action: 'token_deleted',
            description: 'API token deleted',
            meta: [
                'token_id' => $event->tokenId,
                'token_name' => $event->tokenName,
                'tokenable_id' => $event->tokenableId,
                'revoke_reason' => $event->revokeReason,
                'source' => 'domain_event',
            ]
        );
    }
}
