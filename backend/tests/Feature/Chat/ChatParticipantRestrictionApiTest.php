<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatParticipantRestrictionApiTest extends TestCase
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
            'title' => 'Restriction chat',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
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

    private function makeMessage(Conversation $conversation, User $sender, string $body = 'test'): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'is_imported' => false,
            'sent_at' => now(),
        ]);
    }

    public function test_chat_participant_restriction_api_foundation(): void
    {
        $conversation = $this->makeConversation();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $this->addParticipant($conversation, $member, ['role' => 'member']);
        $message = $this->makeMessage($conversation, $owner);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/access", [
            'access_state' => 'read_only',
        ])->assertUnauthorized();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/block", [
            'block_display_mode' => 'show_notice',
        ])->assertUnauthorized();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/unblock")
            ->assertUnauthorized();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/capabilities", [
            'can_invite' => true,
        ])->assertUnauthorized();

        $normalMember = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
        ]);
        $this->addParticipant($conversation, $normalMember, ['role' => 'member', 'can_manage' => false, 'can_moderate' => false]);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/block", [
            'block_display_mode' => 'show_notice',
        ])->assertForbidden();

        $manager = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
            'chat.participants.manage',
            'chat.admin.moderate',
        ]);
        $this->addParticipant($conversation, $manager, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);

        $block = $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/block", [
            'block_display_mode' => 'show_notice',
            'blocked_reason' => 'moderation',
        ])->assertOk();
        $block->assertJsonPath('data.status', 'blocked');
        $block->assertJsonPath('data.access_state', 'blocked');
        $block->assertJsonPath('data.block_display_mode', 'show_notice');

        $blockedParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $member->id)
            ->firstOrFail();
        $this->assertNotNull($blockedParticipant->blocked_at);
        $this->assertSame($manager->id, $blockedParticipant->blocked_by);
        $this->assertFalse((bool) $blockedParticipant->can_send);
        $this->assertFalse((bool) $blockedParticipant->can_attach);
        $this->assertFalse((bool) $blockedParticipant->can_invite);
        $this->assertFalse((bool) $blockedParticipant->can_remove);
        $this->assertFalse((bool) $blockedParticipant->can_manage);
        $this->assertFalse((bool) $blockedParticipant->can_moderate);

        Sanctum::actingAs($member);
        $member->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/access", [
            'access_state' => 'hidden',
        ])->assertOk();
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonMissing(['id' => $conversation->id]);

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/unblock")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.access_state', 'full');

        $unblocked = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $member->id)
            ->firstOrFail();
        $this->assertNull($unblocked->blocked_by);
        $this->assertNull($unblocked->blocked_at);
        $this->assertNull($unblocked->blocked_reason);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/access", [
            'access_state' => 'read_only',
        ])->assertOk();
        $readOnly = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $member->id)
            ->firstOrFail();
        $this->assertFalse((bool) $readOnly->can_send);
        $this->assertFalse((bool) $readOnly->can_attach);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'read only should fail',
            'type' => 'text',
        ])->assertForbidden();

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/capabilities", [
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => true,
        ])->assertOk();
        $updatedCaps = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $member->id)
            ->firstOrFail();
        $this->assertTrue((bool) $updatedCaps->can_send);
        $this->assertTrue((bool) $updatedCaps->can_attach);
        $this->assertTrue((bool) $updatedCaps->can_invite);

        Sanctum::actingAs($member);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/capabilities", [
            'can_manage' => true,
        ])->assertForbidden();

        Sanctum::actingAs($manager);
        $lastOwnerConversation = $this->makeConversation(['owner' => $manager]);
        $this->addParticipant($lastOwnerConversation, $manager, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $lastOwnerTarget = User::factory()->create();
        $this->addParticipant($lastOwnerConversation, $lastOwnerTarget, ['role' => 'member']);
        $managerPeer = User::factory()->create();
        $this->addParticipant($lastOwnerConversation, $managerPeer, ['role' => 'admin', 'can_manage' => true, 'can_moderate' => true]);
        Sanctum::actingAs($managerPeer);
        $managerPeer->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.participants.manage'])->id,
            Permission::firstOrCreate(['name' => 'chat.admin.moderate'])->id,
        ]);
        $this->patchJson("/api/v1/chat/conversations/{$lastOwnerConversation->id}/participants/{$manager->id}/block", [
            'block_display_mode' => 'hide_chat',
        ])->assertStatus(422);
        $this->patchJson("/api/v1/chat/conversations/{$lastOwnerConversation->id}/participants/{$manager->id}/capabilities", [
            'can_manage' => false,
        ])->assertStatus(422);

        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'action' => 'participant.blocked',
            'target_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'action' => 'participant.unblocked',
            'target_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'action' => 'participant.hidden',
            'target_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'action' => 'participant.capabilities_updated',
            'target_user_id' => $member->id,
        ]);
        $this->assertGreaterThanOrEqual(4, ChatModerationLog::query()->count());

        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/access", [
            'access_state' => 'wrong',
        ])->assertStatus(422);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$member->id}/block", [
            'block_display_mode' => 'invalid',
        ])->assertStatus(422);

        $this->assertSame($conversation->id, $message->conversation_id);
    }
}
