<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatTypingStarted;
use App\Events\Chat\ChatTypingStopped;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTypingIndicatorApiTest extends TestCase
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
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Typing Chat',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
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

    public function test_chat_typing_indicator_api_foundation(): void
    {
        Cache::flush();
        Event::fake([ChatTypingStarted::class, ChatTypingStopped::class]);
        $tenantId = app(TenantContext::class)->tenantId() ?? 'default';

        $owner = User::factory()->create();
        $active = User::factory()->create();
        $conversation = $this->makeConversation($owner);
        $this->addParticipant($conversation, $owner, ['role' => 'owner']);
        $this->addParticipant($conversation, $active);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [])
            ->assertUnauthorized();

        $actor = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $active->id)->delete();
        $this->addParticipant($conversation, $actor);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [
            'device_type' => 'browser',
            'device_key' => 'typing-device-1',
        ])->assertOk();
        Event::assertDispatched(ChatTypingStarted::class);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/stop", [
            'device_type' => 'browser',
        ])->assertOk();
        Event::assertDispatched(ChatTypingStopped::class);

        $hiddenUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $hiddenUser, ['access_state' => 'hidden']);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start")
            ->assertForbidden();

        $blockedUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $blockedUser, [
            'status' => 'blocked',
            'access_state' => 'blocked',
            'block_display_mode' => 'show_notice',
        ]);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start")
            ->assertForbidden();

        $readOnlyUser = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $readOnlyUser, ['access_state' => 'read_only']);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start")
            ->assertForbidden();

        $closedConversation = $this->makeConversation($owner, ['status' => 'closed']);
        $this->addParticipant($closedConversation, $readOnlyUser, ['access_state' => 'full']);
        Sanctum::actingAs($readOnlyUser);
        $this->postJson("/api/v1/chat/conversations/{$closedConversation->id}/typing/start")
            ->assertForbidden();

        $archivedConversation = $this->makeConversation($owner, ['status' => 'archived']);
        $this->addParticipant($archivedConversation, $readOnlyUser, ['access_state' => 'full']);
        $this->postJson("/api/v1/chat/conversations/{$archivedConversation->id}/typing/start")
            ->assertForbidden();

        $deletedConversation = $this->makeConversation($owner);
        $this->addParticipant($deletedConversation, $readOnlyUser, ['access_state' => 'full']);
        $deletedConversation->delete();
        $this->postJson("/api/v1/chat/conversations/{$deletedConversation->id}/typing/start")
            ->assertNotFound();

        Sanctum::actingAs($actor);
        Cache::flush();
        Event::fake([ChatTypingStarted::class, ChatTypingStopped::class]);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [
            'device_type' => 'browser',
        ])->assertOk();
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [
            'device_type' => 'browser',
        ])->assertOk();
        Event::assertDispatchedTimes(ChatTypingStarted::class, 1);

        Cache::forget("chat:typing:{$tenantId}:{$conversation->id}:{$actor->id}:start");
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/start", [
            'device_type' => 'browser',
        ])->assertOk();
        Event::assertDispatchedTimes(ChatTypingStarted::class, 2);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/typing/stop", [
            'device_type' => 'browser',
        ])->assertOk();

        Event::assertDispatched(ChatTypingStarted::class, function (ChatTypingStarted $event): bool {
            return ! array_key_exists('email', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('permissions', $event->payload)
                && ! array_key_exists('blocked_reason', $event->payload);
        });

        Event::assertDispatched(ChatTypingStopped::class, function (ChatTypingStopped $event): bool {
            return ! array_key_exists('email', $event->payload)
                && ! array_key_exists('ip_address', $event->payload)
                && ! array_key_exists('user_agent', $event->payload)
                && ! array_key_exists('metadata', $event->payload)
                && ! array_key_exists('permissions', $event->payload)
                && ! array_key_exists('blocked_reason', $event->payload);
        });

        $this->assertSame('realtime', (new ChatTypingStarted($conversation->id, []))->broadcastQueue);
        $this->assertSame('realtime', (new ChatTypingStopped($conversation->id, []))->broadcastQueue);
    }
}
