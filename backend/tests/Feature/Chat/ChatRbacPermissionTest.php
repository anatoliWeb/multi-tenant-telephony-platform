<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatRbacPermissionTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(?User $owner = null): Conversation
    {
        $owner ??= User::factory()->create();

        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'RBAC',
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

    private function addParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        return ConversationParticipant::query()->create([
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
        ]);
    }

    public function test_chat_permission_middleware_and_rbac_behaviour(): void
    {
        $conversation = $this->makeConversation();

        // 1) guest on protected chat route gets 401
        $this->getJson('/api/v1/chat/conversations')->assertStatus(401);

        // 2) authenticated user without permission gets 403
        $noPermUser = $this->actingAsWithPermissions([]);
        $this->addParticipant($conversation, $noPermUser);
        $this->getJson('/api/v1/chat/conversations')->assertStatus(403);

        // 3) user with chat.view can list conversations
        $chatViewUser = $this->actingAsWithPermissions(['chat.view']);
        $this->addParticipant($conversation, $chatViewUser);
        $this->getJson('/api/v1/chat/conversations')->assertStatus(200);

        // 4) OR permissions work: chat.conversations.view also allows access
        $chatConversationsViewUser = $this->actingAsWithPermissions(['chat.conversations.view']);
        $this->addParticipant($conversation, $chatConversationsViewUser);
        $this->getJson('/api/v1/chat/conversations')->assertStatus(200);

        // 5) user without chat.send cannot send message
        $noSendUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $noSendUser);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'forbidden',
        ])->assertStatus(403);

        // 6) user with chat.send can send message
        $sender = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $sender);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'allowed',
        ])->assertCreated();

        // 7) user without admin/webhooks view metadata cannot access admin monitoring delivery route
        $nonAdmin = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $nonAdmin);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/webhook-deliveries")
            ->assertStatus(403);

        // 8) user with chat.admin.view_metadata can access route
        $adminMetadataUser = $this->actingAsWithPermissions(['chat.admin.view_metadata', 'chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $adminMetadataUser);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/webhook-deliveries")
            ->assertStatus(200);

        // 9) user without chat.webhooks.manage cannot create webhook endpoint
        $webhookDeniedUser = $this->actingAsWithPermissions(['chat.view']);
        $denied = $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Denied',
            'url' => 'https://example.test/webhook',
            'events' => ['message.created'],
            'is_active' => true,
        ]);
        $denied->assertStatus(403);

        // 10) user with chat.webhooks.manage can create endpoint
        $webhookManager = $this->actingAsWithPermissions(['chat.webhooks.manage']);
        $allowed = $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Allowed',
            'url' => 'https://example.test/webhook',
            'events' => ['message.created'],
            'is_active' => true,
        ]);
        $allowed->assertStatus(201);

        // 11) response does not include sensitive debug details
        $content = (string) $denied->getContent();
        $this->assertStringNotContainsString('trace', $content);
        $this->assertStringNotContainsString('exception', $content);
    }
}


