<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatHistoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatMessageAuditLoggingTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(User $owner, array $overrides = []): Conversation
    {
        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Audit',
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

    public function test_message_create_and_update_create_audit_logs_without_raw_body(): void
    {
        $actor = $this->actingAsWithPermissions(['chat.send', 'chat.edit', 'chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($actor);
        $this->addParticipant($conversation, $actor, ['role' => 'owner', 'can_send' => true, 'can_manage' => true]);

        $create = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Sensitive hello',
            'type' => 'text',
        ])->assertCreated();

        $messageId = (int) $create->json('data.id');

        $this->patchJson("/api/v1/chat/messages/{$messageId}", [
            'body' => 'Edited sensitive text',
        ])->assertOk();

        $createdLog = ChatModerationLog::query()
            ->where('action', 'message.created')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();
        $updatedLog = ChatModerationLog::query()
            ->where('action', 'message.updated')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();

        $this->assertNotNull($createdLog);
        $this->assertNotNull($updatedLog);
        $this->assertSame($actor->id, $createdLog->actor_id);
        $this->assertSame($actor->id, $updatedLog->actor_id);
        $this->assertSame($conversation->id, $createdLog->conversation_id);
        $this->assertSame($conversation->id, $updatedLog->conversation_id);
        $this->assertArrayNotHasKey('body', $createdLog->metadata ?? []);
        $this->assertArrayNotHasKey('body', $updatedLog->metadata ?? []);
        $this->assertSame(['body'], $updatedLog->metadata['edited_fields'] ?? []);
    }

    public function test_admin_reply_and_delete_are_audited_and_unauthorized_delete_is_not_logged(): void
    {
        $admin = $this->actingAsWithPermissions([
            'chat.send',
            'chat.delete',
            'chat.admin.reply',
            'chat.admin.delete_messages',
            'chat.admin.moderate',
            'chat.view',
            'chat.conversations.view',
        ]);
        $conversation = $this->makeConversation($admin);
        $targetUser = User::factory()->create();
        $this->addParticipant($conversation, $admin, ['role' => 'owner', 'can_send' => true, 'can_manage' => true, 'can_moderate' => true]);
        $this->addParticipant($conversation, $targetUser, ['role' => 'member', 'can_send' => true]);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Admin reply',
            'type' => 'text',
        ])->assertCreated();
        $messageId = (int) $response->json('data.id');

        $adminReplyLog = ChatModerationLog::query()
            ->where('action', 'message.admin_reply_created')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();
        $this->assertNotNull($adminReplyLog);
        $this->assertSame($admin->id, $adminReplyLog->actor_id);

        $this->deleteJson("/api/v1/chat/messages/{$messageId}")
            ->assertOk();

        $deleteLog = ChatModerationLog::query()
            ->where('action', 'message.deleted')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();
        $this->assertNotNull($deleteLog);
        $this->assertSame($admin->id, $deleteLog->actor_id);

        $outsider = $this->actingAsWithPermissions(['chat.delete']);
        Sanctum::actingAs($admin);
        $second = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Second message',
            'type' => 'text',
        ])->assertCreated();
        $secondMessageId = (int) $second->json('data.id');

        Sanctum::actingAs($outsider);
        $this->deleteJson("/api/v1/chat/messages/{$secondMessageId}")
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'action' => 'message.deleted',
            'actor_id' => $outsider->id,
            'message_id' => $secondMessageId,
        ]);
    }

    public function test_external_message_and_import_batch_are_audited_with_safe_metadata(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.external_api.send',
            'chat.view',
            'chat.conversations.view',
            'chat.create',
            'chat.conversations.create',
        ]);
        $externalConversation = $this->makeConversation($actor, ['type' => 'external', 'source' => 'api']);
        $this->addParticipant($externalConversation, $actor, ['role' => 'owner', 'can_send' => true]);

        $this->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $externalConversation->id,
            'external_provider' => 'crm',
            'external_message_id' => 'ext-123',
            'body' => 'External body',
            'type' => 'text',
            'metadata' => [
                'source' => 'external-system',
                'token' => 'unsafe',
                'nested' => [
                    'secret' => 'unsafe',
                    'status' => 'ok',
                ],
            ],
        ])->assertCreated();

        $externalLog = ChatModerationLog::query()
            ->where('action', 'message.external_created')
            ->latest('id')
            ->first();
        $this->assertNotNull($externalLog);
        $this->assertSame($actor->id, $externalLog->actor_id);
        $this->assertSame('crm', $externalLog->metadata['external_provider'] ?? null);
        $this->assertSame('ext-123', $externalLog->metadata['external_message_id'] ?? null);
        $this->assertArrayNotHasKey('token', $externalLog->metadata ?? []);

        $sourceConversation = $this->makeConversation($actor, ['type' => 'direct']);
        $targetConversation = $this->makeConversation($actor, ['type' => 'group']);
        $this->addParticipant($sourceConversation, $actor, ['role' => 'owner', 'can_send' => true]);
        $this->addParticipant($targetConversation, $actor, ['role' => 'owner', 'can_send' => true]);

        $this->postJson("/api/v1/chat/conversations/{$sourceConversation->id}/messages", [
            'body' => 'Source import message',
            'type' => 'text',
        ])->assertCreated();

        /** @var ChatHistoryImportService $historyImportService */
        $historyImportService = app(ChatHistoryImportService::class);
        $historyImportService->importHistory($actor, $sourceConversation, $targetConversation, 'full');

        $importLog = ChatModerationLog::query()
            ->where('action', 'message.imported')
            ->latest('id')
            ->first();
        $this->assertNotNull($importLog);
        $this->assertSame($actor->id, $importLog->actor_id);
        $this->assertSame($targetConversation->id, $importLog->metadata['conversation_id'] ?? null);
        $this->assertSame(true, $importLog->metadata['was_imported'] ?? null);
    }
}

