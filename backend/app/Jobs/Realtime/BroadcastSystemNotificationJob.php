<?php

namespace App\Jobs\Realtime;

use App\Events\SystemNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastSystemNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public string $createdAt,
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
        event(new SystemNotificationEvent(
            type: $this->type,
            title: $this->title,
            message: $this->message,
            createdAt: $this->createdAt,
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BroadcastSystemNotificationJob permanently failed', [
            'type' => $this->type,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}

