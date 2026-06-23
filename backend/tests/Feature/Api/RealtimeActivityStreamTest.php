<?php

namespace Tests\Feature\Api;

use App\Events\Activity\ActivityLogged;
use App\Jobs\Realtime\BroadcastActivityLoggedJob;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealtimeActivityStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_write_dispatches_realtime_activity_broadcast_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();

        /** @var ActivityService $service */
        $service = app(ActivityService::class);
        $service->write(
            userId: $actor->id,
            action: 'user_updated',
            description: 'Realtime stream activity',
            meta: [
                'module' => 'users',
                'source' => 'domain_event',
                'token' => 'must-not-be-streamed',
            ],
        );

        Queue::assertPushedOn('realtime', BroadcastActivityLoggedJob::class);
        Queue::assertPushed(BroadcastActivityLoggedJob::class, function (BroadcastActivityLoggedJob $job): bool {
            return isset($job->activity['id'])
                && $job->activity['action'] === 'user_updated'
                && $job->activity['description'] === 'Realtime stream activity'
                && array_key_exists('user', $job->activity)
                && array_key_exists('created_at', $job->activity)
                && data_get($job->activity, 'meta.source') === 'domain_event'
                && data_get($job->activity, 'meta.module') === 'users'
                && data_get($job->activity, 'meta.token') === null
                && data_get($job->activity, 'token') === null
                && data_get($job->activity, 'password') === null;
        });
    }

    public function test_activity_logged_event_has_private_channel_and_stable_safe_payload(): void
    {
        Event::fake([ActivityLogged::class]);

        $job = new BroadcastActivityLoggedJob([
            'id' => 99,
            'action' => 'permission_changed',
            'description' => 'Permission updated',
            'user' => ['id' => 7, 'name' => 'Admin'],
            'created_at' => now()->toISOString(),
            'meta' => ['source' => 'domain_event', 'module' => 'rbac'],
        ]);

        $job->handle();

        Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $event): bool {
            $channel = $event->broadcastOn();
            $payload = $event->broadcastWith();

            return $channel instanceof PrivateChannel
                && $channel->name === 'private-activity.stream'
                && $event->broadcastAs() === 'activity.logged'
                && array_key_exists('id', $payload)
                && array_key_exists('action', $payload)
                && array_key_exists('description', $payload)
                && array_key_exists('user', $payload)
                && array_key_exists('created_at', $payload)
                && data_get($payload, 'meta.source') === 'domain_event'
                && data_get($payload, 'meta.module') === 'rbac'
                && data_get($payload, 'token') === null
                && data_get($payload, 'password') === null
                && data_get($payload, 'meta.token') === null
                && data_get($payload, 'meta.password') === null;
        });
    }

    public function test_activity_stream_broadcast_does_not_create_duplicate_rows(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $beforeCount = ActivityLog::query()
            ->where('action', 'settings_updated')
            ->count();

        /** @var ActivityService $service */
        $service = app(ActivityService::class);
        $service->write(
            userId: $actor->id,
            action: 'settings_updated',
            description: 'Settings changed',
            meta: ['module' => 'settings'],
        );

        $afterCount = ActivityLog::query()
            ->where('action', 'settings_updated')
            ->count();

        $this->assertSame($beforeCount + 1, $afterCount);
    }
}
