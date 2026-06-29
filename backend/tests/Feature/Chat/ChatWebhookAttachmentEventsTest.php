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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatWebhookAttachmentEventsTest extends TestCase
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
            'title' => 'Webhook Attachments',
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

    public function test_attachment_created_queues_webhook_delivery_with_safe_payload(): void
    {
        Bus::fake();
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.allowed_mimes', ['image/png', 'application/pdf', 'text/plain']);
        config()->set('chat.attachments.max_size_kb', 1024);

        $user = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
            'chat.attachments.upload',
        ]);
        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user, ['role' => 'owner', 'can_attach' => true, 'can_send' => true]);

        $message = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Attachment target message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $active = $this->createEndpoint('Attachment Active', ['attachment.created'], true);
        $inactive = $this->createEndpoint('Attachment Inactive', ['attachment.created'], false);
        $otherOnly = $this->createEndpoint('Attachment Other', ['message.created'], true);

        $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('safe.png', 10, 'image/png'),
        ])->assertCreated();

        $delivery = ChatWebhookDelivery::query()
            ->where('event', 'attachment.created')
            ->where('webhook_endpoint_id', $active->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($delivery);
        $this->assertSame(0, ChatWebhookDelivery::query()->where('event', 'attachment.created')->where('webhook_endpoint_id', $inactive->id)->count());
        $this->assertSame(0, ChatWebhookDelivery::query()->where('event', 'attachment.created')->where('webhook_endpoint_id', $otherOnly->id)->count());

        $payload = (array) ($delivery?->payload ?? []);
        $this->assertSame('attachment.created', data_get($payload, 'event'));
        $this->assertSame($conversation->id, data_get($payload, 'conversation_id'));
        $this->assertSame($message->id, data_get($payload, 'message_id'));
        $this->assertSame('safe.png', data_get($payload, 'original_name'));
        $this->assertSame('active', data_get($payload, 'status'));
        $this->assertNotNull(data_get($payload, 'attachment_id'));

        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertArrayNotHasKey('disk', $payload);
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('checksum', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('device_key', $payload);
        $this->assertArrayNotHasKey('user_agent', $payload);
        $this->assertArrayNotHasKey('ip_address', $payload);

        Bus::assertDispatched(DeliverChatWebhookJob::class);
    }
}


