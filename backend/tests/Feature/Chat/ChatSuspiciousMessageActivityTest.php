<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatSuspiciousMessageActivityTest extends TestCase
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
            'title' => 'Suspicious chat',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);
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

    public function test_normal_message_does_not_create_suspicious_log(): void
    {
        config()->set('chat.suspicious_activity.enabled', true);
        config()->set('chat.suspicious_activity.max_message_length', 5000);

        $sender = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $peer = User::factory()->create();
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $peer);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Normal safe message',
            'type' => 'text',
        ])->assertCreated();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'action' => 'suspicious.message_activity',
        ]);
    }

    public function test_suspicious_signal_creates_log_without_raw_content_and_send_succeeds(): void
    {
        config()->set('chat.suspicious_activity.enabled', true);
        config()->set('chat.suspicious_activity.max_message_length', 32);

        $sender = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $peer = User::factory()->create();
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $peer);

        $body = str_repeat('A', 40).' https://example.test';

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => $body,
            'type' => 'text',
        ])->assertCreated();

        $messageId = (int) $response->json('data.id');
        $log = ChatModerationLog::query()
            ->where('action', 'suspicious.message_activity')
            ->where('message_id', $messageId)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($sender->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($messageId, $log->message_id);
        $this->assertSame($sender->id, $log->target_user_id);
        $this->assertIsArray($log->metadata);
        $this->assertIsArray($log->metadata['signals'] ?? null);
        $this->assertContains('too_long_message', $log->metadata['signals'] ?? []);
        $this->assertContains('suspicious_link_placeholder', $log->metadata['signals'] ?? []);
        $this->assertArrayNotHasKey('body', $log->metadata ?? []);
        $this->assertArrayNotHasKey('content', $log->metadata ?? []);
        $this->assertArrayNotHasKey('raw_payload', $log->metadata ?? []);
        $this->assertArrayNotHasKey('token', $log->metadata ?? []);
        $this->assertArrayNotHasKey('secret', $log->metadata ?? []);
        $this->assertArrayNotHasKey('device_key', $log->metadata ?? []);
        $this->assertArrayNotHasKey('user_agent', $log->metadata ?? []);
        $this->assertArrayNotHasKey('ip_address', $log->metadata ?? []);
    }

    public function test_config_disabled_prevents_suspicious_log(): void
    {
        config()->set('chat.suspicious_activity.enabled', false);
        config()->set('chat.suspicious_activity.max_message_length', 16);

        $sender = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $peer = User::factory()->create();
        $conversation = $this->makeConversation($sender);
        $this->addParticipant($conversation, $sender, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $peer);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => str_repeat('X', 64),
            'type' => 'text',
        ])->assertCreated();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'action' => 'suspicious.message_activity',
        ]);
    }
}


