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

class ChatConversationCreationApiTest extends TestCase
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
            'title' => 'Test group',
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

    public function test_chat_conversation_creation_and_participants_foundation(): void
    {
        $target = User::factory()->create();
        $this->postJson('/api/v1/chat/conversations/direct', ['user_id' => $target->id])->assertUnauthorized();
        $this->postJson('/api/v1/chat/conversations/group', [
            'visibility' => 'private',
            'participant_ids' => [$target->id],
        ])->assertUnauthorized();

        $creator = $this->actingAsWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
        ]);

        $directResponse = $this->postJson('/api/v1/chat/conversations/direct', [
            'user_id' => $target->id,
        ])->assertCreated();

        $directId = (int) $directResponse->json('data.id');
        $directConversation = Conversation::query()->findOrFail($directId);
        $this->assertSame('direct', $directConversation->type);
        $this->assertSame('private', $directConversation->visibility);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $directId,
            'user_id' => $creator->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $directId,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        $this->assertSame(2, ConversationParticipant::query()->where('conversation_id', $directId)->where('status', 'active')->count());

        $groupTargetA = User::factory()->create();
        $groupTargetB = User::factory()->create();

        $privateGroupResponse = $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Private Group',
            'visibility' => 'private',
            'join_policy' => 'invite_only',
            'participant_ids' => [$groupTargetA->id, $groupTargetB->id],
        ])->assertCreated();
        $privateGroupId = (int) $privateGroupResponse->json('data.id');
        $privateGroup = Conversation::query()->findOrFail($privateGroupId);
        $this->assertSame('group', $privateGroup->type);
        $this->assertSame('private', $privateGroup->visibility);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $privateGroupId,
            'user_id' => $creator->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $privateGroupId,
            'user_id' => $groupTargetA->id,
            'role' => 'member',
        ]);

        $publicGroupTarget = User::factory()->create();
        $publicGroupResponse = $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Public Group',
            'visibility' => 'public',
            'join_policy' => 'public_join',
            'participant_ids' => [$publicGroupTarget->id],
        ])->assertCreated();
        $publicGroupId = (int) $publicGroupResponse->json('data.id');
        $publicGroup = Conversation::query()->findOrFail($publicGroupId);
        $this->assertSame('public', $publicGroup->visibility);

        $this->postJson('/api/v1/chat/conversations/group', [
            'visibility' => 'team-private',
            'participant_ids' => [$publicGroupTarget->id],
        ])->assertStatus(422);

        $this->postJson('/api/v1/chat/conversations/group', [
            'visibility' => 'private',
            'join_policy' => 'invalid_policy',
            'participant_ids' => [$publicGroupTarget->id],
        ])->assertStatus(422);

        $dupCandidate = User::factory()->create();
        $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Dup check',
            'visibility' => 'private',
            'participant_ids' => [$dupCandidate->id, $dupCandidate->id],
        ])->assertStatus(422);

        $inviteConversation = $this->makeConversation(['owner' => $creator]);
        $this->addParticipant($inviteConversation, $creator, [
            'role' => 'owner',
            'can_invite' => true,
            'can_remove' => true,
            'can_manage' => true,
        ]);
        $newParticipant = User::factory()->create();
        $this->postJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants", [
            'user_id' => $newParticipant->id,
            'role' => 'viewer',
            'capabilities' => [
                'can_send' => false,
            ],
        ])->assertCreated();

        $beforeCount = ConversationParticipant::query()
            ->where('conversation_id', $inviteConversation->id)
            ->where('user_id', $newParticipant->id)
            ->count();
        $this->postJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants", [
            'user_id' => $newParticipant->id,
        ])->assertCreated();
        $afterCount = ConversationParticipant::query()
            ->where('conversation_id', $inviteConversation->id)
            ->where('user_id', $newParticipant->id)
            ->count();
        $this->assertSame($beforeCount, $afterCount);

        $this->getJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants")
            ->assertOk()
            ->assertJsonMissingPath('data.0.blocked_reason')
            ->assertJsonMissingPath('data.0.can_moderate');

        $this->deleteJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants/{$newParticipant->id}")
            ->assertOk();
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $inviteConversation->id,
            'user_id' => $newParticipant->id,
            'status' => 'removed',
        ]);

        $this->deleteJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants/{$creator->id}")
            ->assertStatus(422);

        $limitedUser = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.view',
        ]);
        $conversationLimited = $this->makeConversation(['owner' => $creator]);
        $this->addParticipant($conversationLimited, $limitedUser, [
            'role' => 'member',
            'can_invite' => false,
            'can_remove' => false,
        ]);
        $this->addParticipant($conversationLimited, $creator, [
            'role' => 'owner',
            'can_invite' => true,
            'can_remove' => true,
        ]);

        $candidate = User::factory()->create();
        $this->postJson("/api/v1/chat/conversations/{$conversationLimited->id}/participants", [
            'user_id' => $candidate->id,
        ])->assertForbidden();

        $existingMember = User::factory()->create();
        $this->addParticipant($conversationLimited, $existingMember);
        $this->deleteJson("/api/v1/chat/conversations/{$conversationLimited->id}/participants/{$existingMember->id}")
            ->assertForbidden();

        $noPermissionUser = $this->actingAsWithPermissions(['chat.view']);
        $someTarget = User::factory()->create();
        $this->postJson('/api/v1/chat/conversations/direct', ['user_id' => $someTarget->id])->assertForbidden();
        $this->getJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants")->assertForbidden();
        $this->postJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants", ['user_id' => $someTarget->id])->assertForbidden();
        $this->deleteJson("/api/v1/chat/conversations/{$inviteConversation->id}/participants/{$someTarget->id}")->assertForbidden();
    }
}

