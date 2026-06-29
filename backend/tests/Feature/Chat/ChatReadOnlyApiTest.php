<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatReadOnlyApiTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        return $this->makeTenantChatUserWithPermissions($permissions);
    }

    private function makeConversation(array $overrides = []): Conversation
    {
        $owner = $overrides['owner'] ?? User::factory()->create();
        unset($overrides['owner']);

        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'API Chat',
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
            'metadata' => [
                'unsafe' => 'must_not_leak',
            ],
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
            'blocked_reason' => 'internal-only',
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
            'metadata' => ['unsafe' => 'must_not_leak'],
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
            'body' => 'Body',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => ['unsafe' => 'must_not_leak'],
            'created_at' => now(),
            'updated_at' => now(),
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

    public function test_chat_read_only_api_foundation(): void
    {
        $this->getJson('/api/v1/chat/conversations')->assertUnauthorized();

        $user = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $other = User::factory()->create();

        $visibleConversation = $this->makeConversation(['owner' => $user, 'type' => 'group', 'source' => 'internal']);
        $this->addParticipant($visibleConversation, $user);
        $this->addParticipant($visibleConversation, $other);

        $hiddenConversation = $this->makeConversation(['owner' => $other]);
        $this->addParticipant($hiddenConversation, $user, ['access_state' => 'hidden']);

        $noticeConversation = $this->makeConversation(['owner' => $other]);
        $this->addParticipant($noticeConversation, $user, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);

        $hideChatConversation = $this->makeConversation(['owner' => $other]);
        $this->addParticipant($hideChatConversation, $user, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'hide_chat',
        ]);

        $readOnlyConversation = $this->makeConversation(['owner' => $other]);
        $this->addParticipant($readOnlyConversation, $user, ['access_state' => 'read_only']);
        $this->addParticipant($readOnlyConversation, $other);

        $readOnlyMessage = $this->makeMessage($readOnlyConversation, $other, ['body' => 'readonly-visible']);

        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.0.admin_metadata.webhook_secret');

        $conversationIds = collect($this->getJson('/api/v1/chat/conversations')->json('data'))->pluck('id')->all();
        $this->assertContains($visibleConversation->id, $conversationIds);
        $this->assertContains($noticeConversation->id, $conversationIds);
        $this->assertContains($readOnlyConversation->id, $conversationIds);
        $this->assertNotContains($hiddenConversation->id, $conversationIds);
        $this->assertNotContains($hideChatConversation->id, $conversationIds);

        $this->getJson("/api/v1/chat/conversations/{$hiddenConversation->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/chat/conversations/{$noticeConversation->id}")
            ->assertOk()
            ->assertJsonPath('data.current_user_access.access_state', 'blocked');

        $this->getJson("/api/v1/chat/conversations/{$noticeConversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/chat/conversations/{$readOnlyConversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.id', $readOnlyMessage->id);

        $historyConversation = $this->makeConversation(['owner' => $other]);
        $participant = $this->addParticipant($historyConversation, $user);
        $this->addParticipant($historyConversation, $other);
        $m1 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);
        $m2 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)]);
        $m3 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)]);

        $participant->update(['history_visible_from_at' => now()->subMinutes(25)]);
        $idsFrom = collect($this->getJson("/api/v1/chat/conversations/{$historyConversation->id}/messages")->json('data'))->pluck('id')->all();
        $this->assertNotContains($m1->id, $idsFrom);

        $participant->update(['history_visible_from_at' => null, 'history_visible_until_at' => now()->subMinutes(15)]);
        $idsUntil = collect($this->getJson("/api/v1/chat/conversations/{$historyConversation->id}/messages")->json('data'))->pluck('id')->all();
        $this->assertNotContains($m3->id, $idsUntil);

        $sourceOwner = User::factory()->create();
        $sourceSecond = User::factory()->create();
        $third = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view']);

        $direct = $this->makeConversation(['type' => 'direct', 'visibility' => 'private', 'owner' => $sourceOwner]);
        $this->addParticipant($direct, $sourceOwner, ['role' => 'owner']);
        $this->addParticipant($direct, $sourceSecond, ['role' => 'member']);
        $sourceMessage = $this->makeMessage($direct, $sourceOwner);

        $group = $this->makeConversation([
            'type' => 'group',
            'visibility' => 'private',
            'owner' => $sourceOwner,
            'created_from_conversation_id' => $direct->id,
            'history_import_mode' => 'full',
        ]);
        $this->addParticipant($group, $sourceOwner);
        $this->addParticipant($group, $sourceSecond);
        $this->addParticipant($group, $third);
        $imported = $this->makeMessage($group, $sourceOwner, [
            'is_imported' => true,
            'imported_from_conversation_id' => $direct->id,
            'imported_from_message_id' => $sourceMessage->id,
        ]);

        Sanctum::actingAs($third);
        $this->getJson("/api/v1/chat/conversations/{$direct->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/chat/conversations/{$group->id}/messages")
            ->assertOk()
            ->assertJsonFragment(['id' => $imported->id]);

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/chat/conversations/{$visibleConversation->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.admin_metadata');

        $admin = User::factory()->create();
        $this->prepareTenantChatUser($admin, [
            'chat.view',
            'chat.conversations.view',
            'chat.admin.view',
            'chat.admin.view_metadata',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/chat/conversations/{$visibleConversation->id}")
            ->assertOk()
            ->assertJsonPath('data.admin_metadata.owner_id', $visibleConversation->owner_id);

        Sanctum::actingAs($user);
        $filtered = $this->getJson('/api/v1/chat/conversations?type=group&source=internal&unread=true')
            ->assertOk()
            ->json('data');
        $this->assertIsArray($filtered);

        $deletedMessage = $this->makeMessage($visibleConversation, $other, [
            'status' => 'deleted',
            'deleted_at' => now(),
            'body' => 'deleted',
        ]);
        $safeMessage = $this->makeMessage($visibleConversation, $other, ['body' => 'safe']);
        $visibleConversation->update([
            'last_message_id' => $safeMessage->id,
            'last_message_at' => $safeMessage->created_at,
        ]);

        $messagesResponse = $this->getJson("/api/v1/chat/conversations/{$visibleConversation->id}/messages")
            ->assertOk();
        $ids = collect($messagesResponse->json('data'))->pluck('id')->all();
        $this->assertContains($safeMessage->id, $ids);
        $this->assertNotContains($deletedMessage->id, $ids);
        $messagesResponse->assertJsonMissing([
            'unsafe' => 'must_not_leak',
        ]);
    }
}

