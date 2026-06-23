<?php

namespace App\Services;

use App\Jobs\Realtime\BroadcastSystemNotificationJob;
use Illuminate\Support\Carbon;

class SocketService
{
    /**
     * Broadcast system notification event.
     *
     * WHY:
     * Controllers and domain services should not know the concrete
     * broadcasting event class.
     *
     * This service is a small abstraction layer for future Reverb,
     * private channels, presence channels and queue-based broadcasting.
     */
    public function broadcastSystemNotification(
        string $type = 'info',
        string $title = 'Realtime event',
        string $message = 'System notification delivered.',
        ?string $createdAt = null,
    ): void {
        BroadcastSystemNotificationJob::dispatch(
            type: $type,
            title: $title,
            message: $message,
            createdAt: $createdAt ?? Carbon::now()->toIso8601String(),
        );
    }
}
