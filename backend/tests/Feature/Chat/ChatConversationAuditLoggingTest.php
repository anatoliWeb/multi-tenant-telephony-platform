<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatConversationAuditLoggingTest extends TestCase
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
            'title' => 'Conversation audit',
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

    public function test_direct_and_group_creation_write_conversation_audit_logs(): void
    {
        $creator = $this->actingAsWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
        ]);
        $target = User::factory()->create();

        $direct = $this->postJson('/api/v1/chat/conversations/direct', [
            'user_id' => $target->id,
        ])->assertCreated();
        $directId = (int) $direct->json('data.id');

        $groupUserA = User::factory()->create();
        $groupUserB = User::factory()->create();
        $group = $this->postJson('/api/v1/chat/conversations/group', [
            'title' => 'Audit group',
            'visibility' => 'private',
            'participant_ids' => [$groupUserA->id, $groupUserB->id],
        ])->assertCreated();
        $groupId = (int) $group->json('data.id');

        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $directId,
            'actor_id' => $creator->id,
            'action' => 'conversation.direct_created',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $groupId,
            'actor_id' => $creator->id,
            'action' => 'conversation.group_created',
        ]);

        $directLog = ChatModerationLog::query()->where('conversation_id', $directId)->where('action', 'conversation.direct_created')->latest('id')->first();
        $this->assertNotNull($directLog);
        $this->assertSame('direct', $directLog->metadata['conversation_type'] ?? null);
        $this->assertSame('private', $directLog->metadata['visibility'] ?? null);
        $this->assertArrayNotHasKey('token', $directLog->metadata ?? []);
        $this->assertArrayNotHasKey('secret', $directLog->metadata ?? []);
    }

    public function test_close_archive_leave_write_conversation_audit_logs_and_unauthorized_action_does_not(): void
    {
        $owner = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.conversations.close',
            'chat.conversations.archive',
        ]);
        $member = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $member, ['role' => 'member']);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/close")->assertOk();
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'actor_id' => $owner->id,
            'action' => 'conversation.closed',
        ]);

        $secondConversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($secondConversation, $owner, ['role' => 'owner', 'can_manage' => true]);
        $this->patchJson("/api/v1/chat/conversations/{$secondConversation->id}/archive")->assertOk();
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $secondConversation->id,
            'actor_id' => $owner->id,
            'action' => 'conversation.archived',
        ]);

        $leaver = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $leaveConversation = $this->makeConversation();
        $this->addParticipant($leaveConversation, $leaver, ['role' => 'member']);
        $this->postJson("/api/v1/chat/conversations/{$leaveConversation->id}/leave")->assertOk();
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $leaveConversation->id,
            'actor_id' => $leaver->id,
            'action' => 'conversation.left',
        ]);

        $unauthorized = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.conversations.close']);
        $forbiddenConversation = $this->makeConversation();
        $this->addParticipant($forbiddenConversation, $unauthorized, ['role' => 'member', 'can_manage' => false]);
        $this->patchJson("/api/v1/chat/conversations/{$forbiddenConversation->id}/close")->assertForbidden();
        $this->assertDatabaseMissing('chat_moderation_logs', [
            'conversation_id' => $forbiddenConversation->id,
            'actor_id' => $unauthorized->id,
            'action' => 'conversation.closed',
        ]);
    }

    public function test_sanitizer_strips_nested_sensitive_metadata(): void
    {
        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);

        $sanitized = $service->sanitizeMetadata([
            'source' => 'conversation_lifecycle',
            'nested' => [
                'authorization' => 'Bearer xxx',
                'token' => 'secret',
                'status' => 'active',
                'children' => [
                    'ip_address' => '127.0.0.1',
                    'visibility' => 'private',
                ],
            ],
        ]);

        $this->assertSame('conversation_lifecycle', $sanitized['source'] ?? null);
        $this->assertSame('active', $sanitized['nested']['status'] ?? null);
        $this->assertSame('private', $sanitized['nested']['children']['visibility'] ?? null);
        $this->assertArrayNotHasKey('authorization', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('token', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('ip_address', $sanitized['nested']['children'] ?? []);
    }
}

