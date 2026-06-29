<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatAccessFoundationTest extends TestCase
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
            'title' => 'Test chat',
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
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender?->id,
            'sender_type' => $sender ? 'user' : 'system',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'Hello',
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
        ], $overrides));
    }

    public function test_chat_access_foundation_rules(): void
    {
        $user = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $conversation = $this->makeConversation(['owner' => $user]);
        $this->addParticipant($conversation, $user, ['role' => 'owner', 'can_send' => true]);

        /** @var ChatAccessService $access */
        $access = app(ChatAccessService::class);

        $this->assertTrue($access->canViewConversation($user, $conversation));
        $this->assertTrue($access->canSendMessage($user, $conversation));
        $this->assertTrue(Gate::forUser($user)->allows('sendMessage', $conversation));

        $readOnlyUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $readOnlyConversation = $this->makeConversation();
        $this->addParticipant($readOnlyConversation, $readOnlyUser, ['access_state' => 'read_only']);
        $this->assertTrue($access->canViewConversation($readOnlyUser, $readOnlyConversation));
        $this->assertFalse($access->canSendMessage($readOnlyUser, $readOnlyConversation));

        $hiddenUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view']);
        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $hiddenUser, ['access_state' => 'hidden']);
        $this->assertFalse($access->canViewConversation($hiddenUser, $hiddenConversation));
        $this->assertFalse($access->canViewMessages($hiddenUser, $hiddenConversation));

        $blockedUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $blockedConversation = $this->makeConversation();
        $this->addParticipant($blockedConversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $bounds = $access->getVisibleHistoryBounds($blockedUser, $blockedConversation);
        $this->assertTrue($access->canViewConversation($blockedUser, $blockedConversation));
        $this->assertFalse($access->canViewMessages($blockedUser, $blockedConversation));
        $this->assertFalse($access->canSendMessage($blockedUser, $blockedConversation));
        $this->assertTrue($bounds['notice_only']);

        $noSendUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $noSendConversation = $this->makeConversation();
        $this->addParticipant($noSendConversation, $noSendUser, ['can_send' => false]);
        $this->assertFalse($access->canSendMessage($noSendUser, $noSendConversation));

        $owner = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.participants.add']);
        $member = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.participants.add']);
        $inviteConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($inviteConversation, $owner, ['role' => 'owner', 'can_invite' => true]);
        $this->addParticipant($inviteConversation, $member, ['role' => 'member', 'can_invite' => false]);
        $this->assertTrue($access->canInvite($owner, $inviteConversation));
        $this->assertFalse($access->canInvite($member, $inviteConversation));

        $attachUser = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send', 'chat.attachments.upload']);
        $attachConversation = $this->makeConversation();
        $this->addParticipant($attachConversation, $attachUser, ['can_attach' => false]);
        $this->assertFalse($access->canAttachFile($attachUser, $attachConversation));

        $admin = $this->makeUserWithPermissions(['chat.admin.view', 'chat.admin.view_metadata']);
        $regular = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view']);
        $adminConversation = $this->makeConversation();
        $this->addParticipant($adminConversation, $regular, ['role' => 'member']);
        $this->assertTrue(Gate::forUser($admin)->allows('viewAdminMetadata', $adminConversation));
        $this->assertFalse(Gate::forUser($regular)->allows('viewAdminMetadata', $adminConversation));

        $owner = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $second = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $third = $this->makeUserWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);

        $direct = $this->makeConversation([
            'type' => 'direct',
            'visibility' => 'private',
            'owner' => $owner,
        ]);
        $this->addParticipant($direct, $owner, ['role' => 'owner']);
        $this->addParticipant($direct, $second, ['role' => 'member']);

        $sourceMessage = $this->makeMessage($direct, $owner);

        $group = $this->makeConversation([
            'type' => 'group',
            'visibility' => 'private',
            'owner' => $owner,
            'created_from_conversation_id' => $direct->id,
            'history_import_mode' => 'full',
        ]);
        $this->addParticipant($group, $owner, ['role' => 'owner']);
        $this->addParticipant($group, $second, ['role' => 'member']);
        $this->addParticipant($group, $third, ['role' => 'member']);

        $importedMessage = $this->makeMessage($group, $owner, [
            'is_imported' => true,
            'imported_from_conversation_id' => $direct->id,
            'imported_from_message_id' => $sourceMessage->id,
        ]);

        $this->assertFalse($access->canViewConversation($third, $direct));
        $this->assertFalse($access->isMessageVisibleToUser($third, $direct, $sourceMessage));
        $this->assertTrue($access->canViewConversation($third, $group));
        $this->assertTrue($access->isMessageVisibleToUser($third, $group, $importedMessage));
    }
}

