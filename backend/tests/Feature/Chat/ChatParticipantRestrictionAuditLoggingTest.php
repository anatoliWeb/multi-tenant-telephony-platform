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
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatParticipantRestrictionAuditLoggingTest extends TestCase
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
            'title' => 'Restriction audit',
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

    public function test_block_unblock_and_access_changes_write_normalized_participant_logs(): void
    {
        $manager = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.participants.manage',
            'chat.admin.moderate',
        ]);
        $target = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $manager]);
        $this->addParticipant($conversation, $manager, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $targetParticipant = $this->addParticipant($conversation, $target, ['role' => 'member']);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/block", [
            'block_display_mode' => 'show_notice',
            'blocked_reason' => 'contains-sensitive-details',
        ])->assertOk();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/unblock")->assertOk();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/access", [
            'access_state' => 'read_only',
        ])->assertOk();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/access", [
            'access_state' => 'hidden',
        ])->assertOk();
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/access", [
            'access_state' => 'full',
        ])->assertOk();

        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'target_user_id' => $target->id,
            'action' => 'participant.blocked',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'target_user_id' => $target->id,
            'action' => 'participant.unblocked',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'target_user_id' => $target->id,
            'action' => 'participant.read_only',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'target_user_id' => $target->id,
            'action' => 'participant.hidden',
        ]);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'target_user_id' => $target->id,
            'action' => 'participant.visible_restored',
        ]);

        $blockedLog = ChatModerationLog::query()
            ->where('action', 'participant.blocked')
            ->where('target_user_id', $target->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($blockedLog);
        $this->assertSame($manager->id, $blockedLog->actor_id);
        $this->assertSame($conversation->id, $blockedLog->conversation_id);
        $this->assertSame($targetParticipant->id, $blockedLog->metadata['participant_id'] ?? null);
        $this->assertSame('full', $blockedLog->metadata['old_access_state'] ?? null);
        $this->assertSame('blocked', $blockedLog->metadata['new_access_state'] ?? null);
        $this->assertArrayNotHasKey('token', $blockedLog->metadata ?? []);
        $this->assertArrayNotHasKey('secret', $blockedLog->metadata ?? []);
        $this->assertArrayNotHasKey('authorization', $blockedLog->metadata ?? []);
        $this->assertArrayNotHasKey('blocked_reason', $blockedLog->metadata ?? []);
    }

    public function test_unauthorized_restriction_does_not_create_log_and_no_duplicate_log_per_action(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $target = User::factory()->create();
        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $this->addParticipant($conversation, $target);
        $this->addParticipant($conversation, $outsider, ['role' => 'member', 'can_manage' => false, 'can_moderate' => false]);

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/block", [
            'block_display_mode' => 'show_notice',
        ])->assertForbidden();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'conversation_id' => $conversation->id,
            'actor_id' => $outsider->id,
            'target_user_id' => $target->id,
            'action' => 'participant.blocked',
        ]);

        $manager = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.participants.manage',
            'chat.admin.moderate',
        ]);
        $this->addParticipant($conversation, $manager, ['role' => 'admin', 'can_manage' => true, 'can_moderate' => true]);
        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$target->id}/block", [
            'block_display_mode' => 'show_notice',
        ])->assertOk();

        $count = ChatModerationLog::query()
            ->where('conversation_id', $conversation->id)
            ->where('target_user_id', $target->id)
            ->where('action', 'participant.blocked')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_sanitizer_strips_nested_sensitive_metadata_for_participant_logs(): void
    {
        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);

        $sanitized = $service->sanitizeMetadata([
            'restriction_source' => 'participant_restriction',
            'nested' => [
                'token' => 'secret',
                'ip_address' => '127.0.0.1',
                'allowed' => 'yes',
                'children' => [
                    'authorization' => 'Bearer x',
                    'new_access_state' => 'read_only',
                ],
            ],
        ]);

        $this->assertSame('participant_restriction', $sanitized['restriction_source'] ?? null);
        $this->assertSame('yes', $sanitized['nested']['allowed'] ?? null);
        $this->assertSame('read_only', $sanitized['nested']['children']['new_access_state'] ?? null);
        $this->assertArrayNotHasKey('token', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('ip_address', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('authorization', $sanitized['nested']['children'] ?? []);
    }
}


