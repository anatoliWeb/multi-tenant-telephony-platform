<?php

namespace Tests\Feature\Api;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Monitoring\StructuredLogContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructuredLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_removes_forbidden_keys_recursively_and_keeps_safe_keys(): void
    {
        $service = app(StructuredLogContextService::class);

        $sanitized = $service->sanitize([
            'event' => 'test',
            'safe' => 'ok',
            'token' => 'secret',
            'nested' => [
                'authorization' => 'Bearer xxx',
                'status' => 'sent',
                'payload' => ['x' => 1],
            ],
        ]);

        $this->assertSame('test', $sanitized['event']);
        $this->assertSame('ok', $sanitized['safe']);
        $this->assertArrayNotHasKey('token', $sanitized);
        $this->assertArrayNotHasKey('authorization', $sanitized['nested']);
        $this->assertArrayNotHasKey('payload', $sanitized['nested']);
        $this->assertSame('sent', $sanitized['nested']['status']);
    }

    public function test_exception_summary_contains_class_and_message_without_trace(): void
    {
        $service = app(StructuredLogContextService::class);

        $summary = $service->summarizeThrowable(new \RuntimeException('Sample failure for summary'));

        $this->assertArrayHasKey('error_class', $summary);
        $this->assertArrayHasKey('error_summary', $summary);
        $this->assertStringContainsString('RuntimeException', $summary['error_class']);
        $this->assertStringContainsString('Sample failure', $summary['error_summary']);
        $this->assertArrayNotHasKey('trace', $summary);
    }

    public function test_request_logs_include_request_id_and_structured_keys(): void
    {
        Log::spy();

        $response = $this->getJson('/health')->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'API Request'
                    && data_get($context, 'event') === 'http.request.completed'
                    && data_get($context, 'category') === 'request'
                    && data_get($context, 'module') === 'api'
                    && isset($context['request_id'], $context['method'], $context['path'], $context['duration_ms'])
                    && ! array_key_exists('authorization', $context)
                    && ! array_key_exists('cookie', $context)
                    && ! array_key_exists('token', $context);
            })
            ->once();
    }

    public function test_queue_and_realtime_logs_use_structured_safe_context(): void
    {
        config([
            'logging.queue.enabled' => true,
            'logging.realtime.enabled' => true,
            'logging.realtime.channel_auth_failures' => true,
        ]);
        Log::spy();

        Http::fake([
            'https://example.test/structured' => Http::response(['ok' => true, 'raw_response' => ['unsafe' => true]], 200),
        ]);

        $owner = User::factory()->create();
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Structured Logs',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
        ]);

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '9.1',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
            'authorization' => 'unsafe',
        ])->assertForbidden();

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Structured Queue Endpoint',
            'url' => 'https://example.test/structured',
            'secret' => 'endpoint-secret',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $owner->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);

        $delivery = ChatWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'message.created',
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => [
                'conversation_id' => $conversation->id,
                'body' => 'hello',
                'token' => 'must-not-log',
            ],
            'status' => 'pending',
            'attempts' => 0,
        ]);

        DeliverChatWebhookJob::dispatchSync($delivery->id);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'realtime.channel.auth.denied'
                    && data_get($context, 'category') === 'realtime'
                    && data_get($context, 'module') === 'broadcast'
                    && ! array_key_exists('authorization', $context)
                    && ! array_key_exists('cookie', $context)
                    && ! array_key_exists('token', $context);
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.webhooks.delivery.completed'
                    && data_get($context, 'category') === 'queue'
                    && data_get($context, 'module') === 'chat.webhooks'
                    && isset($context['job_class'], $context['queue'], $context['attempt'])
                    && ! array_key_exists('token', $context)
                    && ! array_key_exists('secret', $context)
                    && ! array_key_exists('raw_payload', $context)
                    && ! array_key_exists('raw_response', $context);
            })
            ->once();
    }

    public function test_structured_sanitizer_can_be_disabled_via_config(): void
    {
        $service = app(StructuredLogContextService::class);
        config()->set('logging.structured.enabled', false);

        $raw = [
            'token' => 'kept-when-disabled',
            'status' => 'ok',
        ];

        $this->assertSame($raw, $service->sanitize($raw));
    }
}

