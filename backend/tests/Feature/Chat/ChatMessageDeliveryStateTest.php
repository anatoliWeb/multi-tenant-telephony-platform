<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageDeliveryUpdated;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatMessageDeliveryStateTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Delivery state',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);
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
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ], $overrides));
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

    public function test_message_delivery_state_foundation(): void
    {
        Event::fake([ChatMessageDeliveryUpdated::class]);

        $sender = $this->actingAsWithPermissions([
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner']);
        $this->addParticipant($conversation, $recipientA);
        $this->addParticipant($conversation, $recipientB);

        $activeEndpoint = $this->createEndpoint('Delivery endpoint', ['message.delivery.updated'], true);
        $inactiveEndpoint = $this->createEndpoint('Delivery inactive', ['message.delivery.updated'], false);

        $sendResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Delivery check',
        ])->assertCreated();

        $messageId = (int) $sendResponse->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        $deliveries = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $deliveries);
        foreach ($deliveries as $delivery) {
            $this->assertSame('pending', $delivery->status);
            $this->assertNull($delivery->delivered_at);
            $this->assertNull($delivery->failed_at);
            $this->assertNull($delivery->failure_reason);
        }

        Event::assertDispatched(ChatMessageDeliveryUpdated::class, 2);
        Event::assertDispatched(ChatMessageDeliveryUpdated::class, function (ChatMessageDeliveryUpdated $event) use ($conversation, $message): bool {
            $payload = (array) $event->payload;

            $this->assertSame($conversation->id, $event->conversationId);
            $this->assertSame($conversation->id, (int) data_get($payload, 'conversation_id'));
            $this->assertSame($message->id, (int) data_get($payload, 'message_id'));
            $this->assertSame('pending', data_get($payload, 'status'));
            $this->assertArrayNotHasKey('metadata', $payload);
            $this->assertArrayNotHasKey('token', $payload);
            $this->assertArrayNotHasKey('secret', $payload);
            $this->assertArrayNotHasKey('signature', $payload);
            $this->assertArrayNotHasKey('authorization', $payload);
            $this->assertArrayNotHasKey('user_agent', $payload);
            $this->assertArrayNotHasKey('ip_address', $payload);

            return true;
        });

        $webhookDeliveries = ChatWebhookDelivery::query()
            ->where('event', 'message.delivery.updated')
            ->where('message_id', $message->id)
            ->get();
        $this->assertCount(2, $webhookDeliveries);
        $this->assertSame(
            2,
            ChatWebhookDelivery::query()
                ->where('event', 'message.delivery.updated')
                ->where('message_id', $message->id)
                ->where('webhook_endpoint_id', $activeEndpoint->id)
                ->count()
        );
        $this->assertSame(
            0,
            ChatWebhookDelivery::query()
                ->where('event', 'message.delivery.updated')
                ->where('message_id', $message->id)
                ->where('webhook_endpoint_id', $inactiveEndpoint->id)
                ->count()
        );

        $firstPayload = (array) ($webhookDeliveries->first()?->payload ?? []);
        $this->assertSame($conversation->id, (int) data_get($firstPayload, 'conversation_id'));
        $this->assertSame($message->id, (int) data_get($firstPayload, 'message_id'));
        $this->assertSame('pending', data_get($firstPayload, 'status'));
        $this->assertArrayNotHasKey('metadata', $firstPayload);
        $this->assertArrayNotHasKey('raw_payload', $firstPayload);
        $this->assertArrayNotHasKey('secret', $firstPayload);
        $this->assertArrayNotHasKey('signature', $firstPayload);
        $this->assertArrayNotHasKey('token', $firstPayload);
        $this->assertArrayNotHasKey('disk', $firstPayload);
        $this->assertArrayNotHasKey('path', $firstPayload);
        $this->assertArrayNotHasKey('checksum', $firstPayload);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertStatus(403);
    }
}

