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
use Tests\TestCase;

class ChatMessageSearchApiTest extends TestCase
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
            'title' => 'Search chat',
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
            'history_visible_from_message_id' => null,
            'history_visible_from_at' => null,
            'history_visible_until_message_id' => null,
            'history_visible_until_at' => null,
            'joined_at' => now(),
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, User $sender, string $body, array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => ['secret' => 'must-not-expose'],
        ], $overrides));
    }

    public function test_chat_message_search_api_foundation(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $anotherSender = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $this->addParticipant($conversation, $owner, ['role' => 'owner']);
        $participantRow = $this->addParticipant($conversation, $participant, ['role' => 'member']);
        $this->addParticipant($conversation, $anotherSender, ['role' => 'member']);

        $mAlpha = $this->makeMessage($conversation, $owner, 'alpha keyword');
        $mBeta = $this->makeMessage($conversation, $participant, 'beta keyword');
        $mFile = $this->makeMessage($conversation, $anotherSender, 'file message', ['type' => 'file']);
        $mSystem = $this->makeMessage($conversation, $owner, 'system ping', ['type' => 'system']);
        $mDeleted = $this->makeMessage($conversation, $owner, 'deleted keyword', [
            'status' => 'deleted',
            'deleted_at' => now(),
        ]);

        MessageAttachment::query()->create([
            'message_id' => $mFile->id,
            'conversation_id' => $conversation->id,
            'uploaded_by' => $anotherSender->id,
            'disk' => 'local',
            'path' => 'chat/attachments/search-file.txt',
            'original_name' => 'search-file.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'checksum' => 'abc',
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => ['preview' => ['category' => 'text']],
        ]);

        $directOwner = User::factory()->create();
        $directPeer = User::factory()->create();
        $newGroupUser = User::factory()->create();
        $direct = $this->makeConversation([
            'type' => 'direct',
            'owner' => $directOwner,
            'visibility' => 'private',
        ]);
        $this->addParticipant($direct, $directOwner, ['role' => 'owner']);
        $this->addParticipant($direct, $directPeer);
        $sourceDirectMessage = $this->makeMessage($direct, $directOwner, 'direct-only source');

        $targetGroup = $this->makeConversation([
            'type' => 'group',
            'owner' => $directOwner,
            'created_from_conversation_id' => $direct->id,
            'history_import_mode' => 'full',
        ]);
        $this->addParticipant($targetGroup, $directOwner, ['role' => 'owner']);
        $this->addParticipant($targetGroup, $directPeer);
        $this->addParticipant($targetGroup, $newGroupUser);
        $imported = $this->makeMessage($targetGroup, $directOwner, 'imported visible body', [
            'is_imported' => true,
            'imported_from_conversation_id' => $direct->id,
            'imported_from_message_id' => $sourceDirectMessage->id,
        ]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=alpha")
            ->assertUnauthorized();

        $searcher = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $searcher);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=alpha")
            ->assertOk()
            ->assertJsonFragment(['id' => $mAlpha->id]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=a")
            ->assertStatus(422);

        $hiddenUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $hiddenConversation = $this->makeConversation();
        $this->addParticipant($hiddenConversation, $hiddenUser, ['access_state' => 'hidden']);
        $this->getJson("/api/v1/chat/conversations/{$hiddenConversation->id}/messages/search?q=hello")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $blockedUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $blockedConversation = $this->makeConversation();
        $this->addParticipant($blockedConversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->makeMessage($blockedConversation, $owner, 'blocked should not see');
        $this->getJson("/api/v1/chat/conversations/{$blockedConversation->id}/messages/search?q=blocked")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($searcher);
        $searcher->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);
        $searcherConversation = Conversation::query()->findOrFail($conversation->id);
        if (! $searcherConversation->participants()->where('user_id', $searcher->id)->exists()) {
            $this->addParticipant($searcherConversation, $searcher);
        }

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=keyword")
            ->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mAlpha->id, $ids);
        $this->assertContains($mBeta->id, $ids);
        $this->assertNotContains($mDeleted->id, $ids);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?sender_id={$participant->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $mBeta->id])
            ->assertJsonMissing(['id' => $mAlpha->id]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?type=file")
            ->assertOk()
            ->assertJsonFragment(['id' => $mFile->id])
            ->assertJsonMissing(['id' => $mSystem->id]);

        $from = now()->subMinute()->toISOString();
        $to = now()->addMinute()->toISOString();
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonFragment(['id' => $mAlpha->id]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?has_attachments=1")
            ->assertOk()
            ->assertJsonFragment(['id' => $mFile->id])
            ->assertJsonMissing(['id' => $mAlpha->id]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?imported=0")
            ->assertOk()
            ->assertJsonMissing(['id' => $imported->id]);

        $data = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=alpha")
            ->assertOk()
            ->json('data');
        $first = collect($data)->first();
        $this->assertArrayNotHasKey('metadata', $first);

        $newGroupUser->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);
        Sanctum::actingAs($newGroupUser);
        $this->getJson("/api/v1/chat/conversations/{$direct->id}/messages/search?q=direct-only")
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/chat/conversations/{$targetGroup->id}/messages/search?q=imported")
            ->assertOk()
            ->assertJsonFragment(['id' => $imported->id]);

        $participantRow->history_visible_from_at = now()->addHour();
        $participantRow->history_visible_until_at = now()->addHours(2);
        $participantRow->save();
        Sanctum::actingAs($participant);
        $participant->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages/search?q=keyword")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

