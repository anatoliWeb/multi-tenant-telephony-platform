<?php

namespace Tests\Feature\Api;

use App\Events\Notifications\DatabaseNotificationCreated;
use App\Jobs\Realtime\BroadcastDatabaseNotificationCreatedJob;
use App\Models\Permission;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_sync_notification_creation_dispatches_realtime_broadcast_job_with_safe_payload(): void
    {
        Queue::fake();

        $user = $this->actingAsWithPermissions(['notifications.view']);

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $created = $service->createForUser($user, 'Realtime title', 'Realtime message', [
            'secret' => 'hidden',
        ]);

        $this->assertNotEmpty($created);

        Queue::assertPushed(BroadcastDatabaseNotificationCreatedJob::class, function (BroadcastDatabaseNotificationCreatedJob $job) use ($user): bool {
            return $job->userId === $user->id
                && isset($job->payload['id'])
                && ($job->payload['title'] ?? null) === 'Realtime title'
                && ($job->payload['message'] ?? null) === 'Realtime message'
                && !array_key_exists('data', $job->payload)
                && !array_key_exists('secret', $job->payload);
        });

        Queue::assertPushedOn('realtime', BroadcastDatabaseNotificationCreatedJob::class);
    }

    public function test_realtime_disabled_prevents_broadcast_but_keeps_database_notification_creation(): void
    {
        Queue::fake();

        $user = $this->actingAsWithPermissions(['notifications.view']);

        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'realtime.enabled' => false,
            ],
        ])->assertOk();

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $created = $service->createForUser($user, 'No realtime', 'Stored only');

        $this->assertNotEmpty($created);
        $this->assertSame(1, DatabaseNotification::query()->count());
        Queue::assertNotPushed(BroadcastDatabaseNotificationCreatedJob::class);
    }

    public function test_system_disabled_prevents_creation_and_broadcast(): void
    {
        Queue::fake();

        $user = $this->actingAsWithPermissions(['notifications.view']);

        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'system.enabled' => false,
            ],
        ])->assertOk();

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $created = $service->createForUser($user, 'Blocked', 'Blocked');

        $this->assertSame([], $created);
        $this->assertSame(0, DatabaseNotification::query()->count());
        Queue::assertNotPushed(BroadcastDatabaseNotificationCreatedJob::class);
    }

    public function test_notification_broadcast_job_has_explicit_retry_policy(): void
    {
        $job = new BroadcastDatabaseNotificationCreatedJob(
            userId: 10,
            payload: [
                'id' => 'test-id',
                'type' => 'system',
                'title' => 'Title',
                'message' => 'Message',
                'is_read' => false,
                'read_at' => null,
                'created_at' => now()->toIso8601String(),
            ],
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_notification_broadcast_event_uses_private_owner_channel_with_safe_payload(): void
    {
        $event = new DatabaseNotificationCreated(
            userId: 42,
            payload: [
                'id' => 'n-42',
                'type' => 'system',
                'title' => 'Broadcast title',
                'message' => 'Broadcast message',
                'is_read' => false,
                'read_at' => null,
                'created_at' => now()->toIso8601String(),
                'data' => ['secret' => 'hidden'],
            ],
        );

        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();
        $hasOwnerPrivateChannel = false;

        foreach ($channels as $channel) {
            if (
                $channel instanceof PrivateChannel
                && in_array($channel->name, ['private-notifications.user.42', 'notifications.user.42'], true)
            ) {
                $hasOwnerPrivateChannel = true;
            }
        }

        $this->assertSame('notification.created', $event->broadcastAs());
        $this->assertTrue($hasOwnerPrivateChannel);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('is_read', $payload);
        $this->assertArrayHasKey('read_at', $payload);
        $this->assertArrayHasKey('created_at', $payload);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
    }
}
