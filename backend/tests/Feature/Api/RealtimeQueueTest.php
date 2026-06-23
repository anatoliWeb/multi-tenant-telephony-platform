<?php

namespace Tests\Feature\Api;

use App\Events\SystemNotificationEvent;
use App\Jobs\Realtime\BroadcastSystemNotificationJob;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_realtime_notify_endpoint_dispatches_realtime_queue_job_and_keeps_response_shape(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/realtime/notify', [
            'type' => 'info',
            'title' => 'Queue test',
            'message' => 'Realtime queued dispatch',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['dispatched'],
            ])
            ->assertJsonPath('data.dispatched', true);

        Queue::assertPushed(BroadcastSystemNotificationJob::class, function (BroadcastSystemNotificationJob $job): bool {
            return $job->type === 'info'
                && $job->title === 'Queue test'
                && $job->message === 'Realtime queued dispatch'
                && $job->createdAt !== '';
        });

        Queue::assertPushedOn('realtime', BroadcastSystemNotificationJob::class);
    }

    public function test_realtime_broadcast_job_has_explicit_retry_policy(): void
    {
        $job = new BroadcastSystemNotificationJob(
            type: 'info',
            title: 'Retry title',
            message: 'Retry message',
            createdAt: now()->toIso8601String(),
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_realtime_broadcast_job_dispatches_system_notification_event_with_stable_payload_shape(): void
    {
        Event::fake([SystemNotificationEvent::class]);

        $job = new BroadcastSystemNotificationJob(
            type: 'warning',
            title: 'Realtime title',
            message: 'Realtime message',
            createdAt: now()->toIso8601String(),
        );

        $job->handle();

        Event::assertDispatched(SystemNotificationEvent::class, function (SystemNotificationEvent $event): bool {
            $payload = $event->broadcastWith();
            $channels = $event->broadcastOn();
            $hasPublic = false;
            $hasPrivate = false;

            foreach ($channels as $channel) {
                if ($channel instanceof Channel && $channel->name === 'system.notifications') {
                    $hasPublic = true;
                }

                if ($channel instanceof PrivateChannel && $channel->name === 'private-system.notifications') {
                    $hasPrivate = true;
                }
            }

            return $event->broadcastAs() === 'system.notification'
                && $hasPublic
                && $hasPrivate
                && array_key_exists('type', $payload)
                && array_key_exists('title', $payload)
                && array_key_exists('message', $payload)
                && array_key_exists('created_at', $payload)
                && $payload['type'] === 'warning'
                && $payload['title'] === 'Realtime title'
                && $payload['message'] === 'Realtime message';
        });
    }
}
