<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatExternalMessageAuditLoggingTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function makeConversation(User $owner, array $overrides = []): Conversation
    {
        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'External Audit',
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
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => false,
            'can_remove' => false,
            'can_manage' => false,
            'can_moderate' => false,
            'joined_at' => now(),
        ], $overrides));
    }

    private function makeEndpoint(User $creator, array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Incoming Endpoint',
            'url' => 'https://example.test/incoming',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => ['token_hash' => 'safe'],
        ], $overrides));
    }

    /**
     * @return array<string,mixed>
     */
    private function externalPayload(int $conversationId, array $overrides = []): array
    {
        return array_merge([
            'conversation_id' => $conversationId,
            'external_provider' => 'crm-provider',
            'external_message_id' => 'ext-audit-001',
            'body' => 'External body that must not be logged',
            'type' => 'text',
            'metadata' => [
                'source' => 'crm',
                'token' => 'do-not-store',
                'secret' => 'do-not-store',
                'authorization' => 'do-not-store',
            ],
            'idempotency_key' => 'idem-audit-001',
        ], $overrides);
    }

    /**
     * @return array<string,mixed>
     */
    private function webhookPayload(int $conversationId, array $overrides = []): array
    {
        return array_merge([
            'event' => 'message.created',
            'conversation_id' => $conversationId,
            'external_provider' => 'webhook-provider',
            'external_message_id' => 'wh-audit-001',
            'body' => 'Webhook body that must not be logged',
            'type' => 'text',
            'metadata' => [
                'source' => 'external-webhook',
                'signature' => 'do-not-store',
                'authorization' => 'do-not-store',
                'token' => 'do-not-store',
            ],
            'idempotency_key' => 'idem-wh-001',
        ], $overrides);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function postSignedWebhook(ChatWebhookEndpoint $endpoint, array $payload, string $secret)
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        /** @var ChatWebhookSigningService $signing */
        $signing = app(ChatWebhookSigningService::class);
        $signed = $signing->signPayload($payloadJson, $secret);

        $sigHeader = (string) config('chat.webhooks.signature_header', 'X-Chat-Signature');
        $tsHeader = (string) config('chat.webhooks.timestamp_header', 'X-Chat-Timestamp');

        return $this->call(
            'POST',
            '/api/v1/chat/external/webhooks/'.$endpoint->uuid,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', $sigHeader)) => $signed['signature'],
                'HTTP_'.strtoupper(str_replace('-', '_', $tsHeader)) => (string) $signed['timestamp'],
            ],
            $payloadJson
        );
    }

    public function test_external_and_webhook_message_audit_logging_foundation(): void
    {
        $actor = $this->actingAsWithPermissions([
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);
        $conversation = $this->makeConversation($actor);
        $this->addParticipant($conversation, $actor, ['role' => 'owner']);

        $create = $this->postJson('/api/v1/chat/external/messages', $this->externalPayload($conversation->id))
            ->assertCreated();
        $messageId = (int) $create->json('data.id');

        $log = ChatModerationLog::query()
            ->where('action', 'message.external_created')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame('external_api', data_get($log->metadata, 'source'));
        $this->assertSame('external_in', data_get($log->metadata, 'direction'));
        $this->assertSame('crm-provider', data_get($log->metadata, 'external_provider'));
        $this->assertSame('ext-audit-001', data_get($log->metadata, 'external_message_id'));
        $this->assertSame('idem-audit-001', data_get($log->metadata, 'idempotency_key'));
        $this->assertSame(false, data_get($log->metadata, 'idempotent'));
        $this->assertArrayNotHasKey('body', $log->metadata ?? []);
        $this->assertArrayNotHasKey('content', $log->metadata ?? []);
        $this->assertArrayNotHasKey('token', $log->metadata ?? []);
        $this->assertArrayNotHasKey('secret', $log->metadata ?? []);
        $this->assertArrayNotHasKey('signature', $log->metadata ?? []);
        $this->assertArrayNotHasKey('authorization', $log->metadata ?? []);

        $this->postJson('/api/v1/chat/external/messages', $this->externalPayload($conversation->id))
            ->assertOk();
        $this->assertSame(
            1,
            ChatModerationLog::query()
                ->where('action', 'message.external_created')
                ->where('message_id', $messageId)
                ->count()
        );

        $endpointCreator = User::factory()->create();
        $this->prepareTenantChatUser($endpointCreator, [
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);
        $webhookConversation = $this->makeConversation($endpointCreator);
        $this->addParticipant($webhookConversation, $endpointCreator, ['role' => 'owner']);
        $endpoint = $this->makeEndpoint($endpointCreator);

        $webhookResponse = $this->postSignedWebhook($endpoint, $this->webhookPayload($webhookConversation->id), $endpoint->secret)
            ->assertCreated();
        $webhookMessageId = (int) $webhookResponse->json('data.id');

        $webhookLog = ChatModerationLog::query()
            ->where('action', 'message.external_created')
            ->where('message_id', $webhookMessageId)
            ->latest('id')
            ->first();
        $this->assertNotNull($webhookLog);
        $this->assertSame($endpointCreator->id, $webhookLog->actor_id);
        $this->assertSame('incoming_webhook', data_get($webhookLog->metadata, 'source'));
        $this->assertSame('external_in', data_get($webhookLog->metadata, 'direction'));
        $this->assertSame('webhook-provider', data_get($webhookLog->metadata, 'external_provider'));
        $this->assertSame('wh-audit-001', data_get($webhookLog->metadata, 'external_message_id'));

        $beforeUnauthorized = ChatModerationLog::query()->where('action', 'message.external_created')->count();
        $outsider = $this->actingAsWithPermissions(['chat.view']);
        $this->postJson('/api/v1/chat/external/messages', $this->externalPayload($conversation->id, [
            'external_message_id' => 'ext-unauthorized',
            'idempotency_key' => 'idem-unauthorized',
        ]))->assertForbidden();
        $this->assertDatabaseMissing('chat_moderation_logs', [
            'action' => 'message.external_created',
            'actor_id' => $outsider->id,
        ]);
        $this->assertSame($beforeUnauthorized, ChatModerationLog::query()->where('action', 'message.external_created')->count());

        $beforeInvalidSignature = ChatModerationLog::query()->where('action', 'message.external_created')->count();
        $this->postSignedWebhook($endpoint, $this->webhookPayload($webhookConversation->id, [
            'external_message_id' => 'wh-invalid-signature',
            'idempotency_key' => 'idem-invalid-signature',
        ]), 'wrong-secret')->assertStatus(403);
        $this->assertSame($beforeInvalidSignature, ChatModerationLog::query()->where('action', 'message.external_created')->count());
    }
}


