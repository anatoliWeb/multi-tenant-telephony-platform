<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatUserLeftConversation;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

class ChatPresenceService
{
    public function __construct(
        protected ChatAccessService $accessService,
    ) {
    }

    public function canJoinPresence(User $user, Conversation $conversation): bool
    {
        if ($conversation->trashed()) {
            return false;
        }

        if ($this->accessService->canViewAdminMetadata($user) && $user->hasPermission('chat.admin.view')) {
            return true;
        }

        $participant = $this->accessService->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        if ($participant->status !== 'active') {
            return false;
        }

        if ($participant->access_state === 'hidden' || $participant->access_state === 'blocked') {
            return false;
        }

        return in_array($participant->access_state, ['full', 'read_only'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPresencePayload(User $user, Conversation $conversation, ?ChatUserDevice $device = null): array
    {
        $participant = $this->accessService->getParticipant($conversation, $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => null,
            'role' => $participant?->role,
            'device_type' => $device?->device_type,
        ];
    }

    public function markUserPresenceSeen(User $user, ?string $deviceKey = null): ?ChatUserDevice
    {
        if (! is_string($deviceKey) || trim($deviceKey) === '') {
            return null;
        }

        $device = ChatUserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_key', $deviceKey)
            ->first();

        if (! $device) {
            return null;
        }

        $device->last_seen_at = now();
        $device->is_active = true;
        $device->save();

        return $device->fresh();
    }

    public function markUserPresenceLeft(User $user, Conversation $conversation, ?string $deviceKey = null): void
    {
        if (! $this->canJoinPresence($user, $conversation)) {
            throw new AuthorizationException('You are not allowed to leave presence for this conversation.');
        }

        $this->markUserPresenceSeen($user, $deviceKey);

        event(new ChatUserLeftConversation(
            conversationId: $conversation->id,
            payload: [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'left_at' => now()->toISOString(),
            ]
        ));
    }

    public function cleanupStalePresence(?int $olderThanSeconds = null): int
    {
        $thresholdSeconds = $olderThanSeconds ?? $this->getPresenceStaleThresholdSeconds();
        $thresholdSeconds = max($thresholdSeconds, 1);
        $cutoff = Carbon::now()->subSeconds($thresholdSeconds);

        return ChatUserDevice::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $cutoff)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function getPresenceStaleThresholdSeconds(): int
    {
        return max((int) config('chat.presence.stale_after_seconds', 120), 1);
    }
}
