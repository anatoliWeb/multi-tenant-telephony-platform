<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatUserJoinedConversation;
use App\Events\Chat\ChatUserLeftConversation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatPresenceSafePayloadTest extends TestCase
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

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Presence Safe Chat',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): ConversationParticipant
    {
        return ConversationParticipant::query()->create(array_merge([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => false,
            'can_remove' => false,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ], $overrides));
    }

    public function test_presence_channels_return_only_safe_payload_fields_and_guest_is_denied(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'presence-online',
        ])->assertUnauthorized();

        $user = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'notifications.view',
            'activity.view',
        ]);

        $online = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'presence-presence-online',
        ])->assertOk();
        $dashboard = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'presence-presence-dashboard',
        ])->assertOk();

        $onlinePayload = json_decode((string) data_get($online->json(), 'channel_data'), true);
        $dashboardPayload = json_decode((string) data_get($dashboard->json(), 'channel_data'), true);

        $this->assertSame($user->id, data_get($onlinePayload, 'user_info.id'));
        $this->assertSame($user->name, data_get($onlinePayload, 'user_info.name'));
        $this->assertSame($user->id, data_get($dashboardPayload, 'user_info.id'));
        $this->assertSame($user->name, data_get($dashboardPayload, 'user_info.name'));

        foreach ([$onlinePayload, $dashboardPayload] as $payload) {
            $this->assertNull(data_get($payload, 'user_info.email'));
            $this->assertNull(data_get($payload, 'user_info.phone'));
            $this->assertNull(data_get($payload, 'user_info.device_key'));
            $this->assertNull(data_get($payload, 'user_info.user_agent'));
            $this->assertNull(data_get($payload, 'user_info.ip_address'));
            $this->assertNull(data_get($payload, 'user_info.permissions'));
            $this->assertNull(data_get($payload, 'user_info.blocked_reason'));
            $this->assertNull(data_get($payload, 'user_info.metadata'));
            $this->assertNull(data_get($payload, 'user_info.token'));
            $this->assertNull(data_get($payload, 'user_info.secret'));
        }
    }

    public function test_presence_chat_and_legacy_alias_are_safe_and_apply_access_rules(): void
    {
        Event::fake([ChatUserJoinedConversation::class, ChatUserLeftConversation::class]);

        $owner = User::factory()->create(['name' => 'Owner']);
        $conversation = $this->makeConversation($owner);

        $active = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $active, ['access_state' => 'full']);

        $presence = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.1',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertOk();
        $legacy = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.2',
            'channel_name' => 'chat.'.$conversation->id,
        ])->assertOk();

        foreach ([$presence, $legacy] as $response) {
            $payload = json_decode((string) data_get($response->json(), 'channel_data'), true);
            $this->assertSame($active->id, data_get($payload, 'user_info.id'));
            $this->assertSame($active->name, data_get($payload, 'user_info.name'));
            $this->assertNull(data_get($payload, 'user_info.email'));
            $this->assertNull(data_get($payload, 'user_info.device_key'));
            $this->assertNull(data_get($payload, 'user_info.user_agent'));
            $this->assertNull(data_get($payload, 'user_info.ip_address'));
            $this->assertNull(data_get($payload, 'user_info.permissions'));
            $this->assertNull(data_get($payload, 'user_info.blocked_reason'));
            $this->assertNull(data_get($payload, 'user_info.metadata'));
        }

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hidden, ['access_state' => 'hidden']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.3',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        $blocked = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blocked, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.4',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.5',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        Sanctum::actingAs($active);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertOk();
        Event::assertDispatched(ChatUserLeftConversation::class, function (ChatUserLeftConversation $event): bool {
            return ! array_key_exists('email', $event->payload)
                && ! array_key_exists('device_key', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('permissions', $event->payload)
                && ! array_key_exists('blocked_reason', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });
    }
}
