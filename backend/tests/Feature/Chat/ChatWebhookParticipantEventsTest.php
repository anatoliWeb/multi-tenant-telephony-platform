<?php

namespace Tests\Feature\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookParticipantEventsTest extends TestCase
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
            'title' => 'Webhook Participants',
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

    public function test_participant_webhook_events_are_queued_with_safe_payloads(): void
    {
        Bus::fake();

        $manager = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
            'chat.admin.moderate',
            'chat.send',
        ]);
        $conversation = $this->makeConversation($manager);
        $this->addParticipant($conversation, $manager, [
            'role' => 'owner',
            'can_invite' => true,
            'can_remove' => true,
            'can_manage' => true,
            'can_moderate' => true,
        ]);

        $joinUser = User::factory()->create();
        $blockUser = User::factory()->create();
        $leaveUser = User::factory()->create();
        $this->addParticipant($conversation, $blockUser);
        $this->addParticipant($conversation, $leaveUser);

        $active = $this->createEndpoint('Participant Active', [
            'participant.joined',
            'participant.left',
            'participant.blocked',
            'participant.unblocked',
        ], true);
        $inactive = $this->createEndpoint('Participant Inactive', [
            'participant.joined',
            'participant.left',
            'participant.blocked',
            'participant.unblocked',
        ], false);
        $otherOnly = $this->createEndpoint('Participant Other', ['message.created'], true);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/participants", [
            'user_id' => $joinUser->id,
            'role' => 'member',
        ])->assertCreated();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'participant.joined')->where('webhook_endpoint_id', $active->id)->count());

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$blockUser->id}/block", [
            'block_display_mode' => 'show_notice',
            'blocked_reason' => 'do not expose',
        ])->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'participant.blocked')->where('webhook_endpoint_id', $active->id)->count());

        $this->patchJson("/api/v1/chat/conversations/{$conversation->id}/participants/{$blockUser->id}/unblock")
            ->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'participant.unblocked')->where('webhook_endpoint_id', $active->id)->count());

        Sanctum::actingAs($leaveUser);
        $leaveUser->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
        ]);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/leave")->assertOk();
        $this->assertSame(1, ChatWebhookDelivery::query()->where('event', 'participant.left')->where('webhook_endpoint_id', $active->id)->count());

        $this->assertSame(0, ChatWebhookDelivery::query()->where('webhook_endpoint_id', $inactive->id)->count());
        $this->assertSame(0, ChatWebhookDelivery::query()->where('webhook_endpoint_id', $otherOnly->id)->count());

        $payload = (array) ChatWebhookDelivery::query()
            ->where('event', 'participant.blocked')
            ->where('webhook_endpoint_id', $active->id)
            ->latest('id')
            ->value('payload');

        $this->assertSame('participant.blocked', data_get($payload, 'event'));
        $this->assertSame($conversation->id, data_get($payload, 'conversation_id'));
        $this->assertSame($blockUser->id, data_get($payload, 'target_user_id'));
        $this->assertSame($manager->id, data_get($payload, 'actor_id'));
        $this->assertSame('blocked', data_get($payload, 'access_state'));

        $this->assertArrayNotHasKey('blocked_reason', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('device_key', $payload);
        $this->assertArrayNotHasKey('user_agent', $payload);
        $this->assertArrayNotHasKey('ip_address', $payload);

        Bus::assertDispatched(DeliverChatWebhookJob::class);
    }
}

