<?php

namespace App\Jobs\Realtime;

use App\Events\Activity\ActivityLogged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastActivityLoggedJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    /**
     * @param array<string, mixed> $activity
     */
    public function __construct(public array $activity)
    {
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
        event(new ActivityLogged($this->activity));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BroadcastActivityLoggedJob permanently failed', [
            'activity_id' => $this->activity['id'] ?? null,
            'action' => $this->activity['action'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}

