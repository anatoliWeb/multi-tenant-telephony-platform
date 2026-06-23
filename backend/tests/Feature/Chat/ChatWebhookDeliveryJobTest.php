<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookDeliveryJobTest extends TestCase
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

    private function makeEndpoint(array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Webhook Endpoint',
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
            'payload' => ['conversation_id' => 1, 'message_id' => 2, 'body' => 'hello'],
            'status' => 'pending',
            'attempts' => 0,
        ], $overrides));
    }

    private function makeConversationAndMessage(User $owner): array
    {
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Webhook Queue Event',
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
            'body' => 'Webhook message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return [$conversation, $message];
    }

    public function test_chat_webhook_delivery_job_foundation(): void
    {
        Http::fake([
            'https://example.test/success' => Http::response(['ok' => true, 'secret' => 'must-not-store'], 200),
        ]);

        $endpoint = $this->makeEndpoint(['url' => 'https://example.test/success']);
        $delivery = $this->makeDelivery($endpoint);

        DeliverChatWebhookJob::dispatchSync($delivery->id);

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull(data_get($delivery->response_body, 'secret'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->url() === 'https://example.test/success'
                && $request->hasHeader('X-Chat-Signature')
                && $request->hasHeader('X-Chat-Timestamp')
                && $request->hasHeader('User-Agent', 'LaravelChatWebhook/1.0');
        });

        $retryEndpoint = $this->makeEndpoint(['url' => 'https://example.test/retry']);
        Http::fake([
            'https://example.test/retry' => Http::response(['error' => 'nope', 'token' => 'x'], 500),
        ]);
        $retryDelivery = $this->makeDelivery($retryEndpoint);
        DeliverChatWebhookJob::dispatchSync($retryDelivery->id);
        $retryDelivery->refresh();
        $this->assertSame('retrying', $retryDelivery->status);
        $this->assertNotNull($retryDelivery->next_retry_at);
        $this->assertSame(1, $retryDelivery->attempts);
        $this->assertNull(data_get($retryDelivery->response_body, 'token'));

        $exceptionEndpoint = $this->makeEndpoint(['url' => 'https://example.test/exception']);
        Http::fake([
            'https://example.test/exception' => function () {
                throw new \RuntimeException('timeout');
            },
        ]);
        $exceptionDelivery = $this->makeDelivery($exceptionEndpoint);
        DeliverChatWebhookJob::dispatchSync($exceptionDelivery->id);
        $exceptionDelivery->refresh();
        $this->assertSame('retrying', $exceptionDelivery->status);
        $this->assertNotNull($exceptionDelivery->next_retry_at);

        $maxEndpoint = $this->makeEndpoint(['url' => 'https://example.test/max']);
        Http::fake([
            'https://example.test/max' => Http::response(['error' => 'still-bad'], 500),
        ]);
        $maxDelivery = $this->makeDelivery($maxEndpoint, ['attempts' => (int) config('chat.webhooks.max_attempts', 5) - 1]);
        DeliverChatWebhookJob::dispatchSync($maxDelivery->id);
        $maxDelivery->refresh();
        $this->assertSame('failed', $maxDelivery->status);

        Http::fake();
        $inactiveEndpoint = $this->makeEndpoint([
            'url' => 'https://example.test/inactive',
            'is_active' => false,
            'status' => 'disabled',
        ]);
        $inactiveDelivery = $this->makeDelivery($inactiveEndpoint);
        DeliverChatWebhookJob::dispatchSync($inactiveDelivery->id);
        $inactiveDelivery->refresh();
        $this->assertSame('cancelled', $inactiveDelivery->status);
        Http::assertNothingSent();

        Bus::fake();
        $dueA = $this->makeDelivery($retryEndpoint, ['status' => 'retrying', 'next_retry_at' => now()->subMinute()]);
        $dueB = $this->makeDelivery($retryEndpoint, ['status' => 'pending', 'next_retry_at' => null]);
        $future = $this->makeDelivery($retryEndpoint, ['status' => 'retrying', 'next_retry_at' => now()->addHour()]);
        $sent = $this->makeDelivery($retryEndpoint, ['status' => 'sent']);

        $this->artisan('chat:webhooks:retry-due --limit=100')
            ->expectsOutputToContain('Chat Webhooks Retry Due')
            ->expectsOutputToContain('dispatched')
            ->assertExitCode(0);

        Bus::assertDispatched(DeliverChatWebhookJob::class, 2);
        Bus::assertDispatched(DeliverChatWebhookJob::class, fn (DeliverChatWebhookJob $job): bool => in_array($job->deliveryId, [$dueA->id, $dueB->id], true));
        Bus::assertNotDispatched(DeliverChatWebhookJob::class, fn (DeliverChatWebhookJob $job): bool => in_array($job->deliveryId, [$future->id, $sent->id], true));

        Bus::fake();
        $service = app(ChatWebhookDeliveryService::class);
        $activeEndpoint = $this->makeEndpoint(['events' => ['message.created', 'message.deleted']]);
        $inactiveForQueue = $this->makeEndpoint(['is_active' => false, 'status' => 'disabled', 'events' => ['message.created']]);
        [$conversation, $message] = $this->makeConversationAndMessage(User::factory()->create());
        $beforeForInactive = ChatWebhookDelivery::query()
            ->where('webhook_endpoint_id', $inactiveForQueue->id)
            ->count();
        $beforeForConversation = ChatWebhookDelivery::query()
            ->where('event', 'message.created')
            ->where('conversation_id', $conversation->id)
            ->count();

        $created = $service->queueEvent('message.created', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'secret' => 'unsafe',
        ]);
        $this->assertGreaterThanOrEqual(2, $created);
        $this->assertSame(
            $beforeForConversation + $created,
            ChatWebhookDelivery::query()
                ->where('event', 'message.created')
                ->where('conversation_id', $conversation->id)
                ->count()
        );
        $this->assertSame(
            $beforeForInactive,
            ChatWebhookDelivery::query()
                ->where('webhook_endpoint_id', $inactiveForQueue->id)
                ->count()
        );
        Bus::assertDispatched(DeliverChatWebhookJob::class, $created);

        $mappingUser = $this->actingAsWithPermissions(['chat.webhooks.create']);
        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'API Endpoint',
            'url' => 'https://example.test/api',
            'events' => ['message.created'],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonMissingPath('data.secret')
            ->assertJsonMissingPath('data.token_hash')
            ->assertJsonPath('data.name', 'API Endpoint');
    }
}
