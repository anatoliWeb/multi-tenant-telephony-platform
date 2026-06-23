<?php

namespace App\Listeners\Auth;

use App\Events\Auth\TokenCreated;
use App\Services\ActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LogTokenCreatedActivity implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function handle(TokenCreated $event): void
    {
        $this->activityService->log(
            userId: $event->actorId,
            action: 'token_created',
            description: 'API token created',
            meta: [
                'token_id' => $event->tokenId,
                'token_name' => $event->tokenName,
                'tokenable_id' => $event->tokenableId,
                'abilities' => $event->abilities,
                'source' => 'domain_event',
            ]
        );
    }
}
