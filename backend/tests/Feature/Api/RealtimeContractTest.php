<?php

namespace Tests\Feature\Api;

use App\Events\Chat\ChatAttachmentCreated;
use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageDeliveryUpdated;
use App\Events\Chat\ChatMessageRead;
use App\Events\Chat\ChatParticipantAccessChanged;
use App\Events\Chat\ChatTypingStarted;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeContractTest extends TestCase
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
            'title' => 'Realtime Contract',
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
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ], $overrides));
    }

    public function test_chat_private_and_presence_channel_contracts_allow_and_deny_expected_users(): void
    {
        $guestResponse = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-chat.conversation.1',
        ]);
        $this->assertContains($guestResponse->status(), [401, 403]);
        $this->assertSafeBody((string) $guestResponse->getContent());

        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $participant = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $participant);

        $presenceAllowed = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertOk();
        $this->assertPresencePayloadIsSafe($presenceAllowed->json('channel_data'), $participant);

        $legacyAllowed = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'chat.'.$conversation->id,
        ])->assertOk();
        $this->assertPresencePayloadIsSafe($legacyAllowed->json('channel_data'), $participant);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.4',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertOk();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.5',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hidden, ['access_state' => 'hidden']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.6',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $blocked = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blocked, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.7',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();
    }

    public function test_private_system_activity_notifications_and_global_presence_contracts(): void
    {
        $guestOnline = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.7',
            'channel_name' => 'presence-presence-online',
        ]);
        $this->assertContains($guestOnline->status(), [401, 403]);

        $owner = User::factory()->create();

        Sanctum::actingAs($owner);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.1',
            'channel_name' => 'private-notifications.user.'.$owner->id,
        ])->assertOk();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.2',
            'channel_name' => 'private-notifications.user.'.($owner->id + 1),
        ])->assertForbidden();

        $withoutNotificationsPermission = $this->actingAsWithPermissions([]);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.3',
            'channel_name' => 'private-system.notifications',
        ])->assertForbidden();

        $withNotificationsPermission = $this->actingAsWithPermissions(['notifications.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.4',
            'channel_name' => 'private-system.notifications',
        ])->assertOk();

        Sanctum::actingAs($withoutNotificationsPermission);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.5',
            'channel_name' => 'private-activity.stream',
        ])->assertForbidden();

        $withActivityPermission = $this->actingAsWithPermissions(['activity.view']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.6',
            'channel_name' => 'private-activity.stream',
        ])->assertOk();

        Sanctum::actingAs($withActivityPermission);
        $online = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.8',
            'channel_name' => 'presence-presence-online',
        ])->assertOk();
        $this->assertPresencePayloadIsSafe($online->json('channel_data'), $withActivityPermission);

        $dashboard = $this->postJson('/broadcasting/auth', [
            'socket_id' => '2.9',
            'channel_name' => 'presence-presence-dashboard',
        ])->assertOk();
        $this->assertPresencePayloadIsSafe($dashboard->json('channel_data'), $withActivityPermission);
    }

    public function test_chat_realtime_event_contracts_for_name_queue_channel_and_safe_payload_fields(): void
    {
        $conversationId = 42;

        $events = [
            new ChatMessageCreated($conversationId, ['message_id' => 1]),
            new ChatTypingStarted($conversationId, ['user_id' => 2]),
            new ChatMessageDeliveryUpdated($conversationId, ['message_id' => 1, 'status' => 'delivered']),
            new ChatMessageRead($conversationId, ['message_id' => 1, 'user_id' => 2]),
            new ChatParticipantAccessChanged($conversationId, ['participant_id' => 3, 'changed_fields' => 'blocked']),
            new ChatAttachmentCreated($conversationId, ['attachment_id' => 4, 'message_id' => 1]),
        ];

        foreach ($events as $event) {
            $this->assertSame('realtime', $event->broadcastQueue);

            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame("private-chat.conversation.{$conversationId}", $channels[0]->name);

            $payload = $event->broadcastWith();
            $this->assertArrayNotHasKey('token', $payload);
            $this->assertArrayNotHasKey('secret', $payload);
            $this->assertArrayNotHasKey('authorization', $payload);
            $this->assertArrayNotHasKey('signature', $payload);
            $this->assertArrayNotHasKey('device_key', $payload);
            $this->assertArrayNotHasKey('user_agent', $payload);
            $this->assertArrayNotHasKey('ip_address', $payload);
            $this->assertArrayNotHasKey('disk', $payload);
            $this->assertArrayNotHasKey('path', $payload);
            $this->assertArrayNotHasKey('checksum', $payload);
        }

        $this->assertSame('chat.message.created', $events[0]->broadcastAs());
        $this->assertSame('chat.typing.started', $events[1]->broadcastAs());
        $this->assertSame('chat.message.delivery.updated', $events[2]->broadcastAs());
        $this->assertSame('chat.message.read', $events[3]->broadcastAs());
        $this->assertSame('chat.participant.access_changed', $events[4]->broadcastAs());
        $this->assertSame('chat.attachment.created', $events[5]->broadcastAs());
    }

    public function test_denied_channel_auth_logging_is_safe_and_realtime_docs_explain_ws_ev_on_pg(): void
    {
        config([
            'logging.realtime.enabled' => true,
            'logging.realtime.channel_auth_failures' => true,
        ]);
        Log::spy();

        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '3.1',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
            'token' => 'must-not-log',
        ])->assertForbidden();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($conversation): bool {
                return $message === 'realtime.channel.auth.denied'
                    && data_get($context, 'status') === 'denied'
                    && (int) data_get($context, 'conversation_id') === $conversation->id
                    && ! array_key_exists('token', $context)
                    && ! array_key_exists('authorization', $context)
                    && ! array_key_exists('cookie', $context)
                    && ! array_key_exists('signature', $context)
                    && ! array_key_exists('device_key', $context)
                    && ! array_key_exists('user_agent', $context)
                    && ! array_key_exists('ip_address', $context);
            })
            ->once();

        $docs = file_get_contents(base_path('docs/realtime.md'));
        $this->assertNotFalse($docs);
        $this->assertStringContainsString('WS', $docs);
        $this->assertStringContainsString('EV', $docs);
        $this->assertStringContainsString('ON', $docs);
        $this->assertStringContainsString('PG', $docs);
    }

    private function assertPresencePayloadIsSafe(?string $channelData, User $expectedUser): void
    {
        $payload = json_decode((string) $channelData, true);
        $this->assertIsArray($payload);
        $this->assertSame($expectedUser->id, data_get($payload, 'user_info.id'));
        $this->assertSame($expectedUser->name, data_get($payload, 'user_info.name'));

        foreach (['email', 'token', 'device_key', 'user_agent', 'ip_address', 'permissions', 'metadata'] as $forbidden) {
            $this->assertNull(data_get($payload, "user_info.{$forbidden}"));
        }
    }

    private function assertSafeBody(string $content): void
    {
        $lower = mb_strtolower($content);
        foreach (['token', 'secret', 'authorization', 'signature', 'cookie', 'trace'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $lower);
        }
    }
}
