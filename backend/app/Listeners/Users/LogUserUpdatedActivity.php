<?php

namespace App\Listeners\Users;

use App\Events\Users\UserUpdated;
use App\Services\ActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LogUserUpdatedActivity implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function handle(UserUpdated $event): void
    {
        $this->activityService->log(
            userId: $event->actorId,
            action: 'user_updated',
            description: 'User updated',
            meta: [
                'user_id' => $event->userId,
                'email' => $event->userEmail,
                'name' => $event->userName,
                'changed' => $event->changedFields,
                'source' => 'domain_event',
            ]
        );
    }
}
