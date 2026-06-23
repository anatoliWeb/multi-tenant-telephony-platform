<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ExternalMessageMapping;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSecretRotationService;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatIncomingWebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

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
            'title' => 'Incoming Webhook Conversation',
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
            'name' => 'Incoming Webhook Endpoint',
            'url' => 'https://example.test/incoming',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => ['token_hash' => 'hash'],
        ], $overrides));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function postSignedWebhook(
        ChatWebhookEndpoint $endpoint,
        array $payload,
        string $secret,
        ?int $timestamp = null
    ) {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signing = app(ChatWebhookSigningService::class);
        $signed = $signing->signPayload($payloadJson ?: '{}', $secret, $timestamp);
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
                'HTTP_'.$this->toServerHeader($sigHeader) => $signed['signature'],
                'HTTP_'.$this->toServerHeader($tsHeader) => (string) $signed['timestamp'],
            ],
            $payloadJson ?: '{}'
        );
    }

    private function toServerHeader(string $header): string
    {
        return strtoupper(str_replace('-', '_', $header));
    }

    /**
     * @return array<string,mixed>
     */
    private function validPayload(int $conversationId, array $overrides = []): array
    {
        return array_merge([
            'event' => 'message.created',
            'conversation_id' => $conversationId,
            'external_provider' => 'provider-a',
            'external_message_id' => 'ext-001',
            'body' => 'Incoming webhook message',
            'type' => 'text',
            'sent_at' => now()->toISOString(),
            'metadata' => [
                'source' => 'external-system',
                'trace_id' => 'tr-1',
                'user_agent' => 'SHOULD_NOT_STORE',
                'ip_address' => '127.0.0.1',
                'token' => 'SHOULD_NOT_STORE',
            ],
            'idempotency_key' => 'idp-001',
        ], $overrides);
    }

    public function test_incoming_webhook_endpoint_foundation(): void
    {
        Bus::fake();

        $actor = User::factory()->create();
        $this->grantPermissions($actor, ['chat.external_api.send', 'chat.send', 'chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($actor);
        $this->addParticipant($conversation, $actor, ['role' => 'owner']);
        $endpoint = $this->makeEndpoint($actor);
        $callbackEndpoint = $this->makeEndpoint($actor, [
            'name' => 'Outgoing Callback Endpoint',
            'url' => 'https://example.test/outgoing',
            'secret' => Str::random(64),
            'uuid' => (string) Str::uuid(),
        ]);

        $response = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id), $endpoint->secret);
        $response->assertCreated();
        $this->assertFalse((bool) $response->json('meta.idempotent'));
        $messageId = (int) $response->json('data.id');
        $message = Message::query()->findOrFail($messageId);
        $this->assertSame($conversation->id, (int) $message->conversation_id);
        $this->assertSame('ext-001', (string) $message->external_id);

        $mapping = ExternalMessageMapping::query()
            ->where('provider', 'provider-a')
            ->where('external_id', 'ext-001')
            ->first();
        $this->assertNotNull($mapping);

        $this->assertArrayNotHasKey('user_agent', (array) data_get($message->metadata, 'external_metadata', []));
        $this->assertArrayNotHasKey('ip_address', (array) data_get($message->metadata, 'external_metadata', []));
        $this->assertArrayNotHasKey('token', (array) data_get($message->metadata, 'external_metadata', []));

        $dup = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id), $endpoint->secret);
        $dup->assertOk();
        $this->assertTrue((bool) $dup->json('meta.idempotent'));
        $this->assertSame($messageId, (int) $dup->json('data.id'));
        $this->assertSame(1, Message::query()->where('external_id', 'ext-001')->count());

        Bus::assertDispatched(DeliverChatWebhookJob::class);
        $this->assertGreaterThan(0, ChatWebhookDelivery::query()->where('event', 'message.created')->count());
        $this->assertNotNull($callbackEndpoint);

        $invalidPayloadJson = json_encode($this->validPayload($conversation->id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signing = app(ChatWebhookSigningService::class);
        $signed = $signing->signPayload($invalidPayloadJson ?: '{}', 'wrong-secret');
        $sigHeader = (string) config('chat.webhooks.signature_header', 'X-Chat-Signature');
        $tsHeader = (string) config('chat.webhooks.timestamp_header', 'X-Chat-Timestamp');
        $this->call(
            'POST',
            '/api/v1/chat/external/webhooks/'.$endpoint->uuid,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.$this->toServerHeader($sigHeader) => $signed['signature'],
                'HTTP_'.$this->toServerHeader($tsHeader) => (string) $signed['timestamp'],
            ],
            $invalidPayloadJson ?: '{}'
        )->assertStatus(403);

        $this->postJson('/api/v1/chat/external/webhooks/'.$endpoint->uuid, $this->validPayload($conversation->id))
            ->assertStatus(403);

        $oldTimestamp = now()->subSeconds(((int) config('chat.webhooks.tolerance_seconds', 300)) + 10)->timestamp;
        $expired = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-expired',
            'idempotency_key' => 'idp-expired',
        ]), $endpoint->secret, $oldTimestamp);
        $expired->assertStatus(403);

        $oldSecret = $endpoint->secret;
        $rotation = app(ChatWebhookSecretRotationService::class);
        $rotation->rotateSecret($endpoint, $actor, 3600);
        $endpoint->refresh();
        $withPrevious = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-prev-ok',
            'idempotency_key' => 'idp-prev-ok',
        ]), $oldSecret);
        $withPrevious->assertCreated();

        $meta = (array) $endpoint->metadata;
        $rotationMeta = (array) data_get($meta, 'webhook_secret_rotation', []);
        $rotationMeta['previous_secret_expires_at'] = now()->subSecond()->toISOString();
        $meta['webhook_secret_rotation'] = $rotationMeta;
        $endpoint->metadata = $meta;
        $endpoint->save();
        $endpoint->refresh();
        $withExpiredPrevious = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-prev-expired',
            'idempotency_key' => 'idp-prev-expired',
        ]), $oldSecret);
        $withExpiredPrevious->assertStatus(403);

        $endpoint->is_active = false;
        $endpoint->status = 'disabled';
        $endpoint->save();
        $inactive = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-inactive',
            'idempotency_key' => 'idp-inactive',
        ]), $endpoint->secret);
        $inactive->assertStatus(403);

        $endpoint->is_active = true;
        $endpoint->status = 'active';
        $endpoint->events = ['message.updated'];
        $endpoint->save();
        $notSubscribed = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-not-subscribed',
            'idempotency_key' => 'idp-not-subscribed',
        ]), $endpoint->secret);
        $notSubscribed->assertStatus(422);

        $endpoint->events = ['message.created'];
        $endpoint->save();
        $invalidPayload = $this->postSignedWebhook($endpoint, [
            'event' => 'message.created',
            'conversation_id' => $conversation->id,
            'external_provider' => 'provider-a',
            'external_message_id' => 'ext-invalid',
            'type' => 'text',
            'idempotency_key' => 'idp-invalid',
        ], $endpoint->secret);
        $invalidPayload->assertStatus(422);

        $safeResponse = $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'ext-safe-response',
            'idempotency_key' => 'idp-safe-response',
        ]), $endpoint->secret)->assertCreated();
        $responseData = (array) $safeResponse->json();
        $this->assertStringNotContainsString('token_hash', json_encode($responseData));
        $this->assertStringNotContainsString('secret', json_encode($responseData));
        $this->assertStringNotContainsString('webhook_secret_rotation', json_encode($responseData));

        foreach (['closed', 'archived', 'deleted'] as $status) {
            $conv = $this->makeConversation($actor, ['status' => $status]);
            $this->addParticipant($conv, $actor, ['role' => 'owner']);
            $reject = $this->postSignedWebhook($endpoint, $this->validPayload($conv->id, [
                'external_message_id' => 'ext-'.$status,
                'idempotency_key' => 'idp-'.$status,
            ]), $endpoint->secret);
            $reject->assertStatus(422);
        }
    }

    public function test_incoming_webhook_route_is_rate_limited(): void
    {
        config()->set('chat.external_api.rate_limit.enabled', true);
        config()->set('chat.external_api.rate_limit.max_attempts', 2);
        config()->set('chat.external_api.rate_limit.decay_seconds', 60);

        $actor = User::factory()->create();
        $this->grantPermissions($actor, ['chat.external_api.send', 'chat.send', 'chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($actor);
        $this->addParticipant($conversation, $actor, ['role' => 'owner']);
        $endpoint = $this->makeEndpoint($actor);

        $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'rl-1',
            'idempotency_key' => 'rl-idem-1',
        ]), $endpoint->secret)->assertCreated();
        $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'rl-2',
            'idempotency_key' => 'rl-idem-2',
        ]), $endpoint->secret)->assertCreated();
        $this->postSignedWebhook($endpoint, $this->validPayload($conversation->id, [
            'external_message_id' => 'rl-3',
            'idempotency_key' => 'rl-idem-3',
        ]), $endpoint->secret)->assertStatus(429);
    }
}

