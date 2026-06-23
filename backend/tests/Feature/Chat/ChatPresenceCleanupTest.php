<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatUserLeftConversation;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatPresenceCleanupTest extends TestCase
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
            'title' => 'Presence Cleanup Chat',
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

    public function test_chat_presence_cleanup_foundation(): void
    {
        Event::fake([ChatUserLeftConversation::class]);

        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/presence/leave", [])
            ->assertUnauthorized();

        $active = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $active);

        $device = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $active->id,
            'device_key' => 'presence-cleanup-device-1',
            'device_type' => 'browser',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($active);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/presence/leave", [
            'device_key' => 'presence-cleanup-device-1',
        ])->assertOk();

        Event::assertDispatched(ChatUserLeftConversation::class, function (ChatUserLeftConversation $event) use ($conversation, $active): bool {
            return $event->conversationId === $conversation->id
                && data_get($event->payload, 'conversation_id') === $conversation->id
                && data_get($event->payload, 'user_id') === $active->id
                && ! array_key_exists('email', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('permissions', $event->payload)
                && ! array_key_exists('blocked_reason', $event->payload);
        });

        $this->assertNotNull($device->fresh()?->last_seen_at);
        $this->assertTrue($device->fresh()?->last_seen_at?->gt(now()->subMinute()) ?? false);

        $hidden = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hidden, ['access_state' => 'hidden']);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/presence/leave")
            ->assertForbidden();

        $blocked = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blocked, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/presence/leave")
            ->assertForbidden();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/presence/leave")
            ->assertForbidden();

        $staleDevice = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $active->id,
            'device_key' => 'presence-stale-device',
            'device_type' => 'desktop',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(10),
        ]);
        $freshDevice = ChatUserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $active->id,
            'device_key' => 'presence-fresh-device',
            'device_type' => 'mobile',
            'is_active' => true,
            'last_seen_at' => now()->subSeconds(10),
        ]);

        /** @var ChatPresenceService $presenceService */
        $presenceService = app(ChatPresenceService::class);
        $affected = $presenceService->cleanupStalePresence(120);
        $this->assertSame(1, $affected);

        $this->assertFalse((bool) $staleDevice->fresh()?->is_active);
        $this->assertTrue((bool) $freshDevice->fresh()?->is_active);

        $this->artisan('chat:presence:cleanup', ['--older-than-seconds' => 120])
            ->expectsOutputToContain('Chat Presence Cleanup')
            ->expectsOutputToContain('devices_marked_inactive')
            ->assertExitCode(0);
    }
}
