<?php

namespace Tests\Feature;

use App\Jobs\LogActivityJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActivityQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_job_has_explicit_retry_policy(): void
    {
        $job = new LogActivityJob(
            userId: null,
            action: 'test_action',
            description: 'Test',
            meta: []
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_user_created_dispatches_activity_job_to_activity_queue(): void
    {
        Queue::fake();

        User::factory()->create();

        Queue::assertPushed(LogActivityJob::class, function (LogActivityJob $job) {
            return $job->action === 'user_created'
                && $job->description === 'User created'
                && array_key_exists('user_id', $job->meta);
        });

        Queue::assertPushedOn('activity', LogActivityJob::class);
    }

    public function test_user_updated_dispatches_activity_job_to_activity_queue(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $user->update(['name' => 'Updated Name']);

        Queue::assertPushed(LogActivityJob::class, function (LogActivityJob $job) use ($user) {
            return $job->action === 'user_updated'
                && $job->description === 'User updated'
                && ($job->meta['user_id'] ?? null) === $user->id;
        });

        Queue::assertPushedOn('activity', LogActivityJob::class);
    }

    public function test_user_deleted_dispatches_activity_job_to_activity_queue(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $user->delete();

        Queue::assertPushed(LogActivityJob::class, function (LogActivityJob $job) use ($user) {
            return $job->action === 'user_deleted'
                && $job->description === 'User deleted'
                && ($job->meta['user_id'] ?? null) === $user->id;
        });

        Queue::assertPushedOn('activity', LogActivityJob::class);
    }

    public function test_token_created_dispatches_activity_job_to_activity_queue(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $user->createToken('feature-test-token');

        Queue::assertPushed(LogActivityJob::class, function (LogActivityJob $job) use ($user) {
            return $job->action === 'token_created'
                && $job->description === 'API token created'
                && ($job->meta['tokenable_id'] ?? null) === $user->id;
        });

        Queue::assertPushedOn('activity', LogActivityJob::class);
    }
}
