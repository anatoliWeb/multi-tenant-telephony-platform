<?php

namespace Tests\Feature\Chat;

use App\Models\ActivityLog;
use App\Models\ChatModerationLog;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatActivityIntegrationService;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatActivityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Activity integration',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'history_import_from_message_id' => null,
            'history_import_from_at' => null,
            'last_message_id' => null,
            'last_message_at' => null,
            'metadata' => null,
        ]);
    }

    private function addParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        return ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ]);
    }

    private function makeMessage(Conversation $conversation, User $sender): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'message body should never be copied to activity logs',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => null,
        ]);
    }

    public function test_high_signal_actions_are_written_to_activity_log(): void
    {
        $actor = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = $this->makeConversation($actor);
        $message = $this->makeMessage($conversation, $sender);
        $participant = $this->addParticipant($conversation, $sender);

        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);

        $service->logMessageDeleted($actor, $message, null, [
            'message_type' => 'text',
            'token' => 'strip-me',
        ]);

        $service->logConversationClosed($actor, $conversation);
        $service->logParticipantBlocked($actor, $participant);
        $service->logSuspiciousMessageActivity($actor, $message, [
            'signals' => ['too_long_message'],
            'signal_count' => 1,
            'raw_payload' => ['secret' => 'x'],
        ]);

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Activity endpoint',
            'url' => 'https://example.test/webhook',
            'secret' => 'hidden-secret',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'failure_count' => 0,
            'created_by' => $actor->id,
            'metadata' => null,
        ]);

        $delivery = ChatWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'event' => 'message.created',
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => ['message_id' => $message->id],
            'signature' => 'sig',
            'status' => 'failed',
            'attempts' => 3,
            'failed_at' => now(),
            'response_status' => 500,
            'response_body' => ['error' => 'internal'],
            'error_message' => 'failed',
            'metadata' => null,
        ]);

        $service->logWebhookDeliveryFailed($delivery, ['secret' => 'do-not-log']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'message.deleted']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'conversation.closed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'participant.blocked']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'suspicious.message_activity']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'webhook.delivery.failed']);

        $activity = ActivityLog::query()
            ->where('action', 'message.deleted')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($actor->id, $activity->user_id);
        $this->assertSame('chat', data_get($activity->meta, 'source'));
        $this->assertSame('chat', data_get($activity->meta, 'module'));
        $this->assertNotNull(data_get($activity->meta, 'chat_moderation_log_id'));
        $this->assertNull(data_get($activity->meta, 'chat.token'));
        $this->assertNull(data_get($activity->meta, 'chat.secret'));
        $this->assertNull(data_get($activity->meta, 'chat.raw_payload'));
    }

    public function test_noisy_action_message_created_is_not_written_to_activity_log(): void
    {
        $actor = User::factory()->create();
        $conversation = $this->makeConversation($actor);
        $message = $this->makeMessage($conversation, $actor);

        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);
        $service->logMessageCreated($actor, $message);

        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'message.created',
        ]);
    }

    public function test_config_can_disable_activity_integration(): void
    {
        config()->set('chat.activity_integration.enabled', false);

        $actor = User::factory()->create();
        $conversation = $this->makeConversation($actor);
        $message = $this->makeMessage($conversation, $actor);

        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);
        $service->logMessageDeleted($actor, $message);

        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'message.deleted',
        ]);
    }

    public function test_activity_integration_failure_does_not_break_moderation_log_creation(): void
    {
        $mock = Mockery::mock(ChatActivityIntegrationService::class);
        $mock->shouldReceive('recordFromModerationLog')
            ->once()
            ->andThrow(new \RuntimeException('activity write failed'));
        $this->app->instance(ChatActivityIntegrationService::class, $mock);

        $actor = User::factory()->create();
        $conversation = $this->makeConversation($actor);
        $message = $this->makeMessage($conversation, $actor);

        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);
        $log = $service->logMessageDeleted($actor, $message);

        $this->assertInstanceOf(ChatModerationLog::class, $log);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'id' => $log->id,
            'action' => 'message.deleted',
        ]);
    }
}
