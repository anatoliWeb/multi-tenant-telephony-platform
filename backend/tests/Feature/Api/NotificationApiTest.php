<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
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

    protected function createNotificationForUser(User $user, ?string $readAt = null): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'system',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Test notification',
                'message' => 'Test message',
            ],
            'read_at' => $readAt,
        ]);

        return $notification;
    }

    public function test_user_sees_only_own_notifications(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.view']);
        $other = User::factory()->create();

        $ownNotification = $this->createNotificationForUser($owner);
        $this->createNotificationForUser($other);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $rows = $response->json('data') ?? [];
        $this->assertCount(1, $rows);
        $this->assertSame($ownNotification->id, data_get($rows, '0.id'));
    }

    public function test_unread_count_returns_correct_value_for_owner_only(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.view']);
        $other = User::factory()->create();

        $this->createNotificationForUser($owner, null);
        $this->createNotificationForUser($owner, null);
        $this->createNotificationForUser($owner, now()->toISOString());
        $this->createNotificationForUser($other, null);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_mark_one_as_read_updates_read_at_for_own_notification(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.view']);
        $notification = $this->createNotificationForUser($owner, null);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_mark_all_as_read_updates_all_own_unread_notifications(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.view']);
        $other = User::factory()->create();

        $ownUnreadA = $this->createNotificationForUser($owner, null);
        $ownUnreadB = $this->createNotificationForUser($owner, null);
        $this->createNotificationForUser($other, null);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated', 2);

        $this->assertNotNull($ownUnreadA->fresh()?->read_at);
        $this->assertNotNull($ownUnreadB->fresh()?->read_at);
    }

    public function test_user_cannot_read_or_delete_foreign_notification(): void
    {
        $this->actingAsWithPermissions(['notifications.view', 'notifications.delete']);

        $other = User::factory()->create();
        $foreignNotification = $this->createNotificationForUser($other, null);

        $this->patchJson("/api/v1/notifications/{$foreignNotification->id}/read")
            ->assertNotFound();

        $this->deleteJson("/api/v1/notifications/{$foreignNotification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $foreignNotification->id,
        ]);
    }

    public function test_delete_removes_own_notification_when_permission_granted(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.delete']);
        $notification = $this->createNotificationForUser($owner, null);

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }
}

