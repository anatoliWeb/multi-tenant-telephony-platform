<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatConversationQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    public function test_chat_query_optimization_indexes_exist(): void
    {
        $messageIndexes = collect(DB::select("SHOW INDEX FROM messages"))
            ->pluck('Key_name')
            ->all();
        $deliveryIndexes = collect(DB::select("SHOW INDEX FROM chat_webhook_deliveries"))
            ->pluck('Key_name')
            ->all();

        $this->assertContains('messages_conversation_id_id_idx', $messageIndexes);
        $this->assertContains('chat_webhook_deliveries_conversation_id_id_idx', $deliveryIndexes);
    }

    public function test_batched_unread_counts_match_single_conversation_logic(): void
    {
        $user = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.send']);
        $other = User::factory()->create();

        $conversationA = $this->makeConversation($user);
        $conversationB = $this->makeConversation($user);
        $this->addParticipant($conversationA, $user, ['last_read_message_id' => null, 'last_read_at' => null]);
        $this->addParticipant($conversationA, $other);
        $this->addParticipant($conversationB, $user, ['last_read_message_id' => null, 'last_read_at' => null]);
        $this->addParticipant($conversationB, $other);

        $ownA = $this->makeMessage($conversationA, $user);
        $incomingA1 = $this->makeMessage($conversationA, $other);
        $incomingA2 = $this->makeMessage($conversationA, $other);
        $incomingB1 = $this->makeMessage($conversationB, $other);

        ConversationParticipant::query()
            ->where('conversation_id', $conversationA->id)
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => $incomingA1->id]);
        ConversationParticipant::query()
            ->where('conversation_id', $conversationB->id)
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => null]);

        /** @var ChatConversationQueryService $service */
        $service = app(ChatConversationQueryService::class);
        $batched = $service->unreadCountsForConversations($user, [$conversationA->id, $conversationB->id]);

        $this->assertSame($service->unreadCountFor($user, $conversationA), (int) ($batched[$conversationA->id] ?? 0));
        $this->assertSame($service->unreadCountFor($user, $conversationB), (int) ($batched[$conversationB->id] ?? 0));
        $this->assertNotSame($ownA->id, $incomingA2->id);
        $this->assertNotSame($incomingB1->sender_id, $user->id);
    }

    public function test_conversation_list_keeps_response_shape_and_permission_behavior(): void
    {
        $this->getJson('/api/v1/chat/conversations')->assertUnauthorized();

        $user = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);
        $this->makeMessage($conversation, User::factory()->create());

        $response = $this->getJson('/api/v1/chat/conversations?per_page=10')
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'uuid',
                    'type',
                    'visibility',
                    'title',
                    'status',
                    'source',
                    'last_message_at',
                    'unread_count',
                    'participants_count',
                    'current_user_access',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Query optimization test',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'metadata' => null,
        ]);
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): void
    {
        ConversationParticipant::query()->create(array_merge([
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

    private function makeMessage(Conversation $conversation, User $sender): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'hello',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => null,
        ]);
    }
}
