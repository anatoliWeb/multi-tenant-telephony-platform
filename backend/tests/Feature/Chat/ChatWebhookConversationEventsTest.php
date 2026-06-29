<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatWebhookConversationEventsTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function createEndpoint(string $name, array $events, bool $active = true): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'url' => 'https://example.test/'.Str::slug($name),
            'secret' => Str::random(40),
            'events' => $events,
            'is_active' => $active,
            'status' => $active ? 'active' : 'disabled',
            'created_by' => User::factory()->create()->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);
    }

    public function test_conversation_created_queues_webhook_delivery_with_safe_payload(): void
    {
        Bus::fake();

        $creator = $this->actingAsWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
        ]);
        $target = User::factory()->create();

        $active = $this->createEndpoint('Conversation Active', ['conversation.created'], true);
        $inactive = $this->createEndpoint('Conversation Inactive', ['conversation.created'], false);
        $otherOnly = $this->createEndpoint('Conversation Other', ['message.created'], true);

        $this->postJson('/api/v1/chat/conversations/direct', [
            'user_id' => $target->id,
        ])->assertCreated();

        $delivery = ChatWebhookDelivery::query()
            ->where('event', 'conversation.created')
            ->where('webhook_endpoint_id', $active->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($delivery);
        $this->assertSame(0, ChatWebhookDelivery::query()->where('event', 'conversation.created')->where('webhook_endpoint_id', $inactive->id)->count());
        $this->assertSame(0, ChatWebhookDelivery::query()->where('event', 'conversation.created')->where('webhook_endpoint_id', $otherOnly->id)->count());

        $payload = (array) ($delivery?->payload ?? []);
        $this->assertSame('conversation.created', data_get($payload, 'event'));
        $this->assertSame('direct', data_get($payload, 'type'));
        $this->assertSame('private', data_get($payload, 'visibility'));
        $this->assertSame('active', data_get($payload, 'status'));
        $this->assertSame($creator->id, data_get($payload, 'created_by'));
        $this->assertNotNull(data_get($payload, 'conversation_id'));

        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('device_key', $payload);
        $this->assertArrayNotHasKey('user_agent', $payload);
        $this->assertArrayNotHasKey('ip_address', $payload);
        $this->assertArrayNotHasKey('blocked_reason', $payload);

        Bus::assertDispatched(DeliverChatWebhookJob::class);
    }
}


