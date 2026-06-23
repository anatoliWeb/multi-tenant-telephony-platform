<?php

namespace App\Listeners\Users;

use App\Events\Users\UserCreated;
use App\Services\ActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LogUserCreatedActivity implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function handle(UserCreated $event): void
    {
        $this->activityService->log(
            userId: $event->actorId,
            action: 'user_created',
            description: 'User created',
            meta: [
                'user_id' => $event->userId,
                'email' => $event->userEmail,
                'name' => $event->userName,
                'source' => 'domain_event',
            ]
        );
    }
}
