<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatConversationLifecycleApiTest extends TestCase
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
            'title' => 'Lifecycle chat',
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

    public function test_chat_conversation_lifecycle_api_foundation(): void
    {
        $conversation = $this->makeConversation();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $member, ['role' => 'member']);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertUnauthorized();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/close")->assertUnauthorized();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/archive")->assertUnauthorized();

        $memberUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $memberUser, ['role' => 'member']);
        $leaveResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertOk();
        $left = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $memberUser->id)
            ->firstOrFail();
        $this->assertSame('left', $left->status);
        $this->assertNotNull($left->left_at);
        $this->assertSame('hidden', $left->access_state);
        $this->assertFalse((bool) $left->can_send);
        $this->assertFalse((bool) $left->can_attach);
        $leaveResponse->assertJsonPath('data.status', 'left');
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertStatus(422);

        $lastOwner = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $ownerOnlyConversation = $this->makeConversation(['owner' => $lastOwner]);
        $this->addParticipant($ownerOnlyConversation, $lastOwner, ['role' => 'owner', 'can_manage' => true]);
        $this->postJson("/api/v1/chat/conversations/{$ownerOnlyConversation->id}/leave")->assertStatus(422);

        $closer = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.conversations.close',
            'chat.conversations.archive',
            'chat.send',
        ]);
        $closeConversation = $this->makeConversation(['owner' => $closer]);
        $this->addParticipant($closeConversation, $closer, ['role' => 'owner', 'can_manage' => true]);
        $reader = User::factory()->create();
        $this->addParticipant($closeConversation, $reader, ['role' => 'member']);

        $this->patchJson("/api/v1/chat/conversations/{$closeConversation->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->assertSame('closed', $closeConversation->fresh()->status);

        $this->postJson("/api/v1/chat/conversations/{$closeConversation->id}/messages", [
            'body' => 'must fail while closed',
            'type' => 'text',
        ])->assertStatus(422);

        $limitedCloser = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.conversations.close',
        ]);
        $limitedConversation = $this->makeConversation();
        $this->addParticipant($limitedConversation, $limitedCloser, ['role' => 'member', 'can_manage' => false]);
        $this->patchJson("/api/v1/chat/conversations/{$limitedConversation->id}/close")->assertForbidden();

        Sanctum::actingAs($closer);
        $archiveConversation = $this->makeConversation(['owner' => $closer]);
        $this->addParticipant($archiveConversation, $closer, ['role' => 'owner', 'can_manage' => true]);
        $this->patchJson("/api/v1/chat/conversations/{$archiveConversation->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        $this->assertSame('archived', $archiveConversation->fresh()->status);

        $this->postJson("/api/v1/chat/conversations/{$archiveConversation->id}/messages", [
            'body' => 'must fail while archived',
            'type' => 'text',
        ])->assertStatus(422);

        $limitedArchiver = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.conversations.archive',
        ]);
        $limitedArchiveConversation = $this->makeConversation();
        $this->addParticipant($limitedArchiveConversation, $limitedArchiver, ['role' => 'member', 'can_manage' => false]);
        $this->patchJson("/api/v1/chat/conversations/{$limitedArchiveConversation->id}/archive")->assertForbidden();

        Sanctum::actingAs($closer);
        $this->getJson("/api/v1/chat/conversations/{$closeConversation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->getJson("/api/v1/chat/conversations/{$archiveConversation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'actor_id' => $memberUser->id,
            'action' => 'conversation.left',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $closeConversation->id,
            'actor_id' => $closer->id,
            'action' => 'conversation.closed',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $archiveConversation->id,
            'actor_id' => $closer->id,
            'action' => 'conversation.archived',
        ]);
        $this->assertGreaterThanOrEqual(3, ChatModerationLog::query()->count());
    }
}
