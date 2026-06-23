<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageUpdated;
use App\Models\ChatModerationLog;
use App\Models\ChatUserDevice;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDeviceRead;
use App\Models\MessageRead;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatSensitiveDataPolicyTest extends TestCase
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

    private function makeConversation(User $owner, array $overrides = []): Conversation
    {
        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Sensitive Policy Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ], $overrides));
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): void
    {
        ConversationParticipant::query()->create(array_merge([
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

    private function makeWebhookEndpoint(User $creator, array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sensitive Policy Endpoint',
            'url' => 'https://example.test/policy-webhook',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => ['token_hash' => 'hash'],
        ], $overrides));
    }

    private function toServerHeader(string $header): string
    {
        return strtoupper(str_replace('-', '_', $header));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function postSignedIncoming(ChatWebhookEndpoint $endpoint, array $payload)
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $signed = app(ChatWebhookSigningService::class)->signPayload($payloadJson, (string) $endpoint->secret);
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

    public function test_chat_sensitive_data_policy_foundation(): void
    {
        Storage::fake((string) config('chat.attachments.disk', 'local'));
        Event::fake([ChatMessageCreated::class, ChatMessageUpdated::class, ChatMessageDeleted::class]);

        $sender = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.attachments.upload',
            'chat.webhooks.view',
            'chat.admin.view_metadata',
            'chat.external_api.send',
        ]);

        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner', 'can_manage' => true]);
        $peer = User::factory()->create();
        $this->addParticipant($conversation, $peer);

        $this->makeWebhookEndpoint($sender, ['events' => ['message.created', 'message.updated', 'message.deleted']]);

        $send = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Visible message body',
            'type' => 'text',
        ])->assertCreated();
        $messageId = (int) $send->json('data.id');
        $message = Message::query()->findOrFail($messageId);

        $this->postJson("/api/v1/chat/messages/{$messageId}/attachments", [
            'file' => UploadedFile::fake()->create('report.pdf', 128, 'application/pdf'),
        ])->assertCreated();

        $message->refresh();
        $attachment = $message->attachments()->firstOrFail();

        $device = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sender->id,
            'device_key' => 'dev-key-sensitive',
            'device_name' => 'MacBook',
            'device_type' => 'desktop',
            'platform' => 'macos',
            'browser' => 'chrome',
            'app_version' => '1.0',
            'ip_address' => '10.0.0.9',
            'user_agent' => 'SensitiveAgent',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        MessageRead::query()->create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'read_at' => now(),
        ]);

        MessageDeviceRead::query()->create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'chat_user_device_id' => $device->id,
            'device_key' => 'dev-key-sensitive',
            'device_type' => 'desktop',
            'platform' => 'macos',
            'browser' => 'chrome',
            'read_at' => now(),
            'metadata' => ['user_agent' => 'SensitiveAgent', 'ip_address' => '10.0.0.9'],
        ]);

        $messagesResponse = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")->assertOk();
        $payload = (string) $messagesResponse->getContent();

        $this->assertStringContainsString('Visible message body', $payload);
        $this->assertStringNotContainsString('"disk"', $payload);
        $this->assertStringNotContainsString('"path"', $payload);
        $this->assertStringNotContainsString('"checksum"', $payload);
        $this->assertStringNotContainsString('"device_key"', $payload);
        $this->assertStringNotContainsString('"user_agent"', $payload);
        $this->assertStringNotContainsString('"ip_address"', $payload);
        $this->assertStringNotContainsString('"token_hash"', $payload);
        $this->assertStringNotContainsString('"secret"', $payload);
        $this->assertStringNotContainsString('"signature"', $payload);

        $deviceReads = (array) data_get($messagesResponse->json(), 'data.0.device_reads.0', []);
        $this->assertArrayHasKey('user_id', $deviceReads);
        $this->assertArrayHasKey('read_at', $deviceReads);
        $this->assertArrayHasKey('device_type', $deviceReads);
        $this->assertArrayNotHasKey('device_key', $deviceReads);
        $this->assertArrayNotHasKey('user_agent', $deviceReads);
        $this->assertArrayNotHasKey('ip_address', $deviceReads);

        $this->patchJson("/api/v1/chat/messages/{$messageId}", ['body' => 'Edited body'])->assertOk();
        $this->deleteJson("/api/v1/chat/messages/{$messageId}")->assertOk();

        $moderation = ChatModerationLog::query()
            ->whereIn('action', ['message.created', 'message.updated', 'message.deleted', 'suspicious.message_activity'])
            ->get();

        foreach ($moderation as $log) {
            $meta = (array) ($log->metadata ?? []);
            $this->assertArrayNotHasKey('body', $meta);
            $this->assertArrayNotHasKey('content', $meta);
            $this->assertArrayNotHasKey('raw_payload', $meta);
            $this->assertArrayNotHasKey('raw_response', $meta);
            $this->assertArrayNotHasKey('token', $meta);
            $this->assertArrayNotHasKey('secret', $meta);
            $this->assertArrayNotHasKey('signature', $meta);
            $this->assertArrayNotHasKey('disk', $meta);
            $this->assertArrayNotHasKey('path', $meta);
            $this->assertArrayNotHasKey('checksum', $meta);
        }

        $webhookDelivery = ChatWebhookDelivery::query()->where('event', 'message.created')->latest('id')->first();
        $this->assertNotNull($webhookDelivery);
        $deliveryPayload = (array) ($webhookDelivery?->payload ?? []);
        $this->assertArrayNotHasKey('raw_payload', $deliveryPayload);
        $this->assertArrayNotHasKey('token', $deliveryPayload);
        $this->assertArrayNotHasKey('secret', $deliveryPayload);
        $this->assertArrayNotHasKey('signature', $deliveryPayload);
        $this->assertArrayNotHasKey('disk', $deliveryPayload);
        $this->assertArrayNotHasKey('path', $deliveryPayload);
        $this->assertArrayNotHasKey('checksum', $deliveryPayload);

        Event::assertDispatched(ChatMessageCreated::class, function (ChatMessageCreated $event): bool {
            $encoded = json_encode($event->payload);
            return ! str_contains((string) $encoded, 'raw_payload')
                && ! str_contains((string) $encoded, 'token')
                && ! str_contains((string) $encoded, 'secret')
                && ! str_contains((string) $encoded, 'device_key')
                && ! str_contains((string) $encoded, 'user_agent')
                && ! str_contains((string) $encoded, 'ip_address')
                && ! str_contains((string) $encoded, 'disk')
                && ! str_contains((string) $encoded, 'path')
                && ! str_contains((string) $encoded, 'checksum');
        });
        Event::assertDispatched(ChatMessageUpdated::class);
        Event::assertDispatched(ChatMessageDeleted::class);

        ChatWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $webhookDelivery->webhook_endpoint_id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'event' => 'message.created',
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => ['event' => 'message.created'],
            'status' => 'failed',
            'attempts' => 1,
            'response_status' => 500,
            'response_body' => ['token' => 'x', 'secret' => 'y', 'signature' => 'z'],
            'error_message' => 'failed',
        ]);

        $deliverySummary = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/webhook-deliveries")->assertOk();
        $deliverySummaryContent = (string) $deliverySummary->getContent();
        $this->assertStringNotContainsString('response_body', $deliverySummaryContent);
        $this->assertStringNotContainsString('token_hash', $deliverySummaryContent);
        $this->assertStringNotContainsString('webhook_secret', $deliverySummaryContent);
        $this->assertStringNotContainsString('"signature"', $deliverySummaryContent);

        $incomingEndpoint = $this->makeWebhookEndpoint($sender, ['events' => ['message.created']]);
        $incomingPayload = [
            'event' => 'message.created',
            'conversation_id' => $conversation->id,
            'external_provider' => 'provider-b',
            'external_message_id' => 'incoming-sensitive-1',
            'body' => 'Incoming payload body',
            'type' => 'text',
            'metadata' => ['token' => 'sensitive', 'user_agent' => 'ua', 'ip_address' => '127.0.0.1'],
            'idempotency_key' => 'incoming-sensitive-idem-1',
        ];
        $incomingResponse = $this->postSignedIncoming($incomingEndpoint, $incomingPayload)->assertCreated();
        $incomingResponseContent = (string) $incomingResponse->getContent();
        $this->assertStringNotContainsString('token_hash', $incomingResponseContent);
        $this->assertStringNotContainsString('secret', $incomingResponseContent);
        $this->assertStringNotContainsString('signature', $incomingResponseContent);
    }
}
