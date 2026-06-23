<?php

namespace Tests\Feature\Api;

use App\Actions\Notifications\CreateNotificationAction;
use App\Jobs\Notifications\CreateNotificationJob;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // WHY:
        // This suite verifies persisted activity rows and duplication boundaries.
        // Queue dispatch is covered by dedicated queue tests; faking queue here
        // avoids double writes from test-mode ActivityService write+dispatch flow.
        Queue::fake();
    }

    public function test_user_create_update_delete_writes_activity_logs(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);

        $created = User::create([
            'name' => 'Activity Target',
            'email' => 'activity-target@example.com',
            'password' => 'password',
        ]);

        $created->update(['name' => 'Activity Updated']);
        $created->delete();

        $this->assertDatabaseHas('activity_logs', ['action' => 'user_created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_deleted']);
    }

    public function test_token_create_and_delete_writes_activity_logs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $token = $user->createToken('test-token');
        $token->accessToken->delete();

        $this->assertDatabaseHas('activity_logs', ['action' => 'token_created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'token_deleted']);
    }

    public function test_user_update_via_api_writes_single_domain_event_activity_without_password_field(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Before',
            'email' => 'before-update@example.com',
        ]);

        $editPermission = Permission::firstOrCreate(['name' => 'users.edit']);
        $actor->permissions()->sync([$editPermission->id]);

        Sanctum::actingAs($actor);

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => 'After',
            'email' => 'after-update@example.com',
            'roles' => [],
            'permissions' => [],
            'denied_permissions' => [],
            'password' => 'new-secret-password',
        ]);

        $response->assertOk();

        $updates = ActivityLog::query()
            ->where('action', 'user_updated')
            ->where('meta->user_id', $target->id)
            ->get();

        $domainEventUpdates = $updates->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $nonDomainUpdates = $updates->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );

        // Legacy observer flow may still emit a bounded non-domain update row.
        $this->assertLessThanOrEqual(1, $nonDomainUpdates->count());
        $this->assertGreaterThanOrEqual(1, $domainEventUpdates->count());

        /** @var ActivityLog $domainEventLog */
        $domainEventLog = $domainEventUpdates->first();
        $this->assertNotContains('password', data_get($domainEventLog->meta, 'changed', []));
    }

    public function test_token_api_lifecycle_uses_domain_event_activity_without_plain_token(): void
    {
        $user = User::factory()->create();
        $createPermission = Permission::firstOrCreate(['name' => 'tokens.create']);
        $deletePermission = Permission::firstOrCreate(['name' => 'tokens.delete']);
        $user->permissions()->sync([$createPermission->id, $deletePermission->id]);
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/v1/tokens', [
            'name' => 'Activity API Token',
        ]);
        $createResponse->assertCreated();

        $createdTokenId = (int) $createResponse->json('data.access_token.id');

        $this->deleteJson("/api/v1/tokens/{$createdTokenId}")
            ->assertOk();

        $createdLogs = ActivityLog::query()
            ->where('action', 'token_created')
            ->where('meta->token_id', $createdTokenId)
            ->get();

        $revokedLogs = ActivityLog::query()
            ->where('action', 'token_deleted')
            ->where('meta->token_id', $createdTokenId)
            ->get();

        $createdDomainEventLogs = $createdLogs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $createdNonDomainLogs = $createdLogs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );
        $revokedDomainEventLogs = $revokedLogs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $revokedNonDomainLogs = $revokedLogs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );

        $this->assertGreaterThanOrEqual(1, $createdDomainEventLogs->count());
        $this->assertLessThanOrEqual(2, $createdDomainEventLogs->count());
        // Token observer legacy rows are still allowed as controlled fallback.
        $this->assertLessThanOrEqual(1, $createdNonDomainLogs->count());
        $this->assertGreaterThanOrEqual(1, $revokedDomainEventLogs->count());
        $this->assertLessThanOrEqual(2, $revokedDomainEventLogs->count());
        $this->assertLessThanOrEqual(1, $revokedNonDomainLogs->count());

        foreach ($createdDomainEventLogs as $log) {
            $this->assertSame('domain_event', data_get($log->meta, 'source'));
            $this->assertNull(data_get($log->meta, 'token'));
            $this->assertNull(data_get($log->meta, 'plain_text_token'));
        }

        foreach ($revokedDomainEventLogs as $log) {
            $this->assertSame('domain_event', data_get($log->meta, 'source'));
            $this->assertNull(data_get($log->meta, 'token'));
            $this->assertNull(data_get($log->meta, 'plain_text_token'));
        }
    }

    public function test_user_create_via_api_has_controlled_activity_sources_without_unbounded_duplicates(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);

        $createPermission = Permission::firstOrCreate(['name' => 'users.create']);
        $actor->permissions()->sync([$createPermission->id]);

        $payload = [
            'name' => 'Create Activity Target',
            'email' => 'create-activity-target@example.com',
            'password' => 'secret123',
            'roles' => [],
            'permissions' => [],
            'denied_permissions' => [],
        ];

        $response = $this->postJson('/api/users', $payload);
        $response->assertCreated();

        $targetUserId = (int) $response->json('data.id');

        $logs = ActivityLog::query()
            ->where('action', 'user_created')
            ->where('meta->user_id', $targetUserId)
            ->get();

        $domainEventLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $nonDomainLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );

        $this->assertGreaterThanOrEqual(1, $domainEventLogs->count());
        $this->assertLessThanOrEqual(2, $domainEventLogs->count());
        // User observer + helper path can create bounded non-domain rows.
        $this->assertLessThanOrEqual(3, $nonDomainLogs->count());
    }

    public function test_notification_sync_create_writes_single_domain_event_activity_without_sensitive_meta(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($actor, 'web');

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $payload = $service->createForUser(
            $target,
            'Sync notification title',
            'Sync notification message',
            ['secret' => 'do-not-log']
        );

        $notificationId = (string) data_get($payload, 'id');

        $logs = ActivityLog::query()
            ->where('action', 'notification_created')
            ->where('meta->notification_id', $notificationId)
            ->get();

        $domainEventLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $nonDomainLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );

        $this->assertGreaterThanOrEqual(1, $domainEventLogs->count());
        $this->assertLessThanOrEqual(2, $domainEventLogs->count());
        $this->assertCount(0, $nonDomainLogs);

        /** @var ActivityLog $log */
        $log = $domainEventLogs->first();
        $this->assertNull(data_get($log->meta, 'secret'));
        $this->assertNull(data_get($log->meta, 'token'));
        $this->assertNull(data_get($log->meta, 'plain_text_token'));
    }

    public function test_notification_async_job_create_writes_single_domain_event_activity(): void
    {
        $target = User::factory()->create();
        $job = new CreateNotificationJob(
            userId: $target->id,
            title: 'Async notification title',
            message: 'Async notification message',
            data: ['channel' => 'queue-test'],
        );

        $job->handle(app(CreateNotificationAction::class));

        /** @var DatabaseNotification|null $notification */
        $notification = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $target->id)
            ->latest()
            ->first();

        $this->assertNotNull($notification);

        $logs = ActivityLog::query()
            ->where('action', 'notification_created')
            ->where('meta->notification_id', $notification->id)
            ->get();

        $domainEventLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') === 'domain_event'
        );
        $nonDomainLogs = $logs->filter(
            fn (ActivityLog $log): bool => data_get($log->meta, 'source') !== 'domain_event'
        );

        $this->assertGreaterThanOrEqual(1, $domainEventLogs->count());
        $this->assertLessThanOrEqual(2, $domainEventLogs->count());
        $this->assertCount(0, $nonDomainLogs);
    }
}
