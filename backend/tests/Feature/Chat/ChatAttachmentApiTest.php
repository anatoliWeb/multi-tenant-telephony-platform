<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAttachmentApiTest extends TestCase
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
            'title' => 'Attachment chat',
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
            'body' => 'body',
            'status' => 'sent',
            'is_imported' => false,
            'sent_at' => now(),
            'metadata' => null,
        ]);
    }

    public function test_chat_attachment_api_foundation(): void
    {
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.max_size_kb', 128);
        config()->set('chat.attachments.allowed_mimes', [
            'image/png',
            'application/pdf',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'audio/mpeg',
        ]);

        $owner = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($conversation, $owner, ['role' => 'owner']);
        $message = $this->makeMessage($conversation, $owner);

        $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('a.png', 10, 'image/png'),
        ])->assertUnauthorized();

        $user = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'chat.attachments.upload',
            'chat.attachments.download',
            'chat.attachments.delete',
            'chat.delete',
        ]);
        $this->addParticipant($conversation, $user, ['can_attach' => true, 'can_send' => true]);
        $userMessage = $this->makeMessage($conversation, $user);

        $upload = $this->postJson("/api/v1/chat/messages/{$userMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('proof.png', 10, 'image/png'),
        ])->assertCreated();

        $attachmentId = (int) $upload->json('data.id');
        $this->assertDatabaseHas('message_attachments', [
            'id' => $attachmentId,
            'message_id' => $userMessage->id,
            'status' => 'active',
            'is_imported' => false,
        ]);

        $upload->assertJsonMissingPath('data.path');
        $upload->assertJsonMissingPath('data.disk');
        $upload->assertJsonMissingPath('data.checksum');
        $this->assertSame('active', $upload->json('data.status'));

        $this->postJson("/api/v1/chat/messages/{$userMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('note.docx', 8, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertCreated();

        $this->postJson("/api/v1/chat/messages/{$userMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('voice.mp3', 12, 'audio/mpeg'),
        ])->assertCreated();

        $readOnlyUser = $this->actingAsWithPermissions(['chat.attachments.upload']);
        $readOnlyConversation = $this->makeConversation();
        $this->addParticipant($readOnlyConversation, $readOnlyUser, ['access_state' => 'read_only']);
        $readOnlyMessage = $this->makeMessage($readOnlyConversation, $owner);
        $this->postJson("/api/v1/chat/messages/{$readOnlyMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $noAttachUser = $this->actingAsWithPermissions(['chat.attachments.upload']);
        $noAttachConversation = $this->makeConversation();
        $this->addParticipant($noAttachConversation, $noAttachUser, ['can_attach' => false]);
        $noAttachMessage = $this->makeMessage($noAttachConversation, $owner);
        $this->postJson("/api/v1/chat/messages/{$noAttachMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $hiddenUser = $this->actingAsWithPermissions(['chat.attachments.upload']);
        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $hiddenUser, ['access_state' => 'hidden']);
        $hiddenMessage = $this->makeMessage($hiddenConversation, $owner);
        $this->postJson("/api/v1/chat/messages/{$hiddenMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $blockedUser = $this->actingAsWithPermissions(['chat.attachments.upload']);
        $blockedConversation = $this->makeConversation();
        $this->addParticipant($blockedConversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $blockedMessage = $this->makeMessage($blockedConversation, $owner);
        $this->postJson("/api/v1/chat/messages/{$blockedMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/chat/messages/{$userMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('bad.exe', 5, 'application/x-msdownload'),
        ])->assertStatus(422);

        $this->postJson("/api/v1/chat/messages/{$userMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('big.pdf', 256, 'application/pdf'),
        ])->assertStatus(422);

        $download = $this->get("/api/v1/chat/attachments/{$attachmentId}/download");
        $download->assertOk();

        $outsider = $this->actingAsWithPermissions(['chat.attachments.download']);
        $this->get("/api/v1/chat/attachments/{$attachmentId}/download")->assertForbidden();

        $hiddenDownloadUser = $this->actingAsWithPermissions(['chat.attachments.download']);
        $hiddenDownloadConversation = $this->makeConversation();
        $this->addParticipant($hiddenDownloadConversation, $hiddenDownloadUser, ['access_state' => 'hidden']);
        $hiddenDownloadMessage = $this->makeMessage($hiddenDownloadConversation, $owner);
        $hiddenAttachment = MessageAttachment::query()->create([
            'message_id' => $hiddenDownloadMessage->id,
            'conversation_id' => $hiddenDownloadConversation->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => 'chat/attachments/hidden.png',
            'original_name' => 'hidden.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => 'hidden',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => ['preview' => ['category' => 'image']],
        ])->id;
        Sanctum::actingAs($hiddenDownloadUser);
        $this->get("/api/v1/chat/attachments/{$hiddenAttachment}/download")->assertForbidden();

        $blockedDownloadUser = $this->actingAsWithPermissions(['chat.attachments.download']);
        $blockedDownloadConversation = $this->makeConversation();
        $this->addParticipant($blockedDownloadConversation, $blockedDownloadUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $blockedDownloadMessage = $this->makeMessage($blockedDownloadConversation, $owner);
        $blockedAttachment = MessageAttachment::query()->create([
            'message_id' => $blockedDownloadMessage->id,
            'conversation_id' => $blockedDownloadConversation->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => 'chat/attachments/blocked.png',
            'original_name' => 'blocked.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => 'blocked',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => ['preview' => ['category' => 'image']],
        ])->id;
        Sanctum::actingAs($blockedDownloadUser);
        $this->get("/api/v1/chat/attachments/{$blockedAttachment}/download")->assertForbidden();

        Sanctum::actingAs($user);
        $quarantineMessage = $this->makeMessage($conversation, $owner);
        $quarantineAttachment = $this->postJson("/api/v1/chat/messages/{$quarantineMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('q.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');
        MessageAttachment::query()->whereKey($quarantineAttachment)->update(['status' => 'quarantined']);
        Sanctum::actingAs($user);
        $this->get("/api/v1/chat/attachments/{$quarantineAttachment}/download")->assertForbidden();

        Sanctum::actingAs($user);
        $failedMessage = $this->makeMessage($conversation, $owner);
        $failedAttachment = $this->postJson("/api/v1/chat/messages/{$failedMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');
        MessageAttachment::query()->whereKey($failedAttachment)->update(['status' => 'failed']);
        Sanctum::actingAs($user);
        $this->get("/api/v1/chat/attachments/{$failedAttachment}/download")->assertForbidden();

        Sanctum::actingAs($user);
        $msgForCount = $this->makeMessage($conversation, $user);
        $this->postJson("/api/v1/chat/messages/{$msgForCount->id}/attachments", [
            'file' => UploadedFile::fake()->create('safe.txt', 4, 'text/plain'),
        ])->assertCreated();
        $messageList = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data');
        $listed = collect($messageList)->firstWhere('id', $msgForCount->id);
        $this->assertNotNull($listed);
        $this->assertArrayHasKey('attachments_count', $listed);

        $deleteMessage = $this->makeMessage($conversation, $user);
        $deleteAttachmentId = $this->postJson("/api/v1/chat/messages/{$deleteMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('delete.pdf', 5, 'application/pdf'),
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/chat/messages/{$deleteMessage->id}")->assertOk();
        $this->assertDatabaseHas('message_attachments', [
            'id' => $deleteAttachmentId,
            'status' => 'deleted',
        ]);

        $importedAttachment = MessageAttachment::query()->create([
            'message_id' => $msgForCount->id,
            'conversation_id' => $conversation->id,
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => 'chat/attachments/imported.bin',
            'original_name' => 'imported.bin',
            'mime_type' => 'text/plain',
            'size' => 7,
            'checksum' => 'hash',
            'copied_from_attachment_id' => $attachmentId,
            'is_imported' => true,
            'status' => 'active',
            'metadata' => ['preview' => ['category' => 'text'], 'unsafe' => 'secret'],
        ]);
        $safe = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk();
        $safe->assertJsonMissing(['path' => 'chat/attachments/imported.bin']);
        $safe->assertJsonMissing(['checksum' => 'hash']);

        // Optional attachment delete endpoint behavior
        $deletableMessage = $this->makeMessage($conversation, $user);
        $deletableAttachment = $this->postJson("/api/v1/chat/messages/{$deletableMessage->id}/attachments", [
            'file' => UploadedFile::fake()->create('del.txt', 2, 'text/plain'),
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/chat/attachments/{$deletableAttachment}")
            ->assertOk()
            ->assertJsonPath('data.status', 'deleted');
    }
}
