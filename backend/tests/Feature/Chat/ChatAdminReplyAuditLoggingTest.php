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
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatAdminReplyAuditLoggingTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'support',
            'visibility' => 'private',
            'title' => 'Admin reply audit',
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
        ]);
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

    public function test_admin_reply_creates_admin_reply_audit_log_with_safe_metadata(): void
    {
        $admin = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'chat.admin.reply',
        ]);

        $conversation = $this->makeConversation($admin);
        $this->addParticipant($conversation, $admin, ['role' => 'owner', 'can_send' => true]);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Sensitive admin reply content',
            'type' => 'text',
        ])->assertCreated();

        $messageId = (int) $response->json('data.id');

        $log = ChatModerationLog::query()
            ->where('action', 'message.admin_reply_created')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($messageId, $log->message_id);

        $metadata = $log->metadata ?? [];
        $this->assertSame(true, $metadata['admin_reply'] ?? null);
        $this->assertSame('admin_reply', $metadata['source'] ?? null);
        $this->assertSame('text', $metadata['message_type'] ?? null);
        $this->assertSame('support', $metadata['conversation_type'] ?? null);
        $this->assertSame('internal', $metadata['conversation_source'] ?? null);
        $this->assertSame(false, $metadata['had_attachments'] ?? null);

        $this->assertArrayNotHasKey('body', $metadata);
        $this->assertArrayNotHasKey('content', $metadata);
        $this->assertArrayNotHasKey('token', $metadata);
        $this->assertArrayNotHasKey('secret', $metadata);
        $this->assertArrayNotHasKey('signature', $metadata);
        $this->assertArrayNotHasKey('authorization', $metadata);
        $this->assertArrayNotHasKey('device_key', $metadata);
        $this->assertArrayNotHasKey('user_agent', $metadata);
        $this->assertArrayNotHasKey('ip_address', $metadata);
        $this->assertArrayNotHasKey('disk', $metadata);
        $this->assertArrayNotHasKey('path', $metadata);
        $this->assertArrayNotHasKey('checksum', $metadata);
    }

    public function test_normal_user_message_does_not_create_admin_reply_audit_log(): void
    {
        $user = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user, ['role' => 'owner', 'can_send' => true]);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Normal user message',
            'type' => 'text',
        ])->assertCreated();

        $messageId = (int) $response->json('data.id');

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'action' => 'message.admin_reply_created',
            'message_id' => $messageId,
            'actor_id' => $user->id,
        ]);
    }

    public function test_unauthorized_admin_reply_does_not_create_admin_reply_audit_log(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_send' => true]);

        $unauthorized = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $unauthorized, ['role' => 'member', 'can_send' => false, 'access_state' => 'read_only']);

        $before = ChatModerationLog::query()
            ->where('action', 'message.admin_reply_created')
            ->count();

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Should not be sent',
            'type' => 'text',
        ])->assertForbidden();

        $after = ChatModerationLog::query()
            ->where('action', 'message.admin_reply_created')
            ->count();

        $this->assertSame($before, $after);
    }
}


