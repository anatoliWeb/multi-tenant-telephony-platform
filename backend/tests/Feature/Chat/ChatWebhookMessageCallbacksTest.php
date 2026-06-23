<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookMessageCallbacksTest extends TestCase
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
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Webhook Callbacks',
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

    public function test_chat_webhook_message_callbacks_foundation(): void
    {
        Bus::fake();

        $sender = $this->actingAsWithPermissions([
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.view',
            'chat.conversations.view',
        ]);
        $reader = User::factory()->create();
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner']);
        $this->addParticipant($conversation, $reader);

        $activeAll = $this->createEndpoint('Active All', [
            'message.created',
            'message.updated',
            'message.deleted',
            'message.read',
            'message.device_read',
            'message.delivery.updated',
        ], true);
        $inactiveAll = $this->createEndpoint('Inactive All', [
            'message.created',
            'message.updated',
            'message.deleted',
            'message.read',
            'message.device_read',
            'message.delivery.updated',
        ], false);
        $otherOnly = $this->createEndpoint('Other Event', ['attachment.created'], true);

        $sendResponse = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Callback message',
        ])->assertCreated();
        $messageId = (int) $sendResponse->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.created')->where('message_id', $messageId)->count());
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.delivery.updated')->where('message_id', $messageId)->count());
        $this->assertSame(0, ChatWebhookDelivery::query()->where('event', 'attachment.created')->where('message_id', $messageId)->count());

        $this->patchJson("/api/v1/chat/messages/{$messageId}", ['body' => 'Callback message updated'])
            ->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.updated')->where('message_id', $messageId)->count());

        $this->deleteJson("/api/v1/chat/messages/{$messageId}")
            ->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.deleted')->where('message_id', $messageId)->count());

        $readerUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $reader->id)
            ->delete();
        $this->addParticipant($conversation, $readerUser);
        $this->postJson('/api/v1/chat/devices', [
            'device_key' => 'webhook-device-1',
            'device_type' => 'browser',
        ])->assertOk();

        $readableMessage = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Read me',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->patchJson("/api/v1/chat/messages/{$readableMessage->id}/read", [
            'device_key' => 'webhook-device-1',
        ])->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.read')->where('message_id', $readableMessage->id)->count());
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'message.device_read')->where('message_id', $readableMessage->id)->count());

        $bulkConversation = $this->makeConversation($readerUser);
        $this->addParticipant($bulkConversation, $readerUser, ['role' => 'owner']);
        $bulkSender = User::factory()->create();
        $this->addParticipant($bulkConversation, $bulkSender);

        $m1 = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $bulkConversation->id,
            'sender_id' => $bulkSender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Bulk 1',
            'status' => 'sent',
            'sent_at' => now()->subMinutes(3),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $bulkConversation->id,
            'sender_id' => $bulkSender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Bulk 2',
            'status' => 'sent',
            'sent_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        $m3 = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $bulkConversation->id,
            'sender_id' => $bulkSender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Bulk 3',
            'status' => 'sent',
            'sent_at' => now()->subMinutes(1),
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $beforeRead = ChatWebhookDelivery::query()->where('event', 'message.read')->count();
        $beforeDeviceRead = ChatWebhookDelivery::query()->where('event', 'message.device_read')->count();
        $this->patchJson("/api/v1/chat/conversations/{$bulkConversation->id}/read", [
            'device_key' => 'webhook-device-1',
        ])->assertOk();
        $this->assertSame($beforeRead + 1, ChatWebhookDelivery::query()->where('event', 'message.read')->count());
        $this->assertSame($beforeDeviceRead + 1, ChatWebhookDelivery::query()->where('event', 'message.device_read')->count());
        $lastReadDelivery = ChatWebhookDelivery::query()
            ->where('event', 'message.read')
            ->where('conversation_id', $bulkConversation->id)
            ->latest('id')
            ->first();
        $this->assertSame($m3->id, (int) data_get($lastReadDelivery?->payload, 'message_id'));

        $unsafePayloadDelivery = ChatWebhookDelivery::query()
            ->whereIn('event', ['message.created', 'message.updated', 'message.deleted', 'message.read', 'message.device_read', 'message.delivery.updated'])
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        $payload = (array) ($unsafePayloadDelivery?->payload ?? []);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertArrayNotHasKey('disk', $payload);
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('checksum', $payload);
        $this->assertArrayNotHasKey('user_agent', $payload);
        $this->assertArrayNotHasKey('ip_address', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('token', $payload);

        $inactiveCount = ChatWebhookDelivery::query()
            ->where('webhook_endpoint_id', $inactiveAll->id)
            ->count();
        $this->assertSame(0, $inactiveCount);
        $otherCount = ChatWebhookDelivery::query()
            ->where('webhook_endpoint_id', $otherOnly->id)
            ->whereIn('event', ['message.created', 'message.updated', 'message.deleted', 'message.read', 'message.device_read', 'message.delivery.updated'])
            ->count();
        $this->assertSame(0, $otherCount);

        Bus::assertDispatched(DeliverChatWebhookJob::class);
    }
}
