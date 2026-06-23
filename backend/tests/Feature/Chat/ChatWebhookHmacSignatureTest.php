<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSecretRotationService;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookHmacSignatureTest extends TestCase
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
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'HMAC Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);

        return $conversation;
    }

    private function makeEndpoint(User $creator, string $secret = 'endpoint-secret'): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HMAC Endpoint',
            'url' => 'https://example.test/hmac',
            'secret' => $secret,
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);
    }

    private function toServerHeader(string $header): string
    {
        return strtoupper(str_replace('-', '_', $header));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postSignedIncoming(ChatWebhookEndpoint $endpoint, array $payload, string $secret, ?int $timestamp = null)
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $signed = app(ChatWebhookSigningService::class)->signPayload($payloadJson, $secret, $timestamp);
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
            $payloadJson
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function incomingPayload(int $conversationId, array $overrides = []): array
    {
        return array_merge([
            'event' => 'message.created',
            'conversation_id' => $conversationId,
            'external_provider' => 'provider-a',
            'external_message_id' => 'hmac-msg-1',
            'body' => 'Signed message',
            'type' => 'text',
            'idempotency_key' => 'hmac-idem-1',
        ], $overrides);
    }

    public function test_webhook_hmac_signature_foundation(): void
    {
        config()->set('chat.webhooks.signing_algo', 'sha256');

        $owner = User::factory()->create();
        $owner->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.external_api.send'])->id,
            Permission::firstOrCreate(['name' => 'chat.send'])->id,
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);

        $endpoint = $this->makeEndpoint($owner, 'secret-v1');
        $conversation = $this->makeConversation($owner);
        $message = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Seed message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // outgoing delivery signs payload and sends signature/timestamp headers
        Http::fake(['https://example.test/hmac' => Http::response(['ok' => true], 200)]);
        $delivery = app(\App\Services\Chat\ChatWebhookDeliveryService::class)->createDelivery($endpoint, 'message.created', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'type' => 'text',
        ]);
        DeliverChatWebhookJob::dispatchSync($delivery->id);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $signature = $request->header((string) config('chat.webhooks.signature_header', 'X-Chat-Signature'))[0] ?? '';
            $timestamp = $request->header((string) config('chat.webhooks.timestamp_header', 'X-Chat-Timestamp'))[0] ?? '';

            return str_starts_with((string) $signature, 'v1=')
                && ctype_digit((string) $timestamp);
        });

        // incoming validation happy path
        $ok = $this->postSignedIncoming($endpoint, $this->incomingPayload($conversation->id), 'secret-v1');
        $ok->assertCreated();

        // missing signature
        $this->postJson('/api/v1/chat/external/webhooks/'.$endpoint->uuid, $this->incomingPayload($conversation->id, [
            'external_message_id' => 'hmac-msg-2',
            'idempotency_key' => 'hmac-idem-2',
        ]))->assertStatus(403);

        // invalid signature
        $bad = $this->postSignedIncoming($endpoint, $this->incomingPayload($conversation->id, [
            'external_message_id' => 'hmac-msg-3',
            'idempotency_key' => 'hmac-idem-3',
        ]), 'wrong-secret');
        $bad->assertStatus(403);

        // expired timestamp
        $expiredTs = now()->subSeconds(((int) config('chat.webhooks.tolerance_seconds', 300)) + 5)->timestamp;
        $expired = $this->postSignedIncoming($endpoint, $this->incomingPayload($conversation->id, [
            'external_message_id' => 'hmac-msg-4',
            'idempotency_key' => 'hmac-idem-4',
        ]), 'secret-v1', $expiredTs);
        $expired->assertStatus(403);

        // secret rotation compatibility
        $rotation = app(ChatWebhookSecretRotationService::class);
        $rotation->rotateSecret($endpoint, $owner, 3600);
        $endpoint->refresh();

        $oldSecretOk = $this->postSignedIncoming($endpoint, $this->incomingPayload($conversation->id, [
            'external_message_id' => 'hmac-msg-5',
            'idempotency_key' => 'hmac-idem-5',
        ]), 'secret-v1');
        $oldSecretOk->assertCreated();

        $meta = (array) $endpoint->metadata;
        $rotationMeta = (array) data_get($meta, 'webhook_secret_rotation', []);
        $rotationMeta['previous_secret_expires_at'] = now()->subSecond()->toISOString();
        $meta['webhook_secret_rotation'] = $rotationMeta;
        $endpoint->metadata = $meta;
        $endpoint->save();
        $endpoint->refresh();

        $oldSecretExpired = $this->postSignedIncoming($endpoint, $this->incomingPayload($conversation->id, [
            'external_message_id' => 'hmac-msg-6',
            'idempotency_key' => 'hmac-idem-6',
        ]), 'secret-v1');
        $oldSecretExpired->assertStatus(403);

        // safe response: no secret/signature/token leakage
        $responseContent = (string) $ok->getContent();
        $this->assertStringNotContainsString('secret', $responseContent);
        $this->assertStringNotContainsString('signature', $responseContent);
        $this->assertStringNotContainsString('token_hash', $responseContent);
    }
}
