<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_authorize_private_system_notifications_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-system.notifications',
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_authenticated_user_without_notifications_view_permission_cannot_authorize_private_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-system.notifications',
        ])->assertForbidden();
    }

    public function test_authenticated_user_with_notifications_view_permission_can_authorize_private_channel(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'notifications.view']);
        $user->permissions()->sync([$permission->id]);

        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-system.notifications',
        ])->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_cannot_authorize_private_user_notifications_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.user.1',
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_user_cannot_authorize_another_users_notification_channel(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.user.'.($user->id + 1),
        ])->assertForbidden();
    }

    public function test_owner_can_authorize_own_notification_channel(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.user.'.$user->id,
        ])->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_cannot_authorize_private_activity_stream_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-activity.stream',
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_authenticated_user_without_activity_view_permission_cannot_authorize_activity_stream_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-activity.stream',
        ])->assertForbidden();
    }

    public function test_authenticated_user_with_activity_view_permission_can_authorize_activity_stream_channel(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'activity.view']);
        $user->permissions()->sync([$permission->id]);

        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-activity.stream',
        ])->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_cannot_authorize_presence_online_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-online',
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_authenticated_user_can_authorize_presence_online_with_safe_payload(): void
    {
        $user = User::factory()->create([
            'name' => 'Presence User',
            'email' => 'presence@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-online',
        ])->assertOk();

        $payload = $response->json();
        $this->assertArrayHasKey('channel_data', $payload);

        $channelData = json_decode((string) data_get($payload, 'channel_data'), true);
        $this->assertIsArray($channelData);
        $this->assertSame($user->id, data_get($channelData, 'user_info.id'));
        $this->assertSame('Presence User', data_get($channelData, 'user_info.name'));
        $this->assertNull(data_get($channelData, 'user_info.email'));
        $this->assertNull(data_get($channelData, 'user_info.roles'));
        $this->assertNull(data_get($channelData, 'user_info.permissions'));
    }

    public function test_guest_cannot_authorize_presence_page_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-page.dashboard',
        ]);

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_authenticated_user_can_authorize_presence_page_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-page.dashboard',
        ])->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_authenticated_user_can_authorize_presence_dashboard_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-dashboard',
        ])->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_authenticated_user_can_authorize_presence_typing_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-typing.chat-room-1',
        ])->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_invalid_presence_page_or_context_is_denied(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-page.bad/segment',
        ])->assertForbidden();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-presence-typing.bad/segment',
        ])->assertForbidden();
    }
}
