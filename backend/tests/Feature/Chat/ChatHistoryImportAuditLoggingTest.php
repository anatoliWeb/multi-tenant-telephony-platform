<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatHistoryImportAuditLoggingTest extends TestCase
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

    private function makeConversation(User $owner, array $overrides = []): Conversation
    {
        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'direct',
            'visibility' => 'private',
            'title' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
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
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => false,
            'can_remove' => false,
            'can_manage' => false,
            'can_moderate' => false,
            'joined_at' => now(),
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, User $sender, array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'message',
            'status' => 'sent',
            'is_imported' => false,
            'sent_at' => now(),
            'metadata' => ['unsafe' => 'do-not-log'],
        ], $overrides));
    }

    public function test_history_import_audit_logging_foundation(): void
    {
        $anchor = now();
        $owner = $this->actingAsWithPermissions(['chat.create', 'chat.conversations.create', 'chat.view', 'chat.conversations.view']);
        $peer = User::factory()->create();
        $extra = User::factory()->create();

        $direct = $this->makeConversation($owner, ['type' => 'direct']);
        $this->addParticipant($direct, $owner, ['role' => 'owner', 'can_manage' => true, 'can_invite' => true]);
        $this->addParticipant($direct, $peer, ['role' => 'member']);

        $m1 = $this->makeMessage($direct, $owner, ['created_at' => $anchor->copy()->subDays(4), 'updated_at' => $anchor->copy()->subDays(4)]);
        $m2 = $this->makeMessage($direct, $peer, ['created_at' => $anchor->copy()->subDays(2), 'updated_at' => $anchor->copy()->subDays(2)]);
        $m3 = $this->makeMessage($direct, $owner, ['created_at' => $anchor->copy()->subDay(), 'updated_at' => $anchor->copy()->subDay()]);

        MessageAttachment::query()->create([
            'message_id' => $m2->id,
            'conversation_id' => $direct->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => 'chat/private/file.pdf',
            'original_name' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => 'sha',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => ['raw_payload' => 'x'],
        ]);

        $none = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$extra->id],
            'history_import_mode' => 'none',
            'title' => 'None Mode Group',
        ])->assertCreated();
        $noneGroupId = (int) $none->json('data.id');

        $noneLog = ChatModerationLog::query()
            ->where('action', 'history.imported')
            ->where('conversation_id', $noneGroupId)
            ->latest('id')
            ->first();
        $this->assertNotNull($noneLog);
        $this->assertSame($owner->id, $noneLog->actor_id);
        $this->assertSame(0, data_get($noneLog->metadata, 'imported_messages_count'));
        $this->assertSame(0, data_get($noneLog->metadata, 'imported_attachments_count'));

        $full = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$extra->id],
            'history_import_mode' => 'full',
            'title' => 'Full Mode Group',
        ])->assertCreated();
        $fullGroupId = (int) $full->json('data.id');

        $fullLog = ChatModerationLog::query()
            ->where('action', 'history.imported')
            ->where('conversation_id', $fullGroupId)
            ->latest('id')
            ->first();
        $this->assertNotNull($fullLog);
        $this->assertSame($owner->id, $fullLog->actor_id);
        $this->assertSame($direct->id, data_get($fullLog->metadata, 'source_conversation_id'));
        $this->assertSame($fullGroupId, data_get($fullLog->metadata, 'target_conversation_id'));
        $this->assertSame('full', data_get($fullLog->metadata, 'import_mode'));
        $this->assertSame(3, data_get($fullLog->metadata, 'imported_messages_count'));
        $this->assertSame(1, data_get($fullLog->metadata, 'imported_attachments_count'));
        $this->assertArrayNotHasKey('body', $fullLog->metadata ?? []);
        $this->assertArrayNotHasKey('disk', $fullLog->metadata ?? []);
        $this->assertArrayNotHasKey('path', $fullLog->metadata ?? []);
        $this->assertArrayNotHasKey('checksum', $fullLog->metadata ?? []);

        $fromDate = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$extra->id],
            'history_import_mode' => 'from_date',
            'history_import_from_at' => $anchor->copy()->subDays(3)->toISOString(),
            'title' => 'From Date Group',
        ])->assertCreated();
        $fromDateGroupId = (int) $fromDate->json('data.id');
        $fromDateLog = ChatModerationLog::query()
            ->where('action', 'history.imported')
            ->where('conversation_id', $fromDateGroupId)
            ->latest('id')
            ->first();
        $this->assertNotNull($fromDateLog);
        $this->assertSame('from_date', data_get($fromDateLog->metadata, 'import_mode'));
        $this->assertNotNull(data_get($fromDateLog->metadata, 'from_at'));

        $fromMessage = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$extra->id],
            'history_import_mode' => 'from_message',
            'history_import_from_message_id' => $m2->id,
            'title' => 'From Message Group',
        ])->assertCreated();
        $fromMessageGroupId = (int) $fromMessage->json('data.id');
        $fromMessageLog = ChatModerationLog::query()
            ->where('action', 'history.imported')
            ->where('conversation_id', $fromMessageGroupId)
            ->latest('id')
            ->first();
        $this->assertNotNull($fromMessageLog);
        $this->assertSame('from_message', data_get($fromMessageLog->metadata, 'import_mode'));
        $this->assertSame($m2->id, data_get($fromMessageLog->metadata, 'from_message_id'));

        $this->assertSame(
            1,
            ChatModerationLog::query()
                ->where('action', 'history.imported')
                ->where('conversation_id', $fullGroupId)
                ->count()
        );

        $outsider = $this->actingAsWithPermissions(['chat.create', 'chat.conversations.create']);
        $beforeUnauthorized = ChatModerationLog::query()->where('action', 'history.imported')->count();
        $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$extra->id],
            'history_import_mode' => 'full',
            'title' => 'Unauthorized',
        ])->assertForbidden();
        $this->assertSame($beforeUnauthorized, ChatModerationLog::query()->where('action', 'history.imported')->count());
        $this->assertDatabaseMissing('chat_moderation_logs', [
            'actor_id' => $outsider->id,
            'action' => 'history.imported',
        ]);

        $messageImportedLog = ChatModerationLog::query()
            ->where('action', 'message.imported')
            ->where('actor_id', $owner->id)
            ->whereJsonContains('metadata->conversation_id', $fullGroupId)
            ->latest('id')
            ->first();
        $this->assertNotNull($messageImportedLog);
    }
}
