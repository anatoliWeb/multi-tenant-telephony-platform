<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatDirectToGroupHistoryImportTest extends TestCase
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
            'type' => 'direct',
            'visibility' => 'private',
            'title' => null,
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

    private function makeMessage(Conversation $conversation, User $sender, array $overrides = []): Message
    {
        $payload = array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'message',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => ['sensitive' => 'secret'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        $createdAt = $payload['created_at'];
        $updatedAt = $payload['updated_at'];
        unset($payload['created_at'], $payload['updated_at']);

        $message = Message::query()->create($payload);
        $message->timestamps = false;
        $message->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ])->save();
        $message->timestamps = true;

        return $message->fresh();
    }

    public function test_direct_to_group_history_import_foundation(): void
    {
        $anchor = now();

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $third = User::factory()->create();

        $direct = $this->makeConversation([
            'type' => 'direct',
            'visibility' => 'private',
            'owner' => $owner,
        ]);
        $this->addParticipant($direct, $owner, ['role' => 'owner']);
        $this->addParticipant($direct, $other, ['role' => 'member']);

        $m1 = $this->makeMessage($direct, $owner, ['body' => 'm1', 'created_at' => $anchor->copy()->subDays(4), 'updated_at' => $anchor->copy()->subDays(4)]);
        $m2 = $this->makeMessage($direct, $other, ['body' => 'm2', 'created_at' => $anchor->copy()->subDays(2), 'updated_at' => $anchor->copy()->subDays(2)]);
        $m3 = $this->makeMessage($direct, $owner, ['body' => 'm3', 'created_at' => $anchor->copy()->subDay(), 'updated_at' => $anchor->copy()->subDay()]);
        $deleted = $this->makeMessage($direct, $owner, [
            'body' => 'deleted',
            'status' => 'deleted',
            'deleted_at' => $anchor->copy()->subHours(12),
            'created_at' => $anchor->copy()->subHours(12),
            'updated_at' => $anchor->copy()->subHours(12),
        ]);

        $attachment = MessageAttachment::query()->create([
            'message_id' => $m2->id,
            'conversation_id' => $direct->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => 'chat/demo/file.png',
            'original_name' => 'file.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'checksum' => 'abc123',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => ['unsafe' => 'x'],
        ]);

        $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'history_import_mode' => 'none',
        ])->assertUnauthorized();

        $nonParticipant = $this->actingAsWithPermissions(['chat.create', 'chat.conversations.create']);
        $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'history_import_mode' => 'none',
        ])->assertForbidden();

        $actor = $this->actingAsWithPermissions(['chat.create', 'chat.conversations.create', 'chat.view', 'chat.conversations.view']);
        // align actor with direct owner
        $this->prepareTenantChatUser($owner, ['chat.create', 'chat.conversations.create', 'chat.view', 'chat.conversations.view']);
        Sanctum::actingAs($owner);

        $nonDirect = $this->makeConversation(['type' => 'group', 'visibility' => 'private', 'owner' => $owner]);
        $this->addParticipant($nonDirect, $owner, ['role' => 'owner']);
        $this->postJson("/api/v1/chat/conversations/{$nonDirect->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'history_import_mode' => 'none',
        ])->assertStatus(422);

        $noneResponse = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'title' => 'Group none',
            'history_import_mode' => 'none',
        ])->assertCreated();

        $groupNoneId = (int) $noneResponse->json('data.id');
        $groupNone = Conversation::query()->findOrFail($groupNoneId);
        $this->assertSame('group', $groupNone->type);
        $this->assertSame('private', $groupNone->visibility);
        $this->assertSame($direct->id, $groupNone->created_from_conversation_id);
        $this->assertSame('none', $groupNone->history_import_mode);
        $this->assertSame(0, Message::query()->where('conversation_id', $groupNoneId)->count());

        $this->assertSame('direct', $direct->fresh()->type);
        $this->assertSame(2, ConversationParticipant::query()->where('conversation_id', $direct->id)->where('status', 'active')->count());

        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $groupNoneId, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $groupNoneId, 'user_id' => $other->id, 'role' => 'member']);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $groupNoneId, 'user_id' => $third->id, 'role' => 'member']);

        $fullResponse = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'title' => 'Group full',
            'history_import_mode' => 'full',
        ])->assertCreated();
        $groupFullId = (int) $fullResponse->json('data.id');

        $fullImported = Message::query()->where('conversation_id', $groupFullId)->orderBy('id')->get();
        $this->assertCount(3, $fullImported);
        $this->assertFalse($fullImported->contains(fn (Message $m) => $m->body === 'deleted'));
        $this->assertTrue($fullImported->every(fn (Message $m) => $m->is_imported === true));
        $this->assertTrue($fullImported->every(fn (Message $m) => (int) $m->imported_from_conversation_id === $direct->id));
        $this->assertTrue($fullImported->contains(fn (Message $m) => (int) $m->imported_from_message_id === $m2->id));

        $importedFromM2 = $fullImported->first(fn (Message $m) => (int) $m->imported_from_message_id === $m2->id);
        $this->assertNotNull($importedFromM2);
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $importedFromM2->id,
            'conversation_id' => $groupFullId,
            'copied_from_attachment_id' => $attachment->id,
            'is_imported' => true,
        ]);

        $fromDateResponse = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'title' => 'Group from date',
            'history_import_mode' => 'from_date',
            'history_import_from_at' => $anchor->copy()->subDays(3)->toISOString(),
        ])->assertCreated();
        $groupFromDateId = (int) $fromDateResponse->json('data.id');
        $fromDateImported = Message::query()->where('conversation_id', $groupFromDateId)->pluck('imported_from_message_id')->all();
        $this->assertNotContains($m1->id, $fromDateImported);
        $this->assertContains($m2->id, $fromDateImported);
        $this->assertContains($m3->id, $fromDateImported);

        $fromMessageResponse = $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'title' => 'Group from message',
            'history_import_mode' => 'from_message',
            'history_import_from_message_id' => $m2->id,
        ])->assertCreated();
        $groupFromMessageId = (int) $fromMessageResponse->json('data.id');
        $fromMessageImported = Message::query()->where('conversation_id', $groupFromMessageId)->pluck('imported_from_message_id')->all();
        $this->assertNotContains($m1->id, $fromMessageImported);
        $this->assertContains($m2->id, $fromMessageImported);
        $this->assertContains($m3->id, $fromMessageImported);

        $outsiderMessage = $this->makeMessage($nonDirect, $owner, ['body' => 'outside']);
        $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'history_import_mode' => 'from_message',
            'history_import_from_message_id' => $outsiderMessage->id,
        ])->assertStatus(422);

        $this->postJson("/api/v1/chat/conversations/{$direct->id}/create-private-group", [
            'participant_ids' => [$third->id],
            'history_import_mode' => 'wrong',
        ])->assertStatus(422);

        $thirdWithView = User::query()->findOrFail($third->id);
        $this->prepareTenantChatUser($thirdWithView, ['chat.view', 'chat.conversations.view']);
        Sanctum::actingAs($thirdWithView);
        $this->getJson("/api/v1/chat/conversations/{$direct->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/chat/conversations/{$groupFullId}/messages")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        Sanctum::actingAs($owner);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'conversation_id' => $groupFullId,
            'actor_id' => $owner->id,
            'action' => 'history.imported',
        ]);
    }
}

