<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatMessageLifecycleApiTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(array $overrides = []): Conversation
    {
        $owner = $overrides['owner'] ?? User::factory()->create();
        unset($overrides['owner']);

        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Lifecycle',
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
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
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
            'body' => 'msg',
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

    public function test_chat_message_lifecycle_api_foundation(): void
    {
        $conversation = $this->makeConversation();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $this->addParticipant($conversation, $sender);
        $this->addParticipant($conversation, $receiver);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'hello',
        ])->assertUnauthorized();

        $senderWithPerm = $this->actingAsWithPermissions(['chat.send', 'chat.edit', 'chat.delete', 'chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $senderWithPerm, ['can_send' => true]);

        $sendResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'hello world',
        ])->assertCreated();

        $messageId = (int) $sendResponse->json('data.id');
        $sentMessage = Message::query()->findOrFail($messageId);
        $this->assertSame($senderWithPerm->id, $sentMessage->sender_id);
        $this->assertSame('user', $sentMessage->sender_type);
        $this->assertSame('sent', $sentMessage->status);
        $this->assertNotNull($sentMessage->sent_at);
        $this->assertSame('hello world', $sentMessage->body);

        $conversation->refresh();
        $this->assertSame($sentMessage->id, $conversation->last_message_id);
        $this->assertNotNull($conversation->last_message_at);
        $this->assertDatabaseHas('message_deliveries', [
            'message_id' => $sentMessage->id,
            'conversation_id' => $conversation->id,
            'status' => 'pending',
        ]);

        $readOnlyUser = $this->actingAsWithPermissions(['chat.send']);
        $readOnlyConversation = $this->makeConversation();
        $this->addParticipant($readOnlyConversation, $readOnlyUser, ['access_state' => 'read_only']);
        $this->postJson("/api/v1/chat/conversations/{$readOnlyConversation->id}/messages", [
            'body' => 'blocked',
        ])->assertForbidden();

        $hiddenUser = $this->actingAsWithPermissions(['chat.send']);
        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $hiddenUser, ['access_state' => 'hidden']);
        $this->postJson("/api/v1/chat/conversations/{$hiddenConversation->id}/messages", [
            'body' => 'blocked',
        ])->assertForbidden();

        $blockedUser = $this->actingAsWithPermissions(['chat.send']);
        $blockedConversation = $this->makeConversation();
        $this->addParticipant($blockedConversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson("/api/v1/chat/conversations/{$blockedConversation->id}/messages", [
            'body' => 'blocked',
        ])->assertForbidden();

        $canSendFalseUser = $this->actingAsWithPermissions(['chat.send']);
        $canSendFalseConversation = $this->makeConversation();
        $this->addParticipant($canSendFalseConversation, $canSendFalseUser, ['can_send' => false]);
        $this->postJson("/api/v1/chat/conversations/{$canSendFalseConversation->id}/messages", [
            'body' => 'blocked',
        ])->assertForbidden();

        Sanctum::actingAs($senderWithPerm);
        $editResponse = $this->patchJson("/api/v1/chat/messages/{$sentMessage->id}", [
            'body' => 'edited',
        ])->assertOk();
        $this->assertSame('edited', $editResponse->json('data.body'));
        $this->assertNotNull(Message::query()->findOrFail($sentMessage->id)->edited_at);

        $otherUser = $this->actingAsWithPermissions(['chat.edit']);
        $this->patchJson("/api/v1/chat/messages/{$sentMessage->id}", [
            'body' => 'hacked',
        ])->assertForbidden();

        $importedMessage = $this->makeMessage($conversation, $senderWithPerm, [
            'is_imported' => true,
            'imported_from_conversation_id' => $conversation->id,
            'imported_from_message_id' => $sentMessage->id,
        ]);
        Sanctum::actingAs($senderWithPerm);
        $this->patchJson("/api/v1/chat/messages/{$importedMessage->id}", [
            'body' => 'cant edit',
        ])->assertStatus(422);

        $deleteOwn = $this->deleteJson("/api/v1/chat/messages/{$sentMessage->id}")
            ->assertOk();
        $this->assertSame('deleted', $deleteOwn->json('data.status'));
        $this->assertDatabaseHas('messages', [
            'id' => $sentMessage->id,
            'status' => 'deleted',
        ]);

        $messageByOther = $this->makeMessage($conversation, $otherUser);
        Sanctum::actingAs($senderWithPerm);
        $this->deleteJson("/api/v1/chat/messages/{$messageByOther->id}")
            ->assertForbidden();

        $moderator = $this->actingAsWithPermissions(['chat.admin.moderate', 'chat.delete']);
        $this->deleteJson("/api/v1/chat/messages/{$messageByOther->id}")
            ->assertOk();
        $this->assertDatabaseHas('messages', [
            'id' => $messageByOther->id,
            'status' => 'deleted',
        ]);

        Sanctum::actingAs($senderWithPerm);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonMissing(['id' => $sentMessage->id])
            ->assertJsonMissing(['id' => $messageByOther->id]);

        $lastTestConversation = $this->makeConversation();
        $this->addParticipant($lastTestConversation, $senderWithPerm);
        $m1 = $this->makeMessage($lastTestConversation, $senderWithPerm, ['body' => 'first']);
        $m2 = $this->makeMessage($lastTestConversation, $senderWithPerm, ['body' => 'second']);
        $lastTestConversation->update([
            'last_message_id' => $m2->id,
            'last_message_at' => $m2->created_at,
        ]);
        $this->deleteJson("/api/v1/chat/messages/{$m2->id}")->assertOk();
        $this->assertSame($m1->id, $lastTestConversation->fresh()->last_message_id);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => '',
        ])->assertStatus(422);
    }
}


