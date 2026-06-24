<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatTypingStarted;
use App\Events\Chat\ChatTypingStopped;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

class ChatTypingService
{
    public function __construct(
        protected ChatAccessService $accessService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function startTyping(User $user, Conversation $conversation, array $payload = []): void
    {
        if (! $this->canSendTyping($user, $conversation)) {
            throw new AuthorizationException('You are not allowed to send typing indicator in this conversation.');
        }

        $throttleSeconds = max((int) config('chat.typing.throttle_seconds', 2), 1);
        $key = $this->typingThrottleKey($conversation->id, $user->id, 'start');

        if (! Cache::add($key, true, now()->addSeconds($throttleSeconds))) {
            return;
        }

        event(new ChatTypingStarted(
            conversationId: $conversation->id,
            payload: $this->buildTypingPayload($user, $conversation, $payload, 'start')
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function stopTyping(User $user, Conversation $conversation, array $payload = []): void
    {
        if (! $this->canSendTyping($user, $conversation)) {
            throw new AuthorizationException('You are not allowed to send typing indicator in this conversation.');
        }

        event(new ChatTypingStopped(
            conversationId: $conversation->id,
            payload: $this->buildTypingPayload($user, $conversation, $payload, 'stop')
        ));
    }

    public function canSendTyping(User $user, Conversation $conversation): bool
    {
        if (! $conversation->isInCurrentTenant()) {
            return false;
        }

        if ($conversation->trashed()) {
            return false;
        }

        if ($conversation->status !== 'active') {
            return false;
        }

        if (! $user->hasAnyPermission(['chat.view', 'chat.conversations.view'])) {
            return false;
        }

        $participant = $this->accessService->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        if ($participant->status !== 'active') {
            return false;
        }

        if (in_array($participant->access_state, ['hidden', 'blocked', 'read_only'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function buildTypingPayload(User $user, Conversation $conversation, array $payload = [], string $state = 'start'): array
    {
        $timestampField = $state === 'stop' ? 'stopped_at' : 'started_at';

        return [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'device_type' => isset($payload['device_type']) ? (string) $payload['device_type'] : null,
            $timestampField => now()->toISOString(),
        ];
    }

    private function typingThrottleKey(int $conversationId, int $userId, string $state): string
    {
        $tenantId = app(\App\Services\Tenancy\TenantContext::class)->tenantId() ?? 'default';

        return "chat:typing:{$tenantId}:{$conversationId}:{$userId}:{$state}";
    }
}
