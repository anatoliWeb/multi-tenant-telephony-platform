<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatWebhookDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatWebhookDeliveryAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function makeEndpoint(array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Audit Endpoint',
            'url' => 'https://example.test/audit',
            'secret' => 'endpoint-secret',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => User::factory()->create()->id,
            'metadata' => ['token_hash' => 'hash'],
        ], $overrides));
    }

    private function makeConversationAndMessage(User $owner): array
    {
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Webhook delivery audit',
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
            'body' => 'audit message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return [$conversation, $message];
    }

    public function test_webhook_delivery_audit_logging_foundation(): void
    {
        config()->set('chat.webhooks.max_attempts', 2);

        /** @var ChatWebhookDeliveryService $service */
        $service = app(ChatWebhookDeliveryService::class);
        $endpoint = $this->makeEndpoint();
        [$conversation, $message] = $this->makeConversationAndMessage(User::factory()->create());

        $delivery = $service->createDelivery($endpoint, 'message.created', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'raw_payload' => ['unsafe' => true],
            'token' => 'unsafe',
            'signature' => 'unsafe',
            'response_body' => ['unsafe' => true],
        ]);

        $createdLog = ChatModerationLog::query()
            ->where('action', 'webhook.delivery.created')
            ->whereJsonContains('metadata->webhook_delivery_id', $delivery->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($createdLog);
        $this->assertNull($createdLog->actor_id);
        $this->assertSame($conversation->id, $createdLog->conversation_id);
        $this->assertSame($message->id, $createdLog->message_id);
        $this->assertSame('message.created', data_get($createdLog->metadata, 'event_type'));
        $this->assertSame('pending', data_get($createdLog->metadata, 'status'));
        $this->assertArrayNotHasKey('raw_payload', $createdLog->metadata ?? []);
        $this->assertArrayNotHasKey('response_body', $createdLog->metadata ?? []);
        $this->assertArrayNotHasKey('token', $createdLog->metadata ?? []);
        $this->assertArrayNotHasKey('signature', $createdLog->metadata ?? []);

        $sent = $service->markSucceeded($delivery, 200, ['ok' => true, 'secret' => 'unsafe']);
        $sentLog = ChatModerationLog::query()
            ->where('action', 'webhook.delivery.sent')
            ->whereJsonContains('metadata->webhook_delivery_id', $sent->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($sentLog);
        $this->assertSame('sent', data_get($sentLog->metadata, 'status'));
        $this->assertSame(200, data_get($sentLog->metadata, 'response_status'));
        $this->assertSame(1, ChatModerationLog::query()
            ->where('action', 'webhook.delivery.sent')
            ->whereJsonContains('metadata->webhook_delivery_id', $sent->id)
            ->count());

        $retryDelivery = $service->createDelivery($endpoint, 'message.delivery.updated', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ]);
        $retryDelivery = $service->markFailed($retryDelivery, str_repeat('x', 512));
        $retrying = $service->scheduleRetry($retryDelivery);
        $retryingLog = ChatModerationLog::query()
            ->where('action', 'webhook.delivery.retrying')
            ->whereJsonContains('metadata->webhook_delivery_id', $retrying->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($retryingLog);
        $this->assertSame('retrying', data_get($retryingLog->metadata, 'status'));
        $this->assertNotNull(data_get($retryingLog->metadata, 'next_retry_at'));
        $this->assertLessThanOrEqual(255, strlen((string) data_get($retryingLog->metadata, 'error_summary', '')));
        $this->assertSame(
            0,
            ChatModerationLog::query()
                ->where('action', 'webhook.delivery.failed')
                ->whereJsonContains('metadata->webhook_delivery_id', $retrying->id)
                ->count()
        );

        $failedDelivery = $service->createDelivery($endpoint, 'message.deleted', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ]);
        $failedDelivery->attempts = 1;
        $failedDelivery->save();
        $failed = $service->markFailed($failedDelivery, 'HTTP 500');

        $failedLog = ChatModerationLog::query()
            ->where('action', 'webhook.delivery.failed')
            ->whereJsonContains('metadata->webhook_delivery_id', $failed->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($failedLog);
        $this->assertSame('failed', data_get($failedLog->metadata, 'status'));
        $this->assertSame(2, data_get($failedLog->metadata, 'attempts'));
        $this->assertSame(1, ChatModerationLog::query()
            ->where('action', 'webhook.delivery.failed')
            ->whereJsonContains('metadata->webhook_delivery_id', $failed->id)
            ->count());

        $cancelledDelivery = $service->createDelivery($endpoint, 'message.updated', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ]);
        $cancelled = $service->markCancelled($cancelledDelivery, 'Endpoint inactive or deleted');
        $cancelledLog = ChatModerationLog::query()
            ->where('action', 'webhook.delivery.cancelled')
            ->whereJsonContains('metadata->webhook_delivery_id', $cancelled->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($cancelledLog);
        $this->assertSame('cancelled', data_get($cancelledLog->metadata, 'status'));
        $this->assertSame('Endpoint inactive or deleted', data_get($cancelledLog->metadata, 'cancelled_reason'));
    }
}
