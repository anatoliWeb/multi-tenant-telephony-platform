<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Realtime Logging',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
        ]);
    }

    public function test_denied_private_chat_channel_auth_writes_safe_warning_log(): void
    {
        config([
            'logging.realtime.enabled' => true,
            'logging.realtime.channel_auth_failures' => true,
        ]);
        Log::spy();

        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
            'device_key' => 'must-not-log',
        ])->assertForbidden();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($outsider, $conversation): bool {
                return $message === 'realtime.channel.auth.denied'
                    && data_get($context, 'channel_name') === 'chat.conversation.{conversationId}'
                    && data_get($context, 'channel_type') === 'private'
                    && (int) data_get($context, 'user_id') === $outsider->id
                    && (int) data_get($context, 'conversation_id') === $conversation->id
                    && data_get($context, 'status') === 'denied'
                    && ! array_key_exists('token', $context)
                    && ! array_key_exists('authorization', $context)
                    && ! array_key_exists('cookie', $context)
                    && ! array_key_exists('signature', $context)
                    && ! array_key_exists('body', $context)
                    && ! array_key_exists('raw_payload', $context)
                    && ! array_key_exists('device_key', $context)
                    && ! array_key_exists('user_agent', $context)
                    && ! array_key_exists('ip_address', $context);
            })
            ->once();
    }

    public function test_allowed_channel_auth_does_not_create_noisy_info_log_by_default(): void
    {
        config([
            'logging.realtime.enabled' => true,
            'logging.realtime.channel_auth_failures' => true,
        ]);
        Log::spy();

        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);
        $participant = User::factory()->create();
        $permissionIds = collect(['chat.view', 'chat.conversations.view'])
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $participant->permissions()->sync($permissionIds);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $participant->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($participant);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => 'private-chat.conversation.'.$conversation->id,
        ])->assertOk();

        Log::shouldNotHaveReceived('info', [
            'realtime.channel.auth.authorized',
            \Mockery::type('array'),
        ]);
    }

    public function test_realtime_logging_can_be_disabled_via_config(): void
    {
        config([
            'logging.realtime.enabled' => false,
            'logging.realtime.channel_auth_failures' => true,
        ]);
        Log::spy();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1.3',
            'channel_name' => 'presence-presence-page.bad/segment',
        ])->assertForbidden();

        Log::shouldNotHaveReceived('warning', [
            'realtime.channel.auth.denied',
            \Mockery::type('array'),
        ]);
    }
}
