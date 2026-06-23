<?php

namespace Tests\Feature\Chat;

use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDeviceRead;
use App\Models\MessageRead;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatDeviceReadStateApiTest extends TestCase
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
            'title' => 'Device read chat',
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
            'metadata' => [],
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
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'blocked_by' => null,
            'blocked_at' => null,
            'blocked_reason' => null,
            'history_visibility_mode' => 'full',
            'history_visible_from_message_id' => null,
            'history_visible_from_at' => null,
            'history_visible_until_message_id' => null,
            'history_visible_until_at' => null,
            'joined_at' => now(),
            'left_at' => null,
            'removed_at' => null,
            'last_read_message_id' => null,
            'last_read_at' => null,
            'muted_until' => null,
            'metadata' => [],
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, ?User $sender = null, array $overrides = []): Message
    {
        $payload = array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender?->id,
            'sender_type' => $sender ? 'user' : 'system',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'Device read message',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => [],
            'created_at' => null,
            'updated_at' => null,
        ], $overrides);

        $createdAt = $payload['created_at'] ?? null;
        $updatedAt = $payload['updated_at'] ?? null;
        unset($payload['created_at'], $payload['updated_at']);

        $message = Message::query()->create($payload);
        if ($createdAt !== null || $updatedAt !== null) {
            $message->timestamps = false;
            $message->forceFill([
                'created_at' => $createdAt ?? $message->created_at,
                'updated_at' => $updatedAt ?? $message->updated_at,
            ])->save();
            $message->timestamps = true;
        }

        return $message->fresh();
    }

    public function test_chat_device_read_state_api_foundation(): void
    {
        $this->postJson('/api/v1/chat/devices', [
            'device_key' => 'browser-1',
        ])->assertUnauthorized();

        $user = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);

        $this->postJson('/api/v1/chat/devices', [
            'device_key' => str_repeat('x', 129),
        ])->assertStatus(422);

        $registerResponse = $this->postJson('/api/v1/chat/devices', [
            'device_key' => 'browser-1',
            'device_name' => 'Chrome',
            'device_type' => 'browser',
            'platform' => 'Windows',
            'browser' => 'Chrome',
            'app_version' => '1.0.0',
        ])->assertOk();

        $deviceId = $registerResponse->json('data.id');
        $this->assertDatabaseHas('chat_user_devices', [
            'id' => $deviceId,
            'user_id' => $user->id,
            'device_key' => 'browser-1',
            'device_name' => 'Chrome',
        ]);

        $this->postJson('/api/v1/chat/devices', [
            'device_key' => 'browser-1',
            'device_name' => 'Edge',
        ])->assertOk();
        $this->assertSame(1, ChatUserDevice::query()->where('user_id', $user->id)->where('device_key', 'browser-1')->count());
        $this->assertDatabaseHas('chat_user_devices', [
            'user_id' => $user->id,
            'device_key' => 'browser-1',
            'device_name' => 'Edge',
        ]);

        $other = User::factory()->create();
        ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $other->id,
            'device_key' => 'browser-1',
            'device_name' => 'Other device',
            'device_type' => 'browser',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        $this->assertSame(2, ChatUserDevice::query()->where('device_key', 'browser-1')->count());

        $owner = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $participant = $this->addParticipant($conversation, $user, ['last_read_message_id' => null, 'last_read_at' => null]);
        $this->addParticipant($conversation, $owner);
        $visibleMessage = $this->makeMessage($conversation, $owner);

        $this->patchJson("/api/v1/chat/messages/{$visibleMessage->id}/read", [
            'device_key' => 'browser-1',
        ])->assertOk();

        $this->assertDatabaseHas('message_device_reads', [
            'message_id' => $visibleMessage->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'chat_user_device_id' => $deviceId,
            'device_key' => 'browser-1',
        ]);
        $this->assertDatabaseHas('message_reads', [
            'message_id' => $visibleMessage->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'read_source' => 'device',
        ]);

        $participant->refresh();
        $this->assertSame($visibleMessage->id, $participant->last_read_message_id);
        $this->assertNotNull($participant->last_read_at);

        $readOnlyConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($readOnlyConversation, $user, ['access_state' => 'read_only']);
        $this->addParticipant($readOnlyConversation, $owner);
        $readOnlyMessage = $this->makeMessage($readOnlyConversation, $owner);

        $this->patchJson("/api/v1/chat/messages/{$readOnlyMessage->id}/read", [
            'device_key' => 'browser-1',
        ])->assertOk();

        $hiddenConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($hiddenConversation, $user, ['access_state' => 'hidden']);
        $this->addParticipant($hiddenConversation, $owner);
        $hiddenMessage = $this->makeMessage($hiddenConversation, $owner);

        $this->patchJson("/api/v1/chat/messages/{$hiddenMessage->id}/read", [
            'device_key' => 'browser-1',
        ])->assertNotFound();

        $blockedNoticeConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($blockedNoticeConversation, $user, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->addParticipant($blockedNoticeConversation, $owner);
        $blockedNoticeMessage = $this->makeMessage($blockedNoticeConversation, $owner);

        $this->patchJson("/api/v1/chat/messages/{$blockedNoticeMessage->id}/read", [
            'device_key' => 'browser-1',
        ])->assertNotFound();

        $foreignConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($foreignConversation, $owner);
        $foreignMessage = $this->makeMessage($foreignConversation, $owner);

        $this->patchJson("/api/v1/chat/messages/{$foreignMessage->id}/read", [
            'device_key' => 'browser-1',
        ])->assertNotFound();

        $directOwner = User::factory()->create();
        $directSecond = User::factory()->create();
        $third = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson('/api/v1/chat/devices', ['device_key' => 'browser-3'])->assertOk();

        $directConversation = $this->makeConversation([
            'type' => 'direct',
            'visibility' => 'private',
            'owner' => $directOwner,
        ]);
        $this->addParticipant($directConversation, $directOwner, ['role' => 'owner']);
        $this->addParticipant($directConversation, $directSecond, ['role' => 'member']);
        $sourceDirectMessage = $this->makeMessage($directConversation, $directOwner);

        $groupConversation = $this->makeConversation([
            'type' => 'group',
            'visibility' => 'private',
            'owner' => $directOwner,
            'created_from_conversation_id' => $directConversation->id,
            'history_import_mode' => 'full',
        ]);
        $this->addParticipant($groupConversation, $directOwner);
        $this->addParticipant($groupConversation, $directSecond);
        $this->addParticipant($groupConversation, $third);
        $importedMessage = $this->makeMessage($groupConversation, $directOwner, [
            'is_imported' => true,
            'imported_from_conversation_id' => $directConversation->id,
            'imported_from_message_id' => $sourceDirectMessage->id,
        ]);

        $this->patchJson("/api/v1/chat/messages/{$sourceDirectMessage->id}/read", [
            'device_key' => 'browser-3',
        ])->assertNotFound();

        $this->patchJson("/api/v1/chat/messages/{$importedMessage->id}/read", [
            'device_key' => 'browser-3',
        ])->assertOk();

        Sanctum::actingAs($user);

        $historyConversation = $this->makeConversation(['owner' => $owner]);
        $historyParticipant = $this->addParticipant($historyConversation, $user, [
            'history_visible_from_at' => now()->subMinutes(20),
            'history_visible_until_at' => now()->subMinutes(5),
        ]);
        $this->addParticipant($historyConversation, $owner);

        $oldMessage = $this->makeMessage($historyConversation, $owner, [
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ]);
        $visibleWithinBounds = $this->makeMessage($historyConversation, $owner, [
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $recentMessage = $this->makeMessage($historyConversation, $owner, [
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $this->patchJson("/api/v1/chat/conversations/{$historyConversation->id}/read", [
            'device_key' => 'browser-1',
        ])->assertOk();

        $this->assertDatabaseMissing('message_reads', [
            'message_id' => $oldMessage->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('message_reads', [
            'message_id' => $visibleWithinBounds->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('message_reads', [
            'message_id' => $recentMessage->id,
            'user_id' => $user->id,
        ]);

        $historyParticipant->refresh();
        $this->assertSame($visibleWithinBounds->id, $historyParticipant->last_read_message_id);

        $this->patchJson("/api/v1/chat/messages/{$visibleMessage->id}/read", [
            'device_key' => 'unknown-device',
        ])->assertStatus(422);

        $deviceRead = MessageDeviceRead::query()
            ->where('message_id', $visibleMessage->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($deviceRead);
        $this->assertSame('device_api', data_get($deviceRead?->metadata, 'source'));

        $aggregateRead = MessageRead::query()
            ->where('message_id', $visibleMessage->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($aggregateRead);
    }
}
