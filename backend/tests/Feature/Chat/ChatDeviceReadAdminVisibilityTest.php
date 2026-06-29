<?php

namespace Tests\Feature\Chat;

use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDeviceRead;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatDeviceReadAdminVisibilityTest extends TestCase
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
            'title' => 'Device visibility',
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

    private function makeMessage(Conversation $conversation, User $sender): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Admin visibility message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function test_admin_sees_safe_device_level_read_rows_but_normal_user_does_not(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $owner, ['role' => 'owner', 'can_manage' => true]);
        $this->addParticipant($conversation, $reader);
        $message = $this->makeMessage($conversation, $owner);

        $device = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $reader->id,
            'device_key' => 'reader-device-key',
            'device_name' => 'Reader browser',
            'device_type' => 'browser',
            'platform' => 'Windows',
            'browser' => 'Chrome',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Sensitive user agent',
            'is_active' => true,
            'last_seen_at' => now(),
            'metadata' => ['token' => 'secret'],
        ]);

        MessageDeviceRead::query()->create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'user_id' => $reader->id,
            'chat_user_device_id' => $device->id,
            'device_key' => $device->device_key,
            'device_type' => $device->device_type,
            'platform' => $device->platform,
            'browser' => $device->browser,
            'read_at' => now(),
            'metadata' => ['ip_address' => '127.0.0.1', 'user_agent' => 'Sensitive user agent'],
        ]);

        $admin = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view', 'chat.admin.view_metadata']);
        $this->addParticipant($conversation, $admin, ['role' => 'support']);

        $adminResponse = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk();

        $adminRows = $adminResponse->json('data');
        $this->assertIsArray($adminRows);
        $this->assertNotEmpty($adminRows);
        $first = $adminRows[0];

        $this->assertArrayHasKey('device_read_count', $first);
        $this->assertArrayHasKey('device_reads', $first);
        $this->assertSame(1, (int) $first['device_read_count']);
        $this->assertIsArray($first['device_reads']);
        $this->assertCount(1, $first['device_reads']);
        $this->assertSame($reader->id, $first['device_reads'][0]['user_id']);
        $this->assertSame('browser', $first['device_reads'][0]['device_type']);
        $this->assertNotEmpty($first['device_reads'][0]['read_at']);
        $this->assertArrayNotHasKey('device_key', $first['device_reads'][0]);
        $this->assertArrayNotHasKey('user_agent', $first['device_reads'][0]);
        $this->assertArrayNotHasKey('ip_address', $first['device_reads'][0]);
        $this->assertArrayNotHasKey('metadata', $first['device_reads'][0]);
        $this->assertStringNotContainsString('reader-device-key', json_encode($first));
        $this->assertStringNotContainsString('Sensitive user agent', json_encode($first));

        $normalUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $normalUser, ['role' => 'member']);

        $normalResponse = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertOk();

        $normalRows = $normalResponse->json('data');
        $this->assertIsArray($normalRows);
        $this->assertNotEmpty($normalRows);
        $normalFirst = $normalRows[0];
        $this->assertArrayNotHasKey('device_read_count', $normalFirst);
        $this->assertArrayNotHasKey('device_reads', $normalFirst);
    }

    public function test_hidden_or_non_participant_user_cannot_access_message_list(): void
    {
        $owner = User::factory()->create();
        $hiddenUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $owner, ['role' => 'owner']);
        $this->addParticipant($conversation, $hiddenUser, [
            'status' => 'active',
            'access_state' => 'hidden',
        ]);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertNotFound();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);

        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages")
            ->assertNotFound();
    }
}

