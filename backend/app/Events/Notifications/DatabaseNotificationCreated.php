<?php

namespace App\Events\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatabaseNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("notifications.user.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => (string) ($this->payload['id'] ?? ''),
            'type' => (string) ($this->payload['type'] ?? 'system'),
            'title' => $this->payload['title'] ?? null,
            'message' => $this->payload['message'] ?? null,
            'is_read' => (bool) ($this->payload['is_read'] ?? false),
            'read_at' => $this->payload['read_at'] ?? null,
            'created_at' => $this->payload['created_at'] ?? null,
        ];
    }
}

