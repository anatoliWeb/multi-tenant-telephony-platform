<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
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

    public function test_guest_cannot_access_notification_preferences(): void
    {
        $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => ['system.enabled' => false],
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_gets_default_preferences(): void
    {
        $this->actingAsWithPermissions(['notifications.view']);

        $response = $this->getJson('/api/v1/notifications/preferences');
        $response->assertOk();
        $preferences = $response->json('data.preferences');

        $this->assertIsArray($preferences);
        $this->assertTrue((bool) ($preferences['system.enabled'] ?? false));
        $this->assertTrue((bool) ($preferences['realtime.enabled'] ?? false));
        $this->assertTrue((bool) ($preferences['email.enabled'] ?? false));
        $this->assertTrue((bool) ($preferences['activity.enabled'] ?? false));
    }

    public function test_user_can_update_own_preferences(): void
    {
        $this->actingAsWithPermissions(['notifications.view']);

        $response = $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'system.enabled' => false,
                'realtime.enabled' => true,
                'email.enabled' => false,
                'activity.enabled' => true,
            ],
        ]);

        $response->assertOk();
        $preferences = $response->json('data.preferences');
        $this->assertIsArray($preferences);
        $this->assertFalse((bool) ($preferences['system.enabled'] ?? true));
        $this->assertFalse((bool) ($preferences['email.enabled'] ?? true));
    }

    public function test_unknown_preference_keys_are_rejected(): void
    {
        $this->actingAsWithPermissions(['notifications.view']);

        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'unknown.enabled' => true,
            ],
        ])->assertStatus(422);
    }

    public function test_preferences_are_user_scoped(): void
    {
        $owner = $this->actingAsWithPermissions(['notifications.view']);
        $this->patchJson('/api/v1/notifications/preferences', [
            'preferences' => [
                'system.enabled' => false,
            ],
        ])->assertOk();

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $otherPermission = Permission::firstOrCreate(['name' => 'notifications.view']);
        $other->permissions()->sync([$otherPermission->id]);

        $otherResponse = $this->getJson('/api/v1/notifications/preferences');
        $otherResponse->assertOk();
        $otherPreferences = $otherResponse->json('data.preferences');
        $this->assertTrue((bool) ($otherPreferences['system.enabled'] ?? false));

        Sanctum::actingAs($owner);
        $ownerResponse = $this->getJson('/api/v1/notifications/preferences');
        $ownerResponse->assertOk();
        $ownerPreferences = $ownerResponse->json('data.preferences');
        $this->assertFalse((bool) ($ownerPreferences['system.enabled'] ?? true));
    }

    public function test_disabled_system_preference_prevents_notification_creation(): void
    {
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
        $result = $service->createForUser($user, 'Hidden title', 'Hidden message');

        $this->assertSame([], $result);
        $this->assertSame(0, DatabaseNotification::query()->count());
    }
}
