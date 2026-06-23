<?php

namespace Tests\Feature;

use App\Jobs\Emails\SendSystemEmailJob;
use App\Mail\SystemEmailMailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_email_job_dispatches_to_emails_queue(): void
    {
        Queue::fake();

        SendSystemEmailJob::dispatch(
            to: 'qa@example.com',
            subject: 'Queue foundation',
            body: 'Email queue baseline test',
        );

        Queue::assertPushed(SendSystemEmailJob::class, function (SendSystemEmailJob $job): bool {
            return $job->to === 'qa@example.com'
                && $job->subject === 'Queue foundation'
                && $job->body === 'Email queue baseline test';
        });

        Queue::assertPushedOn('emails', SendSystemEmailJob::class);
    }

    public function test_system_email_job_has_explicit_retry_policy(): void
    {
        $job = new SendSystemEmailJob(
            to: 'ops@example.com',
            subject: 'Retry check',
            body: 'Retry policy validation',
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_system_email_job_handle_sends_email(): void
    {
        Mail::fake();

        $job = new SendSystemEmailJob(
            to: 'alerts@example.com',
            subject: 'System alert',
            body: 'Background mail delivery test',
        );

        $job->handle();

        Mail::assertSent(SystemEmailMailable::class, function (SystemEmailMailable $mail): bool {
            return $mail->hasTo('alerts@example.com')
                && $mail->subjectLine === 'System alert'
                && $mail->body === 'Background mail delivery test';
        });
    }
}
