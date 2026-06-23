<?php

namespace Tests\Feature\Chat;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ExternalMessageMapping;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatWebhookReplayProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Replay Conversation',
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
            'role' => 'owner',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);
    }

    private function makeEndpoint(User $creator): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Replay Endpoint',
            'url' => 'https://example.test/incoming',
            'secret' => Str::random(64),
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
     * @param array<string,mixed> $payload
     */
    private function postSigned(ChatWebhookEndpoint $endpoint, array $payload, string $secret, ?int $timestamp = null)
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
     * @return array<string,mixed>
     */
    private function payload(int $conversationId, array $overrides = []): array
    {
        return array_merge([
            'event' => 'message.created',
            'conversation_id' => $conversationId,
            'external_provider' => 'provider-a',
            'external_message_id' => 'replay-msg-1',
            'body' => 'Replay protected message',
            'type' => 'text',
            'idempotency_key' => 'replay-idem-1',
        ], $overrides);
    }

    public function test_webhook_replay_protection_foundation(): void
    {
        config()->set('chat.webhooks.replay_protection.enabled', true);
        config()->set('chat.webhooks.replay_protection.ttl_seconds', 300);

        $actor = User::factory()->create();
        $this->grantPermissions($actor, ['chat.external_api.send', 'chat.send', 'chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($actor);
        $this->addParticipant($conversation, $actor);
        $endpoint = $this->makeEndpoint($actor);

        $ts = now()->timestamp;
        $first = $this->postSigned($endpoint, $this->payload($conversation->id), $endpoint->secret, $ts);
        $first->assertCreated();

        $replay = $this->postSigned($endpoint, $this->payload($conversation->id), $endpoint->secret, $ts);
        $replay->assertStatus(409);
        $this->assertStringNotContainsString('secret', (string) $replay->getContent());
        $this->assertStringNotContainsString('signature', (string) $replay->getContent());
        $this->assertStringNotContainsString('token', (string) $replay->getContent());

        $this->assertSame(1, Message::query()->where('external_id', 'replay-msg-1')->count());
        $this->assertSame(1, ExternalMessageMapping::query()->where('provider', 'provider-a')->where('external_id', 'replay-msg-1')->count());

        // Invalid signature must not poison replay cache.
        $bad = $this->postSigned($endpoint, $this->payload($conversation->id, [
            'external_message_id' => 'replay-msg-2',
            'idempotency_key' => 'replay-idem-2',
        ]), 'wrong-secret', $ts + 1);
        $bad->assertStatus(403);
        $good = $this->postSigned($endpoint, $this->payload($conversation->id, [
            'external_message_id' => 'replay-msg-2',
            'idempotency_key' => 'replay-idem-2',
        ]), $endpoint->secret, $ts + 1);
        $good->assertCreated();

        // Disabled config allows duplicate signed request (idempotent path returns 200).
        config()->set('chat.webhooks.replay_protection.enabled', false);
        $dupTs = $ts + 2;
        $dupFirst = $this->postSigned($endpoint, $this->payload($conversation->id, [
            'external_message_id' => 'replay-msg-3',
            'idempotency_key' => 'replay-idem-3',
        ]), $endpoint->secret, $dupTs);
        $dupFirst->assertCreated();
        $dupSecond = $this->postSigned($endpoint, $this->payload($conversation->id, [
            'external_message_id' => 'replay-msg-3',
            'idempotency_key' => 'replay-idem-3',
        ]), $endpoint->secret, $dupTs);
        $dupSecond->assertOk()->assertJsonPath('meta.idempotent', true);
    }
}

