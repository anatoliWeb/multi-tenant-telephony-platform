<?php

namespace App\Jobs\Realtime;

use App\Events\Notifications\DatabaseNotificationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastDatabaseNotificationCreatedJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {
        $this->onQueue('realtime');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        event(new DatabaseNotificationCreated(
            userId: $this->userId,
            payload: $this->payload,
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BroadcastDatabaseNotificationCreatedJob permanently failed', [
            'user_id' => $this->userId,
            'notification_id' => $this->payload['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}

