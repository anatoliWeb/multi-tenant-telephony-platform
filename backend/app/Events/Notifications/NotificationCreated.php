<?php

namespace App\Events\Notifications;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $notificationId,
        public int $notifiableId,
        public string $type,
        public ?string $title = null,
        public ?string $message = null,
        public ?int $actorId = null,
        public ?string $occurredAt = null,
    ) {
    }
}

