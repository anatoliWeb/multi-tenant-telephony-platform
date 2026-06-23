<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatUserJoinedConversation;
use App\Events\Chat\ChatUserLeftConversation;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatPresenceChannelTest extends TestCase
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
            'title' => 'Presence Chat',
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

    public function test_chat_presence_channel_authorization_and_safe_payload(): void
    {
        Event::fake([ChatUserJoinedConversation::class, ChatUserLeftConversation::class]);

        $owner = User::factory()->create(['name' => 'Owner User', 'email' => 'owner@example.com']);
        $conversation = $this->makeConversation($owner);

        $active = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $active, ['access_state' => 'full']);
        ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $active->id,
            'device_key' => 'presence-device-1',
            'device_type' => 'browser',
            'is_active' => true,
            'last_seen_at' => now()->subHour(),
        ]);

        $activeResponse = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'presence-chat.'.$conversation->id,
            'device_key' => 'presence-device-1',
        ])->assertOk();

        $activePayload = json_decode((string) data_get($activeResponse->json(), 'channel_data'), true);
        $this->assertSame($active->id, data_get($activePayload, 'user_info.id'));
        $this->assertSame($active->name, data_get($activePayload, 'user_info.name'));
        $this->assertSame('member', data_get($activePayload, 'user_info.role'));
        $this->assertSame('browser', data_get($activePayload, 'user_info.device_type'));
        $this->assertNull(data_get($activePayload, 'user_info.email'));
        $this->assertNull(data_get($activePayload, 'user_info.user_agent'));
        $this->assertNull(data_get($activePayload, 'user_info.ip_address'));
        $this->assertNull(data_get($activePayload, 'user_info.metadata'));
        $this->assertNull(data_get($activePayload, 'user_info.permissions'));
        $this->assertNull(data_get($activePayload, 'user_info.blocked_reason'));

        Event::assertDispatched(ChatUserJoinedConversation::class, function (ChatUserJoinedConversation $event) use ($conversation, $active): bool {
            return $event->conversationId === $conversation->id
                && data_get($event->payload, 'conversation_id') === $conversation->id
                && data_get($event->payload, 'user_id') === $active->id
                && ! array_key_exists('email', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });

        $readOnly = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $readOnly, ['access_state' => 'read_only']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertOk();

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hidden, ['access_state' => 'hidden']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        $blockedNotice = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blockedNotice, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.4',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.5',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertForbidden();

        $admin = $this->actingAsWithPermissions(['chat.admin.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.6',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertOk();

        $deletedConversation = $this->makeConversation($owner);
        $deletedConversation->delete();
        Sanctum::actingAs($admin);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.7',
            'channel_name' => 'presence-chat.'.$deletedConversation->id,
        ])->assertForbidden();

        Sanctum::actingAs($active);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")
            ->assertOk();

        Event::assertDispatched(ChatUserLeftConversation::class, function (ChatUserLeftConversation $event) use ($conversation, $active): bool {
            return $event->conversationId === $conversation->id
                && data_get($event->payload, 'conversation_id') === $conversation->id
                && data_get($event->payload, 'user_id') === $active->id
                && ! array_key_exists('email', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });
    }
}

