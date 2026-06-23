<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public string $createdAt,
    ) {
    }

    public function broadcastOn(): array
    {
        // Keep public channel for backward-compatible smoke checks while
        // private channel authorization is being rolled out.
        return [
            new Channel('system.notifications'),
            new PrivateChannel('system.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'system.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'created_at' => $this->createdAt,
        ];
    }
}
