<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageDeliveryUpdated;
use App\Events\Chat\ChatMessageDeviceRead;
use App\Events\Chat\ChatMessageRead;
use App\Events\Chat\ChatMessageUpdated;
use App\Events\Chat\ChatParticipantAccessChanged;
use App\Events\Chat\ChatAttachmentCreated;
use App\Events\Chat\ChatAttachmentDeleted;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatRealtimeEventsTest extends TestCase
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

    private function makeConversation(array $overrides = []): Conversation
    {
        $owner = $overrides['owner'] ?? User::factory()->create();
        unset($overrides['owner']);

        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Realtime Chat',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'history_import_from_message_id' => null,
            'history_import_from_at' => null,
            'last_message_id' => null,
            'last_message_at' => null,
            'metadata' => null,
        ], $overrides));
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
            'blocked_by' => null,
            'blocked_at' => null,
            'blocked_reason' => null,
            'history_visible_from_message_id' => null,
            'history_visible_from_at' => null,
            'history_visible_until_message_id' => null,
            'history_visible_until_at' => null,
            'left_at' => null,
            'removed_at' => null,
            'last_read_message_id' => null,
            'last_read_at' => null,
            'muted_until' => null,
            'joined_at' => now(),
            'history_visibility_mode' => 'full',
            'metadata' => null,
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, User $sender, array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'hello',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => null,
        ], $overrides));
    }

    public function test_chat_realtime_events_and_channel_authorization(): void
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
        $receiver = User::factory()->create();
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.view',
            'chat.conversations.view',
            'chat.participants.manage',
            'chat.attachments.upload',
            'chat.attachments.delete',
            'chat.attachments.download',
        ]);

        $conversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($conversation, $actor, ['role' => 'owner']);
        $this->addParticipant($conversation, $receiver);

        $messageResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Realtime message',
        ])->assertCreated();

        $messageId = (int) $messageResponse->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        Event::assertDispatched(ChatMessageCreated::class);
        Event::assertDispatched(ChatMessageDeliveryUpdated::class);

        $this->patchJson("/api/v1/chat/messages/{$messageId}", [
            'body' => 'Realtime edited',
        ])->assertOk();
        Event::assertDispatched(ChatMessageUpdated::class);

        $device = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'device_key' => 'realtime-device-1',
            'device_name' => 'Browser',
            'device_type' => 'browser',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $this->patchJson("/api/v1/chat/messages/{$messageId}/read", [
            'device_key' => $device->device_key,
        ])->assertOk();

        Event::assertDispatched(ChatMessageRead::class);
        Event::assertDispatched(ChatMessageDeviceRead::class);

        $target = User::factory()->create();
        $this->addParticipant($conversation, $target);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/access", [
            'access_state' => 'read_only',
        ])->assertOk();
        Event::assertDispatched(ChatParticipantAccessChanged::class, fn (ChatParticipantAccessChanged $event): bool => data_get($event->payload, 'changed_fields') === 'access_updated');

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/block", [
            'block_display_mode' => 'show_notice',
            'blocked_reason' => 'internal reason',
        ])->assertOk();
        Event::assertDispatched(ChatParticipantAccessChanged::class, fn (ChatParticipantAccessChanged $event): bool => data_get($event->payload, 'changed_fields') === 'blocked');

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/unblock")
            ->assertOk();
        Event::assertDispatched(ChatParticipantAccessChanged::class, fn (ChatParticipantAccessChanged $event): bool => data_get($event->payload, 'changed_fields') === 'unblocked');

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/capabilities", [
            'can_invite' => true,
            'can_remove' => true,
        ])->assertOk();
        Event::assertDispatched(ChatParticipantAccessChanged::class, fn (ChatParticipantAccessChanged $event): bool => data_get($event->payload, 'changed_fields') === 'capabilities_updated');

        $uploadResponse = $this->postJson("/api/v1/chat/messages/{$messageId}/attachments", [
            'file' => UploadedFile::fake()->create('demo.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        Event::assertDispatched(ChatAttachmentCreated::class);

        $attachmentId = (int) $uploadResponse->json('data.id');
        $attachment = MessageAttachment::query()->findOrFail($attachmentId);

        $this->deleteJson("/api/v1/chat/attachments/{$attachment->id}")
            ->assertOk();
        Event::assertDispatched(ChatAttachmentDeleted::class);

        $this->deleteJson("/api/v1/chat/messages/{$messageId}")
            ->assertOk();
        Event::assertDispatched(ChatMessageDeleted::class);

        Event::assertDispatched(ChatMessageCreated::class, function (ChatMessageCreated $event): bool {
            return ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('disk', $event->payload)
                && ! array_key_exists('path', $event->payload)
                && ! array_key_exists('checksum', $event->payload)
                && ! array_key_exists('user_agent', $event->payload);
        });

        Event::assertDispatched(ChatMessageDeviceRead::class, function (ChatMessageDeviceRead $event): bool {
            return ! array_key_exists('device_key', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });

        Event::assertDispatched(ChatMessageDeliveryUpdated::class, function (ChatMessageDeliveryUpdated $event): bool {
            return ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('webhook_secret', $event->payload)
                && ! array_key_exists('external_payload', $event->payload);
        });

        Event::assertDispatched(ChatParticipantAccessChanged::class, function (ChatParticipantAccessChanged $event): bool {
            return ! array_key_exists('blocked_reason', $event->payload)
                && ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('old_values', $event->payload)
                && ! array_key_exists('new_values', $event->payload);
        });

        Event::assertDispatched(ChatAttachmentCreated::class, function (ChatAttachmentCreated $event): bool {
            return ! array_key_exists('disk', $event->payload)
                && ! array_key_exists('path', $event->payload)
                && ! array_key_exists('checksum', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });

        Event::assertDispatched(ChatAttachmentDeleted::class, function (ChatAttachmentDeleted $event): bool {
            return ! array_key_exists('disk', $event->payload)
                && ! array_key_exists('path', $event->payload)
                && ! array_key_exists('checksum', $event->payload)
                && ! array_key_exists('metadata', $event->payload);
        });

        $this->assertSame('realtime', (new ChatMessageCreated(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatMessageUpdated(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatMessageDeleted(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatMessageRead(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatMessageDeviceRead(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatMessageDeliveryUpdated(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatParticipantAccessChanged(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatAttachmentCreated(1, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatAttachmentDeleted(1, []))->broadcastQueue);

        Sanctum::actingAs($actor);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertOk()->assertJsonStructure(['auth']);

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        Sanctum::actingAs($outsider);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hidden, ['access_state' => 'hidden']);
        Sanctum::actingAs($hidden);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $blockedNotice = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blockedNotice, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        Sanctum::actingAs($blockedNotice);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.4',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertForbidden();

        $this->assertSame($conversation->id, $message->conversation_id);
    }
}
