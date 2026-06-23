<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityRealtimeChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_private_chat_channel_authorization_rules_are_enforced(): void
    {
        $guest = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-chat.conversation.1',
        ]);
        $this->assertContains($guest->status(), [401, 403]);
        $this->assertStringNotContainsString('trace', mb_strtolower((string) $guest->getContent()));

        $owner = User::factory()->create();
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Realtime Authorization',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
        ]);

        $participant = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $participant->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertOk();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $hidden->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'hidden',
            'can_send' => false,
            'can_attach' => false,
            'joined_at' => now(),
        ]);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.4',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();
    }

    public function test_presence_and_global_channels_are_not_public_and_keep_safe_payloads(): void
    {
        $presenceGuest = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.1',
            'channel_name' => 'presence-presence-online',
        ]);
        $this->assertContains($presenceGuest->status(), [401, 403]);

        $user = $this->actingAsWithPermissions(['notifications.view']);
        $presence = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.2',
            'channel_name' => 'presence-presence-online',
        ])->assertOk();
        $presencePayload = json_decode((string) data_get($presence->json(), 'channel_data'), true);
        $this->assertIsArray($presencePayload);
        $this->assertArrayNotHasKey('email', (array) data_get($presencePayload, 'user_info', []));
        $this->assertArrayNotHasKey('permissions', (array) data_get($presencePayload, 'user_info', []));
        $this->assertArrayNotHasKey('device_key', (array) data_get($presencePayload, 'user_info', []));
        $this->assertArrayNotHasKey('ip_address', (array) data_get($presencePayload, 'user_info', []));
        $this->assertArrayNotHasKey('user_agent', (array) data_get($presencePayload, 'user_info', []));

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.3',
            'channel_name' => 'private-system.notifications',
        ])->assertOk();

        $withoutNotificationsPermission = $this->actingAsWithPermissions([]);
        $denied = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.4',
            'channel_name' => 'private-system.notifications',
        ])->assertForbidden();

        $deniedPayload = mb_strtolower((string) $denied->getContent());
        $this->assertStringNotContainsString('token', $deniedPayload);
        $this->assertStringNotContainsString('secret', $deniedPayload);
        $this->assertStringNotContainsString('bearer ', $deniedPayload);

        Sanctum::actingAs($withoutNotificationsPermission);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.5',
            'channel_name' => 'presence-presence-dashboard',
        ])->assertOk();
    }
}
