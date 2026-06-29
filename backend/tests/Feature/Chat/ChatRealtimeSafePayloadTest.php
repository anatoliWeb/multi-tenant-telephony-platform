<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatAttachmentCreated;
use App\Events\Chat\ChatAttachmentDeleted;
use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageDeliveryUpdated;
use App\Events\Chat\ChatMessageDeviceRead;
use App\Events\Chat\ChatMessageRead;
use App\Events\Chat\ChatMessageUpdated;
use App\Events\Chat\ChatParticipantAccessChanged;
use App\Events\Chat\ChatTypingStarted;
use App\Events\Chat\ChatTypingStopped;
use App\Events\Chat\ChatUserJoinedConversation;
use App\Events\Chat\ChatUserLeftConversation;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatRealtimeSafePayloadTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Realtime Safe Chat',
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

    public function test_realtime_payloads_remain_safe_for_core_chat_events(): void
    {
        Event::fake([
            ChatMessageCreated::class,
            ChatMessageUpdated::class,
            ChatMessageDeleted::class,
            ChatMessageRead::class,
            ChatMessageDeviceRead::class,
            ChatMessageDeliveryUpdated::class,
            ChatParticipantAccessChanged::class,
            ChatAttachmentCreated::class,
            ChatAttachmentDeleted::class,
        ]);

        $owner = User::factory()->create();
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.view',
            'chat.conversations.view',
            'chat.participants.manage',
            'chat.attachments.upload',
            'chat.attachments.delete',
        ]);
        $peer = User::factory()->create();

        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $actor, ['role' => 'owner']);
        $this->addParticipant($conversation, $peer);

        $createResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Safe realtime payload message',
        ])->assertCreated();
        $messageId = (int) $createResponse->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        $this->patchJson("/api/v1/chat/messages/{$messageId}", ['body' => 'edited'])->assertOk();

        $device = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'device_key' => 'safe-payload-device',
            'device_name' => 'Browser',
            'device_type' => 'browser',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $this->patchJson("/api/v1/chat/messages/{$messageId}/read", [
            'device_key' => $device->device_key,
        ])->assertOk();

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$peer->id}/access", [
            'access_state' => 'read_only',
        ])->assertOk();

        $uploadResponse = $this->postJson("/api/v1/chat/messages/{$messageId}/attachments", [
            'file' => UploadedFile::fake()->create('safe.pdf', 128, 'application/pdf'),
        ])->assertCreated();
        $attachment = MessageAttachment::query()->findOrFail((int) $uploadResponse->json('data.id'));

        $this->deleteJson("/api/v1/chat/attachments/{$attachment->id}")->assertOk();
        $this->deleteJson("/api/v1/chat/messages/{$message->id}")->assertOk();

        Event::assertDispatched(ChatMessageCreated::class, fn (ChatMessageCreated $event): bool => ! isset($event->payload['metadata'], $event->payload['token'], $event->payload['secret'], $event->payload['signature'], $event->payload['authorization'], $event->payload['body']));
        Event::assertDispatched(ChatMessageUpdated::class, fn (ChatMessageUpdated $event): bool => ! isset($event->payload['metadata'], $event->payload['raw_payload'], $event->payload['user_agent']));
        Event::assertDispatched(ChatMessageDeleted::class, fn (ChatMessageDeleted $event): bool => ! isset($event->payload['metadata'], $event->payload['raw_response']));
        Event::assertDispatched(ChatMessageRead::class, fn (ChatMessageRead $event): bool => ! isset($event->payload['device_key'], $event->payload['user_agent'], $event->payload['ip_address']));
        Event::assertDispatched(ChatMessageDeviceRead::class, fn (ChatMessageDeviceRead $event): bool => ! isset($event->payload['device_key'], $event->payload['user_agent'], $event->payload['ip_address']));
        Event::assertDispatched(ChatMessageDeliveryUpdated::class, fn (ChatMessageDeliveryUpdated $event): bool => ! isset($event->payload['token'], $event->payload['secret'], $event->payload['signature'], $event->payload['authorization']));
        Event::assertDispatched(ChatParticipantAccessChanged::class, fn (ChatParticipantAccessChanged $event): bool => ! isset($event->payload['blocked_reason'], $event->payload['metadata']));
        Event::assertDispatched(ChatAttachmentCreated::class, fn (ChatAttachmentCreated $event): bool => ! isset($event->payload['disk'], $event->payload['path'], $event->payload['checksum'], $event->payload['storage_path']));
        Event::assertDispatched(ChatAttachmentDeleted::class, fn (ChatAttachmentDeleted $event): bool => ! isset($event->payload['disk'], $event->payload['path'], $event->payload['checksum'], $event->payload['storage_path']));
    }

    public function test_typing_and_presence_events_have_safe_payloads(): void
    {
        Event::fake([ChatTypingStarted::class, ChatTypingStopped::class, ChatUserJoinedConversation::class, ChatUserLeftConversation::class]);

        $owner = User::factory()->create();
        $actor = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $owner, ['role' => 'owner']);
        $this->addParticipant($conversation, $actor);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [
            'device_type' => 'browser',
            'device_key' => 'typing-safe-key',
        ])->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/stop", [
            'device_type' => 'browser',
        ])->assertOk();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'presence-chat.'.$conversation->id,
        ])->assertOk();

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertOk();

        Event::assertDispatched(ChatTypingStarted::class, fn (ChatTypingStarted $event): bool => ! isset($event->payload['device_key'], $event->payload['user_agent'], $event->payload['ip_address'], $event->payload['metadata']));
        Event::assertDispatched(ChatTypingStopped::class, fn (ChatTypingStopped $event): bool => ! isset($event->payload['device_key'], $event->payload['user_agent'], $event->payload['ip_address'], $event->payload['metadata']));
        Event::assertDispatched(ChatUserJoinedConversation::class, fn (ChatUserJoinedConversation $event): bool => ! isset($event->payload['email'], $event->payload['user_agent'], $event->payload['ip_address'], $event->payload['metadata']));
        Event::assertDispatched(ChatUserLeftConversation::class, fn (ChatUserLeftConversation $event): bool => ! isset($event->payload['email'], $event->payload['user_agent'], $event->payload['ip_address'], $event->payload['metadata']));
    }

    public function test_all_chat_realtime_events_use_realtime_queue_and_private_conversation_channel(): void
    {
        $conversationId = 123;
        $events = [
            new ChatMessageCreated($conversationId, []),
            new ChatMessageUpdated($conversationId, []),
            new ChatMessageDeleted($conversationId, []),
            new ChatMessageRead($conversationId, []),
            new ChatMessageDeviceRead($conversationId, []),
            new ChatMessageDeliveryUpdated($conversationId, []),
            new ChatParticipantAccessChanged($conversationId, []),
            new ChatAttachmentCreated($conversationId, []),
            new ChatAttachmentDeleted($conversationId, []),
            new ChatTypingStarted($conversationId, []),
            new ChatTypingStopped($conversationId, []),
            new ChatUserJoinedConversation($conversationId, []),
            new ChatUserLeftConversation($conversationId, []),
        ];

        foreach ($events as $event) {
            $this->assertSame('realtime', $event->broadcastQueue);
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame("private-chat.conversation.{$conversationId}", $channels[0]->name);
        }
    }
}

