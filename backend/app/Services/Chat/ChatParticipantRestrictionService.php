<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatParticipantAccessChanged;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatParticipantRestrictionService
{
    private const BLOCK_DISPLAY_MODES = ['hide_chat', 'show_notice', 'show_read_only_history'];
    private const ACCESS_STATES = ['full', 'read_only', 'hidden', 'blocked'];

    public function __construct(
        protected ChatAccessService $accessService,
        protected ChatModerationService $chatModerationService,
        protected ChatWebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    public function updateParticipantAccess(User $actor, Conversation $conversation, User $targetUser, array $payload): ConversationParticipant
    {
        $this->assertCanManageParticipant($actor, $conversation);
        $participant = $this->getParticipantOrFail($conversation, $targetUser);

        $accessState = (string) ($payload['access_state'] ?? '');
        if (! in_array($accessState, self::ACCESS_STATES, true)) {
            throw ValidationException::withMessages([
                'access_state' => ['Invalid access state.'],
            ]);
        }

        if ($participant->role === 'owner' && $this->isLastOwner($conversation, $participant)) {
            if (in_array($accessState, ['hidden', 'blocked'], true)) {
                throw ValidationException::withMessages([
                    'participant' => ['Cannot restrict the last owner with hidden/blocked state.'],
                ]);
            }
        }

        if ($accessState === 'blocked' && ! in_array($payload['block_display_mode'] ?? null, self::BLOCK_DISPLAY_MODES, true)) {
            throw ValidationException::withMessages([
                'block_display_mode' => ['block_display_mode is required for blocked access_state.'],
            ]);
        }

        return DB::transaction(function () use ($participant, $payload, $accessState, $conversation, $actor): ConversationParticipant {
            $oldValues = $participant->only([
                'status',
                'access_state',
                'block_display_mode',
                'can_send',
                'can_attach',
                'history_visible_from_message_id',
                'history_visible_from_at',
                'history_visible_until_message_id',
                'history_visible_until_at',
            ]);
            $oldAccessState = (string) ($participant->access_state ?? 'full');
            $oldStatus = (string) ($participant->status ?? 'active');

            if ($accessState === 'blocked') {
                $participant->status = 'blocked';
                $participant->blocked_by = $actor->id;
                $participant->blocked_at = now();
                $participant->blocked_reason = $payload['blocked_reason'] ?? null;
                $participant->block_display_mode = $payload['block_display_mode'];
            } else {
                if ($participant->status === 'blocked') {
                    $participant->status = 'active';
                }
                $participant->block_display_mode = null;
                $participant->blocked_by = null;
                $participant->blocked_at = null;
                $participant->blocked_reason = null;
            }

            $participant->access_state = $accessState;
            $participant->history_visible_from_message_id = $payload['history_visible_from_message_id'] ?? null;
            $participant->history_visible_from_at = $payload['history_visible_from_at'] ?? null;
            $participant->history_visible_until_message_id = $payload['history_visible_until_message_id'] ?? null;
            $participant->history_visible_until_at = $payload['history_visible_until_at'] ?? null;

            if ($accessState === 'read_only') {
                $participant->can_send = false;
                $participant->can_attach = false;
            }

            if ($accessState === 'blocked') {
                $participant->can_send = false;
                $participant->can_attach = false;
                $participant->can_invite = false;
                $participant->can_remove = false;
                $participant->can_manage = false;
                $participant->can_moderate = false;
            }

            $participant->save();

            $updated = $participant->fresh();
            $newAccessState = (string) ($updated->access_state ?? 'full');
            $action = match (true) {
                $newAccessState === 'read_only' => 'participant.read_only',
                $newAccessState === 'hidden' => 'participant.hidden',
                $newAccessState === 'blocked' => 'participant.blocked',
                $oldAccessState === 'hidden' && $newAccessState === 'full' => 'participant.visible_restored',
                $oldAccessState === 'blocked' && $newAccessState === 'full' => 'participant.unblocked',
                $newAccessState === 'full' => 'participant.full_access_restored',
                default => 'participant.access_changed',
            };

            $this->chatModerationService->logParticipantAccessChanged(
                actor: $actor,
                participant: $updated,
                oldState: $oldAccessState,
                newState: $newAccessState,
                metadata: [
                    'restriction_source' => 'participant_restriction',
                    'conversation_id' => $conversation->id,
                    'participant_id' => $updated->id,
                    'target_user_id' => $updated->user_id,
                    'old_status' => $oldStatus,
                    'new_status' => (string) ($updated->status ?? 'active'),
                    'old_role' => (string) ($oldValues['role'] ?? $updated->role ?? 'member'),
                    'new_role' => (string) ($updated->role ?? 'member'),
                    'previous_value' => ['access_state' => $oldAccessState],
                    'new_value' => ['access_state' => $newAccessState],
                ],
                action: $action,
            );
            $this->dispatchParticipantRealtimeEvent($conversation, $actor, $updated, 'access_updated');

            return $updated;
        });
    }

    public function blockParticipant(User $actor, Conversation $conversation, User $targetUser, array $payload): ConversationParticipant
    {
        $this->assertCanManageParticipant($actor, $conversation);
        $participant = $this->getParticipantOrFail($conversation, $targetUser);

        if ($participant->role === 'owner' && $this->isLastOwner($conversation, $participant)) {
            throw ValidationException::withMessages([
                'participant' => ['Cannot block the last owner.'],
            ]);
        }

        $blockDisplayMode = (string) ($payload['block_display_mode'] ?? '');
        if (! in_array($blockDisplayMode, self::BLOCK_DISPLAY_MODES, true)) {
            throw ValidationException::withMessages([
                'block_display_mode' => ['Invalid block display mode.'],
            ]);
        }

        return DB::transaction(function () use ($participant, $actor, $payload, $blockDisplayMode, $conversation): ConversationParticipant {
            $oldValues = $participant->only([
                'status',
                'access_state',
                'block_display_mode',
                'can_invite',
                'can_remove',
                'can_send',
                'can_attach',
                'can_manage',
                'can_moderate',
            ]);
            $oldAccessState = (string) ($participant->access_state ?? 'full');

            $participant->status = 'blocked';
            $participant->access_state = 'blocked';
            $participant->block_display_mode = $blockDisplayMode;
            $participant->blocked_by = $actor->id;
            $participant->blocked_at = now();
            $participant->blocked_reason = $payload['blocked_reason'] ?? null;
            $participant->history_visible_until_message_id = $payload['history_visible_until_message_id'] ?? null;
            $participant->history_visible_until_at = $payload['history_visible_until_at'] ?? null;
            $participant->can_invite = false;
            $participant->can_remove = false;
            $participant->can_send = false;
            $participant->can_attach = false;
            $participant->can_manage = false;
            $participant->can_moderate = false;
            $participant->save();

            $updated = $participant->fresh();
            $this->chatModerationService->logParticipantBlocked(
                actor: $actor,
                participant: $updated,
                reason: null,
                metadata: [
                    'restriction_source' => 'participant_restriction',
                    'conversation_id' => $conversation->id,
                    'participant_id' => $updated->id,
                    'target_user_id' => $updated->user_id,
                    'old_access_state' => $oldAccessState,
                    'new_access_state' => 'blocked',
                    'old_status' => (string) ($oldValues['status'] ?? 'active'),
                    'new_status' => (string) ($updated->status ?? 'blocked'),
                    'previous_value' => ['access_state' => $oldAccessState],
                    'new_value' => ['access_state' => 'blocked'],
                    'block_display_mode' => (string) $blockDisplayMode,
                ]
            );
            $this->webhookDeliveryService->queueEvent(
                'participant.blocked',
                $this->buildParticipantWebhookPayload('participant.blocked', $conversation, $updated, $actor)
            );
            $this->dispatchParticipantRealtimeEvent($conversation, $actor, $updated, 'blocked');

            return $updated;
        });
    }

    public function unblockParticipant(User $actor, Conversation $conversation, User $targetUser): ConversationParticipant
    {
        $this->assertCanManageParticipant($actor, $conversation);
        $participant = $this->getParticipantOrFail($conversation, $targetUser);

        return DB::transaction(function () use ($participant, $conversation, $actor): ConversationParticipant {
            $oldValues = $participant->only([
                'status',
                'access_state',
                'block_display_mode',
                'blocked_by',
                'blocked_at',
                'blocked_reason',
                'can_send',
                'can_attach',
            ]);
            $oldAccessState = (string) ($participant->access_state ?? 'blocked');

            $participant->status = 'active';
            $participant->access_state = 'full';
            $participant->block_display_mode = null;
            $participant->blocked_by = null;
            $participant->blocked_at = null;
            $participant->blocked_reason = null;
            $participant->can_send = true;
            $participant->can_attach = true;
            $participant->can_invite = false;
            $participant->can_remove = false;
            $participant->can_manage = in_array($participant->role, ['owner', 'admin', 'support'], true);
            $participant->can_moderate = in_array($participant->role, ['owner', 'admin', 'support'], true);
            $participant->save();

            $updated = $participant->fresh();
            $this->chatModerationService->logParticipantUnblocked(
                actor: $actor,
                participant: $updated,
                metadata: [
                    'restriction_source' => 'participant_restriction',
                    'conversation_id' => $conversation->id,
                    'participant_id' => $updated->id,
                    'target_user_id' => $updated->user_id,
                    'old_access_state' => $oldAccessState,
                    'new_access_state' => 'full',
                    'old_status' => (string) ($oldValues['status'] ?? 'blocked'),
                    'new_status' => (string) ($updated->status ?? 'active'),
                    'previous_value' => ['access_state' => $oldAccessState],
                    'new_value' => ['access_state' => 'full'],
                ]
            );
            $this->webhookDeliveryService->queueEvent(
                'participant.unblocked',
                $this->buildParticipantWebhookPayload('participant.unblocked', $conversation, $updated, $actor)
            );
            $this->dispatchParticipantRealtimeEvent($conversation, $actor, $updated, 'unblocked');

            return $updated;
        });
    }

    public function setParticipantCapabilities(User $actor, Conversation $conversation, User $targetUser, array $capabilities): ConversationParticipant
    {
        $this->assertCanManageParticipant($actor, $conversation);
        $participant = $this->getParticipantOrFail($conversation, $targetUser);

        if ((int) $actor->id === (int) $participant->user_id) {
            throw new AuthorizationException('You are not allowed to change your own participant capabilities.');
        }

        $capabilityKeys = ['can_invite', 'can_remove', 'can_send', 'can_attach', 'can_manage', 'can_moderate'];
        $newCapabilities = [];
        foreach ($capabilityKeys as $key) {
            if (array_key_exists($key, $capabilities)) {
                $newCapabilities[$key] = (bool) $capabilities[$key];
            }
        }

        if ($participant->role === 'owner' && $this->isLastOwner($conversation, $participant)) {
            if (($newCapabilities['can_manage'] ?? $participant->can_manage) === false) {
                throw ValidationException::withMessages([
                    'can_manage' => ['Cannot disable manage capability for the last owner.'],
                ]);
            }
        }

        return DB::transaction(function () use ($participant, $newCapabilities, $conversation, $actor): ConversationParticipant {
            $oldValues = $participant->only(array_keys($newCapabilities));
            $participant->fill($newCapabilities);
            $participant->save();

            $updated = $participant->fresh();
            $this->chatModerationService->logParticipantRestricted(
                actor: $actor,
                participant: $updated,
                action: 'participant.capabilities_updated',
                reason: null,
                metadata: [
                    'restriction_source' => 'participant_restriction',
                    'conversation_id' => $conversation->id,
                    'participant_id' => $updated->id,
                    'target_user_id' => $updated->user_id,
                    'previous_value' => $oldValues,
                    'new_value' => $updated->only(array_keys($newCapabilities)),
                ]
            );
            $this->dispatchParticipantRealtimeEvent($conversation, $actor, $updated, 'capabilities_updated');

            return $updated;
        });
    }

    private function assertCanManageParticipant(User $actor, Conversation $conversation): void
    {
        $participant = $this->accessService->getParticipant($conversation, $actor);
        $isOwnerAdmin = $participant !== null && in_array($participant->role, ['owner', 'admin', 'support'], true);
        $canManageOrModerate = $participant !== null && ((bool) $participant->can_manage || (bool) $participant->can_moderate);
        $hasPrivPermission = $actor->hasAnyPermission(['chat.participants.manage', 'chat.admin.moderate']);

        if (! $participant || $participant->access_state === 'hidden' || ! ($isOwnerAdmin || $canManageOrModerate || $hasPrivPermission)) {
            throw new AuthorizationException('You are not allowed to manage participant restrictions in this conversation.');
        }
    }

    private function getParticipantOrFail(Conversation $conversation, User $targetUser): ConversationParticipant
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant' => ['Target participant not found in conversation.'],
            ]);
        }

        return $participant;
    }

    private function isLastOwner(Conversation $conversation, ConversationParticipant $targetParticipant): bool
    {
        if ($targetParticipant->role !== 'owner' || $targetParticipant->status !== 'active') {
            return false;
        }

        $activeOwners = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->count();

        return $activeOwners <= 1;
    }

    private function dispatchParticipantRealtimeEvent(
        Conversation $conversation,
        User $actor,
        ConversationParticipant $participant,
        string $action
    ): void {
        event(new ChatParticipantAccessChanged(
            conversationId: $conversation->id,
            payload: [
                'conversation_id' => $conversation->id,
                'target_user_id' => $participant->user_id,
                'actor_id' => $actor->id,
                'role' => $participant->role,
                'status' => $participant->status,
                'access_state' => $participant->access_state,
                'block_display_mode' => $participant->block_display_mode,
                'changed_fields' => $action,
                'updated_at' => $participant->updated_at?->toISOString(),
            ]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParticipantWebhookPayload(
        string $event,
        Conversation $conversation,
        ConversationParticipant $participant,
        User $actor
    ): array {
        return [
            'event' => $event,
            'conversation_id' => $conversation->id,
            'target_user_id' => $participant->user_id,
            'actor_id' => $actor->id,
            'role' => $participant->role,
            'status' => $participant->status,
            'access_state' => $participant->access_state,
            'changed_at' => $participant->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }
}
