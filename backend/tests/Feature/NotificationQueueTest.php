<?php

namespace Tests\Feature;

use App\Actions\Notifications\CreateNotificationAction;
use App\Jobs\Notifications\CreateNotificationJob;
use App\Models\Permission;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_for_user_pushes_notification_job_to_notifications_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $service->dispatchForUser(
            $user,
            'Queued title',
            'Queued message',
            ['source' => 'test'],
        );

        Queue::assertPushed(CreateNotificationJob::class, function (CreateNotificationJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->title === 'Queued title'
                && $job->message === 'Queued message'
                && ($job->data['source'] ?? null) === 'test';
        });

        Queue::assertPushedOn('notifications', CreateNotificationJob::class);
    }

    public function test_notification_job_has_explicit_retry_policy(): void
    {
        $job = new CreateNotificationJob(
            userId: 1,
            title: 'Retry title',
            message: 'Retry message',
            data: [],
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_notification_job_handle_creates_database_notification_via_action(): void
    {
        $user = User::factory()->create();

        $job = new CreateNotificationJob(
            userId: $user->id,
            title: 'System title',
            message: 'System message',
            data: ['channel' => 'queue'],
        );

        $job->handle(app(CreateNotificationAction::class));

        /** @var DatabaseNotification|null $notification */
        $notification = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('System title', $notification->data['title'] ?? null);
        $this->assertSame('System message', $notification->data['message'] ?? null);
        $this->assertSame('queue', $notification->data['channel'] ?? null);
        $this->assertNull($notification->read_at);
    }

    public function test_dispatch_for_user_is_skipped_when_system_notifications_are_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'notifications.view']);
        $user->permissions()->sync([$permission->id]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'system.enabled' => false,
            ],
        ])->assertOk();

        $service = app(NotificationService::class);
        $service->dispatchForUser($user, 'Skipped title', 'Skipped message');

        Queue::assertNotPushed(CreateNotificationJob::class);
    }
}
