<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeliveryUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $broadcastQueue = 'realtime';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $conversationId,
        public array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.delivery.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

