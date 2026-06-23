<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatGroupConversationTest extends TestCase
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
            'title' => 'Group Scenario',
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

    public function test_group_chat_core_scenarios_are_covered(): void
    {
        $owner = $this->actingAsWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
        ]);
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();

        $privateGroup = $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Private Group',
            'visibility' => 'private',
            'join_policy' => 'invite_only',
            'participant_ids' => [$memberA->id, $memberB->id],
        ])->assertCreated();
        $privateGroupId = (int) $privateGroup->json('data.id');
        $privateConversation = Conversation::query()->findOrFail($privateGroupId);
        $this->assertSame('group', $privateConversation->type);
        $this->assertSame('private', $privateConversation->visibility);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $privateGroupId, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $privateGroupId, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $privateGroupId, 'user_id' => $memberB->id]);

        $publicTarget = User::factory()->create();
        $publicGroup = $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Public Group',
            'visibility' => 'public',
            'join_policy' => 'public_join',
            'participant_ids' => [$publicTarget->id],
        ])->assertCreated();
        $this->assertSame('public', data_get($publicGroup->json(), 'data.visibility'));

        Sanctum::actingAs($owner);
        $message = $this->postJson("/api/v1/chat/conversations/{$privateGroupId}/messages", [
            'body' => 'group message',
            'type' => 'text',
        ])->assertCreated();
        $messageId = (int) $message->json('data.id');
        $this->patchJson("/api/v1/chat/messages/{$messageId}", ['body' => 'group message edited'])
            ->assertOk();
        $this->deleteJson("/api/v1/chat/messages/{$messageId}")
            ->assertOk();

        $participant = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send', 'chat.participants.view']);
        $this->addParticipant($privateConversation, $participant);
        $this->getJson('/api/v1/chat/conversations')->assertOk();
        $this->getJson("/api/v1/chat/conversations/{$privateGroupId}")->assertOk();
        $this->getJson("/api/v1/chat/conversations/{$privateGroupId}/participants")
            ->assertOk()
            ->assertJsonMissingPath('data.0.blocked_reason')
            ->assertJsonMissingPath('data.0.can_moderate');

        $this->postJson("/api/v1/chat/conversations/{$privateGroupId}/messages", [
            'body' => 'participant send',
            'type' => 'text',
        ])->assertCreated();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $this->getJson("/api/v1/chat/conversations/{$privateGroupId}")->assertNotFound();
        $this->postJson("/api/v1/chat/conversations/{$privateGroupId}/messages", [
            'body' => 'outsider',
            'type' => 'text',
        ])->assertForbidden();

        $readOnlyUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $readOnlyConversation = $this->makeConversation();
        $this->addParticipant($readOnlyConversation, $readOnlyUser, ['access_state' => 'read_only']);
        $this->getJson("/api/v1/chat/conversations/{$readOnlyConversation->id}")->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$readOnlyConversation->id}/messages", [
            'body' => 'blocked',
            'type' => 'text',
        ])->assertForbidden();

        $hiddenUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $hiddenUser, ['access_state' => 'hidden']);
        $this->getJson("/api/v1/chat/conversations/{$hiddenConversation->id}")->assertNotFound();

        $blockedUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $blockedConversation = $this->makeConversation();
        $this->addParticipant($blockedConversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->getJson("/api/v1/chat/conversations/{$blockedConversation->id}")->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$blockedConversation->id}/messages", [
            'body' => 'blocked',
            'type' => 'text',
        ])->assertForbidden();

        $manager = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
        ]);
        $managedConversation = $this->makeConversation();
        $this->addParticipant($managedConversation, $manager, ['role' => 'owner', 'can_invite' => true, 'can_remove' => true, 'can_manage' => true]);
        $candidate = User::factory()->create();
        $this->postJson("/api/v1/chat/conversations/{$managedConversation->id}/participants", [
            'user_id' => $candidate->id,
            'role' => 'member',
        ])->assertCreated();
        $this->deleteJson("/api/v1/chat/conversations/{$managedConversation->id}/participants/{$candidate->id}")
            ->assertOk();

        $leaver = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $leaveConversation = $this->makeConversation();
        $this->addParticipant($leaveConversation, $leaver);
        $this->postJson("/api/v1/chat/conversations/{$leaveConversation->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.status', 'left');
    }
}
