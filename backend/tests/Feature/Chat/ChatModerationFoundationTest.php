<?php

namespace Tests\Feature\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatModerationFoundationTest extends TestCase
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

    private function makeConversation(array $overrides = []): Conversation
    {
        $owner = $overrides['owner'] ?? User::factory()->create();
        unset($overrides['owner']);

        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Moderation',
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
        ], $overrides));
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): ConversationParticipant
    {
        return ConversationParticipant::query()->create(array_merge([
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
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, User $sender, array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'message body',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => [
                'token' => 'do-not-log',
                'source' => 'safe',
            ],
        ], $overrides));
    }

    public function test_admin_message_delete_creates_moderation_log_with_safe_metadata(): void
    {
        $admin = $this->actingAsWithPermissions([
            'chat.delete',
            'chat.admin.delete_messages',
            'chat.admin.moderate',
        ]);

        $conversation = $this->makeConversation(['owner' => $admin]);
        $sender = User::factory()->create();
        $this->addParticipant($conversation, $admin, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $this->addParticipant($conversation, $sender);

        $message = $this->makeMessage($conversation, $sender, [
            'type' => 'system',
            'is_imported' => true,
        ]);

        $this->deleteJson("/api/v1/chat/messages/{$message->id}")
            ->assertOk();

        $log = ChatModerationLog::query()
            ->where('action', 'message.deleted')
            ->where('message_id', $message->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($message->id, $log->message_id);
        $this->assertSame($sender->id, $log->target_user_id);

        $metadata = $log->metadata ?? [];
        $this->assertSame('system', $metadata['message_type'] ?? null);
        $this->assertSame('internal', $metadata['conversation_source'] ?? null);
        $this->assertSame(true, $metadata['was_imported'] ?? null);
        $this->assertArrayNotHasKey('token', $metadata);
        $this->assertArrayNotHasKey('secret', $metadata);
        $this->assertArrayNotHasKey('raw_payload', $metadata);
        $this->assertArrayNotHasKey('device_key', $metadata);
        $this->assertArrayNotHasKey('user_agent', $metadata);
        $this->assertArrayNotHasKey('ip_address', $metadata);
    }

    public function test_unauthorized_delete_does_not_create_moderation_log(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeConversation(['owner' => $owner]);
        $sender = User::factory()->create();
        $outsider = $this->actingAsWithPermissions(['chat.delete']);

        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true, 'can_moderate' => true]);
        $this->addParticipant($conversation, $sender);
        $message = $this->makeMessage($conversation, $sender);

        $this->deleteJson("/api/v1/chat/messages/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_moderation_logs', [
            'message_id' => $message->id,
            'action' => 'message.deleted',
            'actor_id' => $outsider->id,
        ]);
    }

    public function test_moderation_service_sanitizes_unsafe_metadata(): void
    {
        /** @var ChatModerationService $service */
        $service = app(ChatModerationService::class);

        $sanitized = $service->sanitizeMetadata([
            'source' => 'safe',
            'token' => 'secret-token',
            'signature' => 'sig',
            'nested' => [
                'ip_address' => '127.0.0.1',
                'status' => 'ok',
                'raw_response' => '{"secret":"x"}',
            ],
            'message_type' => 'text',
        ]);

        $this->assertSame('safe', $sanitized['source'] ?? null);
        $this->assertSame('text', $sanitized['message_type'] ?? null);
        $this->assertArrayNotHasKey('token', $sanitized);
        $this->assertArrayNotHasKey('signature', $sanitized);
        $this->assertSame('ok', $sanitized['nested']['status'] ?? null);
        $this->assertArrayNotHasKey('ip_address', $sanitized['nested'] ?? []);
        $this->assertArrayNotHasKey('raw_response', $sanitized['nested'] ?? []);
    }
}

