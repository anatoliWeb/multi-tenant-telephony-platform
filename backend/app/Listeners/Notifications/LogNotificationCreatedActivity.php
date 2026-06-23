<?php

namespace App\Listeners\Notifications;

use App\Events\Notifications\NotificationCreated;
use App\Services\ActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LogNotificationCreatedActivity implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function handle(NotificationCreated $event): void
    {
        $this->activityService->log(
            userId: $event->actorId,
            action: 'notification_created',
            description: 'Notification created',
            meta: [
                'notification_id' => $event->notificationId,
                'notifiable_id' => $event->notifiableId,
                'type' => $event->type,
                'title' => $event->title,
                'message' => $event->message,
                'source' => 'domain_event',
            ],
        );
    }
}

