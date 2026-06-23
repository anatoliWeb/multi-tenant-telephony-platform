<?php

namespace App\Events\Activity;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $activity
     */
    public function __construct(public array $activity)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('activity.stream');
    }

    public function broadcastAs(): string
    {
        return 'activity.logged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->activity;
    }
}

