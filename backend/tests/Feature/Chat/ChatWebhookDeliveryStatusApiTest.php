<?php

namespace Tests\Feature\Chat;

use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookDeliveryStatusApiTest extends TestCase
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

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Webhook Delivery Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
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
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => false,
            'can_remove' => false,
            'can_manage' => false,
            'can_moderate' => false,
            'joined_at' => now(),
        ]);
    }

    public function test_admin_can_view_safe_webhook_delivery_summaries(): void
    {
        $admin = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.webhooks.view',
        ]);

        $conversation = $this->makeConversation($admin);
        $this->addParticipant($conversation, $admin);

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Orders webhook',
            'url' => 'https://example.test/hooks/orders',
            'secret' => Str::random(40),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $admin->id,
            'metadata' => ['token_hash' => 'hash-value'],
        ]);

        ChatWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'conversation_id' => $conversation->id,
            'event' => 'message.created',
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => ['sensitive' => 'must-not-leak'],
            'signature' => 'v1=signature',
            'status' => 'failed',
            'attempts' => 2,
            'next_retry_at' => now()->addMinute(),
            'response_status' => 500,
            'response_body' => ['secret' => 'must-not-leak'],
            'error_message' => 'Webhook timeout',
            'metadata' => ['unsafe' => 'must-not-leak'],
        ]);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/webhook-deliveries")
            ->assertOk();

        $response->assertJsonPath('data.0.event_type', 'message.created');
        $response->assertJsonPath('data.0.status', 'failed');
        $response->assertJsonPath('data.0.attempts', 2);
        $response->assertJsonPath('data.0.last_status_code', 500);
        $response->assertJsonPath('data.0.endpoint_name', 'Orders webhook');
        $response->assertJsonPath('data.0.endpoint_url', 'https://example.test/hooks/orders');
        $response->assertJsonMissingPath('data.0.signature');
        $response->assertJsonMissingPath('data.0.payload');
        $response->assertJsonMissingPath('data.0.response_body');
        $response->assertJsonMissingPath('data.0.secret');
        $response->assertJsonMissingPath('data.0.token_hash');
    }

    public function test_user_without_webhook_permission_cannot_view_delivery_summaries(): void
    {
        $user = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/webhook-deliveries")
            ->assertForbidden();
    }
}
