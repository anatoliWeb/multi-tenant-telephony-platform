<?php

namespace App\Jobs;

use App\Services\ActivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogActivityJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Explicit retry policy for activity writes.
     */
    public int $tries = 3;

    /**
     * Job timeout in seconds.
     *
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    /**
     * Dedicated queue for activity writes.
     *
     * WHY:
     * Isolates audit logging throughput from other async domains
     * like notifications or future realtime delivery jobs.
     */
//    public $queue = 'activity';

    /**
     * Progressive retry delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $userId,
        public string $action,
        public ?string $description = null,
        public array $meta = [],
    ) {
        $this->onQueue('activity');
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityService $activityService): void
    {
        $activityService->write(
            $this->userId,
            $this->action,
            $this->description,
            $this->meta,
        );
    }

    /**
     * Handle terminal job failure after all retries.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('LogActivityJob permanently failed', [
            'action' => $this->action,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
