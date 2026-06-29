<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatConversationQueryService;
use App\Services\Chat\ChatAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatConversationQueryServiceTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

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
            'title' => 'Query test chat',
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
            'body' => 'Message',
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

    public function test_chat_conversation_query_service_foundation_rules(): void
    {
        /** @var ChatConversationQueryService $service */
        $service = app(ChatConversationQueryService::class);

        $user = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $other = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);

        $visibleConversation = $this->makeConversation(['owner' => $user]);
        $this->addParticipant($visibleConversation, $user);
        $this->addParticipant($visibleConversation, $other);

        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $user, ['access_state' => 'hidden']);

        $noticeConversation = $this->makeConversation();
        $this->addParticipant($noticeConversation, $user, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);

        $blockedHiddenConversation = $this->makeConversation();
        $this->addParticipant($blockedHiddenConversation, $user, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'hide_chat',
        ]);

        $readOnlyConversation = $this->makeConversation();
        $this->addParticipant($readOnlyConversation, $user, ['access_state' => 'read_only']);
        $this->makeMessage($readOnlyConversation, $other, ['body' => 'readonly-visible']);

        $otherConversation = $this->makeConversation(['owner' => $other]);
        $this->addParticipant($otherConversation, $other);

        $visibleIds = $service->visibleConversationsFor($user)->pluck('id')->all();
        $this->assertContains($visibleConversation->id, $visibleIds);
        $this->assertContains($noticeConversation->id, $visibleIds);
        $this->assertContains($readOnlyConversation->id, $visibleIds);
        $this->assertNotContains($hiddenConversation->id, $visibleIds);
        $this->assertNotContains($blockedHiddenConversation->id, $visibleIds);
        $this->assertNotContains($otherConversation->id, $visibleIds);

        $this->assertSame(1, $service->visibleMessagesCountFor($user, $readOnlyConversation));
        $this->assertFalse(app(ChatAccessService::class)->canSendMessage($user, $readOnlyConversation));

        $this->assertSame(0, $service->visibleMessagesCountFor($user, $noticeConversation));

        $historyConversation = $this->makeConversation(['owner' => $user]);
        $participant = $this->addParticipant($historyConversation, $user);
        $this->addParticipant($historyConversation, $other);

        $m1 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);
        $m2 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)]);
        $m3 = $this->makeMessage($historyConversation, $other, ['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)]);

        $participant->update(['history_visible_from_at' => now()->subMinutes(25)]);
        $idsFromAt = $service->visibleMessagesFor($user, $historyConversation)->pluck('id')->all();
        $this->assertNotContains($m1->id, $idsFromAt);
        $this->assertContains($m2->id, $idsFromAt);
        $this->assertContains($m3->id, $idsFromAt);

        $participant->update(['history_visible_from_at' => null, 'history_visible_until_at' => now()->subMinutes(15)]);
        $idsUntilAt = $service->visibleMessagesFor($user, $historyConversation)->pluck('id')->all();
        $this->assertContains($m1->id, $idsUntilAt);
        $this->assertContains($m2->id, $idsUntilAt);
        $this->assertNotContains($m3->id, $idsUntilAt);

        $participant->update([
            'history_visible_until_at' => null,
            'history_visible_from_message_id' => $m2->id,
        ]);
        $idsFromMessageId = $service->visibleMessagesFor($user, $historyConversation)->pluck('id')->all();
        $this->assertNotContains($m1->id, $idsFromMessageId);
        $this->assertContains($m2->id, $idsFromMessageId);
        $this->assertContains($m3->id, $idsFromMessageId);

        $sourceOwner = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $sourceSecond = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $newGroupParticipant = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);

        $sourceDirect = $this->makeConversation([
            'type' => 'direct',
            'visibility' => 'private',
            'owner' => $sourceOwner,
        ]);
        $this->addParticipant($sourceDirect, $sourceOwner, ['role' => 'owner']);
        $this->addParticipant($sourceDirect, $sourceSecond, ['role' => 'member']);
        $sourceDirectMessage = $this->makeMessage($sourceDirect, $sourceOwner);

        $targetGroup = $this->makeConversation([
            'type' => 'group',
            'visibility' => 'private',
            'owner' => $sourceOwner,
            'created_from_conversation_id' => $sourceDirect->id,
            'history_import_mode' => 'full',
        ]);
        $this->addParticipant($targetGroup, $sourceOwner, ['role' => 'owner']);
        $this->addParticipant($targetGroup, $sourceSecond, ['role' => 'member']);
        $this->addParticipant($targetGroup, $newGroupParticipant, ['role' => 'member']);

        $importedMessage = $this->makeMessage($targetGroup, $sourceOwner, [
            'is_imported' => true,
            'imported_from_conversation_id' => $sourceDirect->id,
            'imported_from_message_id' => $sourceDirectMessage->id,
        ]);

        $this->assertSame(
            0,
            $service->visibleMessagesCountFor($newGroupParticipant, $sourceDirect)
        );
        $targetVisibleIds = $service->visibleMessagesFor($newGroupParticipant, $targetGroup)->pluck('id')->all();
        $this->assertContains($importedMessage->id, $targetVisibleIds);

        $admin = $this->makeUserWithPermissions(['chat.admin.view', 'chat.admin.view_metadata']);
        $adminIds = $service->adminConversationsFor($admin)->pluck('id')->all();
        $this->assertContains($visibleConversation->id, $adminIds);

        $nonAdmin = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view']);
        try {
            $service->adminConversationsFor($nonAdmin)->get();
            $this->fail('Expected AuthorizationException for non-admin access.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $metadataUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($visibleConversation, $metadataUser, ['role' => 'member']);
        $this->assertTrue($service->applyAdminMetadataGate($admin, $visibleConversation));
        $this->assertFalse($service->applyAdminMetadataGate($metadataUser, $visibleConversation));

        $unreadConversation = $this->makeConversation(['owner' => $user]);
        $unreadParticipant = $this->addParticipant($unreadConversation, $user, ['last_read_message_id' => null, 'last_read_at' => null]);
        $this->addParticipant($unreadConversation, $other);

        $this->makeMessage($unreadConversation, $user, ['body' => 'own']);
        $otherMessageA = $this->makeMessage($unreadConversation, $other, ['body' => 'other-a']);
        $otherMessageB = $this->makeMessage($unreadConversation, $other, ['body' => 'other-b']);
        $unreadConversation->update([
            'last_message_id' => $otherMessageB->id,
            'last_message_at' => $otherMessageB->created_at,
        ]);

        $this->assertSame(2, $service->unreadCountFor($user, $unreadConversation));

        $unreadParticipant->update(['last_read_message_id' => $otherMessageA->id]);
        $this->assertSame(1, $service->unreadCountFor($user, $unreadConversation));

        $filteredByType = $service->visibleConversationsFor($user, ['type' => 'group'])->pluck('id')->all();
        $this->assertContains($visibleConversation->id, $filteredByType);

        $filteredBySource = $service->visibleConversationsFor($user, ['source' => 'internal'])->pluck('id')->all();
        $this->assertContains($visibleConversation->id, $filteredBySource);

        $unreadFilteredIds = $service->visibleConversationsFor($user, [
            'unread' => true,
            'user' => $user,
        ])->pluck('id')->all();
        $this->assertContains($unreadConversation->id, $unreadFilteredIds);
    }
}

