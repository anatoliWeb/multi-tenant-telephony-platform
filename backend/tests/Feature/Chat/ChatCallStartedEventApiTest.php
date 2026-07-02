<?php

namespace Tests\Feature\Chat;

use App\Enums\Extensions\ExtensionStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatCallStartedEventApiTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeDirectConversation(User $owner, User $target): Conversation
    {
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->chatTenant()->id,
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
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'access_state' => 'full',
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => true,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => true,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $target->id,
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

        return $conversation;
    }

    public function test_direct_chat_call_started_event_is_persisted_and_visible(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);

        $target = $this->prepareTenantChatUser(User::factory()->create(), []);
        Extension::factory()->create([
            'tenant_id' => $this->chatTenant()->id,
            'assigned_user_id' => $target->id,
            'number' => '1002',
            'status' => ExtensionStatus::Active->value,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $conversation = $this->makeDirectConversation($actor, $target);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/call-started")
            ->assertCreated();

        $response->assertJsonPath('data.type', 'system')
            ->assertJsonPath('data.body', 'Audio call started with '.$target->name.' (1002)')
            ->assertJsonPath('data.metadata.event', 'call_started')
            ->assertJsonPath('data.metadata.target_user_id', $target->id)
            ->assertJsonPath('data.metadata.target_extension', '1002')
            ->assertJsonPath('data.metadata.target_display_name', $target->name)
            ->assertJsonMissingPath('data.metadata.authorization_username')
            ->assertJsonMissingPath('data.metadata.password')
            ->assertJsonMissingPath('data.metadata.websocket_url');

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'system',
                'conversation_id' => $conversation->id,
            ])
            ->assertJsonPath('data.0.metadata.event', 'call_started');
    }

    public function test_direct_chat_call_started_event_requires_conversation_membership(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);
        $otherUser = $this->prepareTenantChatUser(User::factory()->create(), []);
        $conversation = $this->makeDirectConversation($actor, $otherUser);

        $outsider = $this->prepareTenantChatUser(User::factory()->create(), [
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);

        Sanctum::actingAs($outsider);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/call-started")
            ->assertForbidden();
    }

    public function test_direct_chat_call_started_event_rejects_cross_tenant_access(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);
        $target = $this->prepareTenantChatUser(User::factory()->create(), []);
        Extension::factory()->create([
            'tenant_id' => $this->chatTenant()->id,
            'assigned_user_id' => $target->id,
            'number' => '1002',
            'status' => ExtensionStatus::Active->value,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $conversation = $this->makeDirectConversation($actor, $target);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
            'suspended_at' => null,
        ]);
        $crossTenantUser = $this->prepareTenantChatUser(User::factory()->create(), [
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);
        $this->activateChatTenant($otherTenant);
        Sanctum::actingAs($crossTenantUser);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/call-started")
            ->assertNotFound();
    }

    public function test_direct_chat_call_started_event_rejects_group_conversations(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
            'call_control.view',
        ]);
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->chatTenant()->id,
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Group',
            'description' => null,
            'owner_id' => $actor->id,
            'created_by' => $actor->id,
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
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'role' => 'owner',
            'status' => 'active',
            'access_state' => 'full',
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => true,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => true,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ]);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/call-started")
            ->assertStatus(422);
    }
}
