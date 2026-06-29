<?php

namespace Tests\Feature\Chat;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookDeliveryService;
use App\Services\Chat\ChatWebhookSigningService;
use App\Services\Chat\ExternalChatTokenService;
use App\Services\Chat\ExternalMessageMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatExternalWebhookFoundationTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function createConversationWithMessage(User $owner): array
    {
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'External Test Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);

        $message = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'External payload body',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$conversation, $message];
    }

    public function test_chat_external_webhook_foundation(): void
    {
        /** @var ExternalChatTokenService $tokenService */
        $tokenService = app(ExternalChatTokenService::class);
        $token = $tokenService->generatePlainToken();
        $this->assertStringStartsWith((string) config('chat.external_api.token_prefix'), $token);
        $hash = $tokenService->hashToken($token);
        $this->assertTrue($tokenService->verifyToken($token, $hash));
        $this->assertFalse($tokenService->verifyToken($token.'x', $hash));

        /** @var ChatWebhookSigningService $signingService */
        $signingService = app(ChatWebhookSigningService::class);
        $payload = json_encode(['event' => 'message.created'], JSON_THROW_ON_ERROR);
        $secret = 'super-secret';
        $signed = $signingService->signPayload($payload, $secret);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertArrayHasKey('timestamp', $signed);
        $this->assertTrue($signingService->verifySignature($payload, $secret, $signed['signature'], $signed['timestamp']));
        $this->assertFalse($signingService->verifySignature($payload, $secret, 'v1=bad', $signed['timestamp']));
        $this->assertFalse($signingService->verifySignature($payload, $secret, $signed['signature'], now()->subHour()->timestamp));

        $admin = $this->actingAsWithPermissions([
            'chat.webhooks.create',
            'chat.webhooks.view',
            'chat.webhooks.edit',
            'chat.webhooks.delete',
        ]);

        $createResponse = $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'CRM Endpoint',
            'url' => 'https://example.com/webhooks/chat',
            'events' => ['message.created', 'message.updated'],
            'is_active' => true,
        ])->assertCreated();

        $endpointId = (int) $createResponse->json('data.id');
        $this->assertNotEmpty($createResponse->json('data.plain_token'));
        $this->assertArrayNotHasKey('secret', (array) $createResponse->json('data'));
        $this->assertArrayNotHasKey('token_hash', (array) $createResponse->json('data'));

        $endpoint = ChatWebhookEndpoint::query()->findOrFail($endpointId);
        $this->assertNotEmpty(data_get($endpoint->metadata, 'token_hash'));

        $this->getJson('/api/v1/chat/webhook-endpoints')
            ->assertOk()
            ->assertJsonMissingPath('data.0.secret')
            ->assertJsonMissingPath('data.0.token_hash');

        $this->patchJson("/api/v1/chat/webhook-endpoints/{$endpointId}", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $nonAdmin = $this->actingAsWithPermissions(['chat.view']);
        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Denied Endpoint',
            'url' => 'https://example.com/nope',
            'events' => ['message.created'],
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/chat/webhook-endpoints/{$endpointId}")
            ->assertOk();

        $this->assertSoftDeleted('chat_webhook_endpoints', ['id' => $endpointId]);

        [$conversation, $message] = $this->createConversationWithMessage($admin);

        /** @var ChatWebhookDeliveryService $deliveryService */
        $deliveryService = app(ChatWebhookDeliveryService::class);
        $freshEndpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Delivery Endpoint',
            'url' => 'https://example.com/delivery',
            'secret' => Str::random(40),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $admin->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);

        $delivery = $deliveryService->createDelivery($freshEndpoint, 'message.created', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'type' => 'text',
        ]);
        $this->assertSame('pending', $delivery->status);

        $retrying = $deliveryService->scheduleRetry($delivery);
        $this->assertSame('retrying', $retrying->status);
        $this->assertNotNull($retrying->next_retry_at);

        $retrying->attempts = (int) config('chat.webhooks.max_attempts', 5);
        $retrying->save();
        $failed = $deliveryService->scheduleRetry($retrying);
        $this->assertSame('failed', $failed->status);
        $this->assertNull($failed->next_retry_at);

        /** @var ExternalMessageMappingService $mappingService */
        $mappingService = app(ExternalMessageMappingService::class);
        $mapping = $mappingService->mapExternalMessage(
            $conversation,
            $message,
            'crm',
            'ext-123',
            [
                'source' => 'crm_sync',
                'module' => 'tickets',
                'direction' => 'outbound',
                'unsafe_payload' => ['secret' => 'nope'],
            ]
        );
        $found = $mappingService->findByExternalId('crm', 'ext-123');
        $this->assertNotNull($found);
        $this->assertSame($mapping->id, $found?->id);
        $this->assertNull(data_get($found?->metadata, 'unsafe_payload'));
    }
}


