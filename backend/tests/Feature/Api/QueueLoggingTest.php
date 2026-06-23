<?php

namespace Tests\Feature\Api;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function makeEndpoint(array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Queue Logging Endpoint',
            'url' => 'https://example.test/webhook',
            'secret' => 'endpoint-secret',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => User::factory()->create()->id,
            'metadata' => ['token_hash' => 'hash'],
        ], $overrides));
    }

    private function makeDelivery(ChatWebhookEndpoint $endpoint, array $overrides = []): ChatWebhookDelivery
    {
        return ChatWebhookDelivery::query()->create(array_merge([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'message.created',
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => [
                'conversation_id' => 1,
                'message_id' => 2,
                'body' => 'hello',
                'token' => 'must-not-log',
                'secret' => 'must-not-log',
                'raw_payload' => ['unsafe' => true],
            ],
            'status' => 'pending',
            'attempts' => 0,
        ], $overrides));
    }

    public function test_deliver_chat_webhook_job_logs_safe_start_and_success_context(): void
    {
        config(['logging.queue.enabled' => true]);
        Log::spy();

        Http::fake([
            'https://example.test/success' => Http::response(['ok' => true, 'token' => 'unsafe'], 200),
        ]);

        $endpoint = $this->makeEndpoint(['url' => 'https://example.test/success']);
        $delivery = $this->makeDelivery($endpoint);

        DeliverChatWebhookJob::dispatchSync($delivery->id);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.webhooks.delivery.started'
                    && isset($context['delivery_id'], $context['event'], $context['attempt'])
                    && ! array_key_exists('token', $context)
                    && ! array_key_exists('secret', $context)
                    && ! array_key_exists('raw_payload', $context)
                    && ! array_key_exists('response_body', $context);
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.webhooks.delivery.completed'
                    && data_get($context, 'status') === 'sent'
                    && data_get($context, 'response_status') === 200
                    && isset($context['duration_ms'])
                    && ! array_key_exists('token', $context)
                    && ! array_key_exists('secret', $context)
                    && ! array_key_exists('raw_response', $context)
                    && ! array_key_exists('authorization', $context);
            })
            ->once();
    }

    public function test_deliver_chat_webhook_job_logs_safe_failure_and_retry_context_without_breaking_flow(): void
    {
        config(['logging.queue.enabled' => true]);
        Log::spy();

        Http::fake([
            'https://example.test/retry' => Http::response([
                'error' => 'nope',
                'raw_response' => ['unsafe' => true],
                'signature' => 'unsafe',
            ], 500),
        ]);

        $endpoint = $this->makeEndpoint(['url' => 'https://example.test/retry']);
        $delivery = $this->makeDelivery($endpoint);

        DeliverChatWebhookJob::dispatchSync($delivery->id);
        $delivery->refresh();

        $this->assertSame('retrying', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.webhooks.delivery.retry_scheduled'
                    && data_get($context, 'status') === 'retrying'
                    && ! array_key_exists('signature', $context)
                    && ! array_key_exists('raw_response', $context)
                    && ! array_key_exists('authorization', $context);
            })
            ->once();
    }

    public function test_queue_logging_can_be_disabled_via_config(): void
    {
        config(['logging.queue.enabled' => false]);
        Log::spy();

        Http::fake([
            'https://example.test/silent' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = $this->makeEndpoint(['url' => 'https://example.test/silent']);
        $delivery = $this->makeDelivery($endpoint);

        DeliverChatWebhookJob::dispatchSync($delivery->id);

        Log::shouldNotHaveReceived('info', [
            'queue.webhooks.delivery.started',
            \Mockery::type('array'),
        ]);
        Log::shouldNotHaveReceived('info', [
            'queue.webhooks.delivery.completed',
            \Mockery::type('array'),
        ]);
        Log::shouldNotHaveReceived('warning', [
            'queue.webhooks.delivery.retry_scheduled',
            \Mockery::type('array'),
        ]);
        Log::shouldNotHaveReceived('error', [
            'queue.webhooks.delivery.failed',
            \Mockery::type('array'),
        ]);
    }
}

