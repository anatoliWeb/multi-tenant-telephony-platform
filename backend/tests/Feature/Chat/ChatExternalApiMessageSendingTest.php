<?php

namespace Tests\Feature\Chat;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ExternalMessageMapping;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatExternalApiMessageSendingTest extends TestCase
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
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'External API Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
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
            'block_display_mode' => null,
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => false,
            'can_remove' => false,
            'can_manage' => false,
            'can_moderate' => false,
            'joined_at' => now(),
        ], $overrides));
    }

    private function validPayload(Conversation $conversation, array $overrides = []): array
    {
        return array_merge([
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm-hub',
            'external_message_id' => 'ext-msg-001',
            'body' => 'External hello',
            'type' => 'text',
            'metadata' => [
                'source' => 'crm',
                'customer_ref' => 'C-42',
                'user_agent' => 'SHOULD_NOT_STORE',
                'ip_address' => '127.0.0.1',
                'token' => 'SHOULD_NOT_STORE',
            ],
            'idempotency_key' => 'idem-001',
        ], $overrides);
    }

    public function test_external_api_message_sending_foundation(): void
    {
        Bus::fake();

        $guestConversation = $this->makeConversation(User::factory()->create());
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($guestConversation))
            ->assertUnauthorized();

        $noPermissionUser = $this->actingAsWithPermissions(['chat.view']);
        $noPermissionConversation = $this->makeConversation($noPermissionUser);
        $this->addParticipant($noPermissionConversation, $noPermissionUser);
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($noPermissionConversation, [
            'external_message_id' => 'no-perm-msg',
        ]))->assertForbidden();

        $sender = $this->actingAsWithPermissions([
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender);

        ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'External Callback',
            'url' => 'https://example.test/external-callback',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $sender->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);

        $response = $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation))
            ->assertCreated();

        $this->assertFalse((bool) $response->json('meta.idempotent'));
        $messageId = (int) $response->json('data.id');
        $message = Message::query()->findOrFail($messageId);
        $this->assertSame($conversation->id, (int) $message->conversation_id);
        $this->assertSame('ext-msg-001', (string) $message->external_id);
        $this->assertArrayNotHasKey('user_agent', (array) $message->metadata['external_metadata']);
        $this->assertArrayNotHasKey('ip_address', (array) $message->metadata['external_metadata']);
        $this->assertArrayNotHasKey('token', (array) $message->metadata['external_metadata']);

        $mapping = ExternalMessageMapping::query()
            ->where('provider', 'crm-hub')
            ->where('external_id', 'ext-msg-001')
            ->first();
        $this->assertNotNull($mapping);
        $this->assertSame($message->id, (int) $mapping->message_id);
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.created')->where('message_id', $message->id)->count());
        Bus::assertDispatched(DeliverChatWebhookJob::class);

        $duplicate = $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation))
            ->assertOk();
        $this->assertTrue((bool) $duplicate->json('meta.idempotent'));
        $this->assertSame($message->id, (int) $duplicate->json('data.id'));
        $this->assertSame(1, Message::query()->where('external_id', 'ext-msg-001')->count());

        $outsider = User::factory()->create();
        $blockedConversation = $this->makeConversation($outsider);
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($blockedConversation, [
            'external_message_id' => 'outsider-1',
        ]))->assertForbidden();

        $archivedConversation = $this->makeConversation($sender, ['status' => 'archived']);
        $this->addParticipant($archivedConversation, $sender);
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($archivedConversation, [
            'external_message_id' => 'archived-1',
        ]))->assertUnprocessable();

        $closedConversation = $this->makeConversation($sender, ['status' => 'closed']);
        $this->addParticipant($closedConversation, $sender);
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($closedConversation, [
            'external_message_id' => 'closed-1',
        ]))->assertUnprocessable();

        $deletedConversation = $this->makeConversation($sender, ['status' => 'deleted']);
        $this->addParticipant($deletedConversation, $sender);
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($deletedConversation, [
            'external_message_id' => 'deleted-1',
        ]))->assertUnprocessable();

        $this->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $conversation->id,
            'external_provider' => 'x',
            'external_message_id' => 'invalid-type',
            'body' => 'payload',
            'type' => 'invalid',
        ])->assertUnprocessable();
    }

    public function test_external_messages_route_is_rate_limited(): void
    {
        config()->set('chat.external_api.rate_limit.enabled', true);
        config()->set('chat.external_api.rate_limit.max_attempts', 2);
        config()->set('chat.external_api.rate_limit.decay_seconds', 60);

        $sender = $this->actingAsWithPermissions([
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender);

        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, [
            'external_message_id' => 'rate-limit-1',
            'idempotency_key' => 'rate-limit-idem-1',
        ]))->assertCreated();
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, [
            'external_message_id' => 'rate-limit-2',
            'idempotency_key' => 'rate-limit-idem-2',
        ]))->assertCreated();
        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, [
            'external_message_id' => 'rate-limit-3',
            'idempotency_key' => 'rate-limit-idem-3',
        ]))->assertStatus(429);
    }

    public function test_external_messages_require_explicit_or_unambiguous_tenant_context(): void
    {
        $sender = $this->actingAsWithPermissions([
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);

        $tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => TenantStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
        ]);
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => TenantStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
        ]);

        foreach ([$tenantA, $tenantB] as $tenant) {
            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $sender->id,
                'status' => TenantMembershipStatus::Active,
                'accepted_at' => now(),
                'activated_at' => now(),
            ]);
        }

        $conversation = $this->makeConversation($sender, [
            'tenant_id' => $tenantA->id,
        ]);
        $this->addParticipant($conversation, $sender, [
            'tenant_id' => $tenantA->id,
        ]);

        $this->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, [
            'external_message_id' => 'ambiguous-tenant',
            'idempotency_key' => 'ambiguous-tenant',
        ]))->assertForbidden();

        $this->withHeader('X-Tenant-ID', $tenantA->id)
            ->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, [
                'external_message_id' => 'explicit-tenant',
                'idempotency_key' => 'explicit-tenant',
            ]))->assertCreated();
    }
}
