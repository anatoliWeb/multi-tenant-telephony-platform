<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAttachmentAuditLoggingTest extends TestCase
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
            'title' => 'Attachment audit',
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

    private function makeMessage(Conversation $conversation, User $sender): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'attachment message',
            'status' => 'sent',
            'is_imported' => false,
            'sent_at' => now(),
            'metadata' => null,
        ]);
    }

    public function test_upload_attachment_writes_attachment_uploaded_log_with_safe_metadata(): void
    {
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.allowed_mimes', ['image/png']);
        config()->set('chat.attachments.max_size_kb', 128);

        $user = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'chat.attachments.upload',
        ]);
        $conversation = $this->makeConversation(['owner' => $user]);
        $this->addParticipant($conversation, $user, ['role' => 'owner', 'can_attach' => true]);
        $message = $this->makeMessage($conversation, $user);

        $response = $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('proof.png', 10, 'image/png'),
        ])->assertCreated();

        $attachmentId = (int) $response->json('data.id');
        $log = ChatModerationLog::query()
            ->where('action', 'attachment.uploaded')
            ->whereJsonContains('metadata->attachment_id', $attachmentId)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($message->id, $log->message_id);

        $metadata = $log->metadata ?? [];
        $this->assertSame('image', $metadata['file_type'] ?? null);
        $this->assertSame('png', $metadata['original_extension'] ?? null);
        $this->assertArrayHasKey('file_size', $metadata);
        $this->assertArrayNotHasKey('disk', $metadata);
        $this->assertArrayNotHasKey('path', $metadata);
        $this->assertArrayNotHasKey('checksum', $metadata);
        $this->assertArrayNotHasKey('token', $metadata);
        $this->assertArrayNotHasKey('secret', $metadata);
        $this->assertSame(1, ChatModerationLog::query()
            ->where('action', 'attachment.uploaded')
            ->whereJsonContains('metadata->attachment_id', $attachmentId)
            ->count());
    }

    public function test_delete_attachment_writes_attachment_deleted_log(): void
    {
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.allowed_mimes', ['text/plain']);
        config()->set('chat.attachments.max_size_kb', 128);

        $user = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'chat.attachments.upload',
            'chat.attachments.delete',
        ]);
        $conversation = $this->makeConversation(['owner' => $user]);
        $this->addParticipant($conversation, $user, ['role' => 'owner', 'can_attach' => true]);
        $message = $this->makeMessage($conversation, $user);

        $attachmentId = (int) $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.txt', 4, 'text/plain'),
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/chat/attachments/{$attachmentId}")
            ->assertOk();

        $log = ChatModerationLog::query()
            ->where('action', 'attachment.deleted')
            ->whereJsonContains('metadata->attachment_id', $attachmentId)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($message->id, $log->message_id);
        $this->assertArrayNotHasKey('path', $log->metadata ?? []);
        $this->assertArrayNotHasKey('checksum', $log->metadata ?? []);
    }

    public function test_unauthorized_upload_or_delete_does_not_create_attachment_audit_log(): void
    {
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.allowed_mimes', ['image/png']);
        config()->set('chat.attachments.max_size_kb', 128);

        $owner = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_attach' => true]);
        $message = $this->makeMessage($conversation, $owner);

        $outsider = $this->actingAsWithPermissions(['chat.attachments.upload', 'chat.attachments.delete']);
        $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('proof.png', 10, 'image/png'),
        ])->assertForbidden();

        $attachment = MessageAttachment::query()->create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => 'chat/attachments/manual.txt',
            'original_name' => 'manual.txt',
            'mime_type' => 'text/plain',
            'size' => 3,
            'checksum' => 'manual',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => null,
        ]);

        $this->deleteJson("/api/v1/chat/attachments/{$attachment->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'actor_id' => $outsider->id,
            'action' => 'attachment.uploaded',
        ]);
        $this->assertDatabaseMissing('chat_moderation_logs', [
            'actor_id' => $outsider->id,
            'action' => 'attachment.deleted',
        ]);
    }

    public function test_attachment_audit_metadata_sanitizer_strips_nested_sensitive_fields(): void
    {
        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);

        $sanitized = $service->sanitizeMetadata([
            'file_type' => 'image',
            'nested' => [
                'path' => '/tmp/file.png',
                'checksum' => 'abc',
                'ok' => 'yes',
            ],
            'signature' => 'remove-me',
        ]);

        $this->assertSame('image', $sanitized['file_type'] ?? null);
        $this->assertSame('yes', $sanitized['nested']['ok'] ?? null);
        $this->assertArrayNotHasKey('path', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('checksum', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('signature', $sanitized);
    }
}

