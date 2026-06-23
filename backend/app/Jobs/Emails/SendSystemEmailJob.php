<?php

namespace App\Jobs\Emails;

use App\Mail\SystemEmailMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSystemEmailJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
    ) {
        $this->onQueue('emails');
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
        Mail::to($this->to)->send(
            new SystemEmailMailable(
                subjectLine: $this->subject,
                body: $this->body,
            )
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendSystemEmailJob permanently failed', [
            'to' => $this->to,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]);
    }
}
