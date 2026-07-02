<?php

namespace App\Services\Chat;

use App\Enums\Extensions\ExtensionStatus;
use App\Events\Chat\ChatUserLeftConversation;
use App\Models\Extension;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatConversationService
{
    private const ALLOWED_VISIBILITIES = ['private', 'public'];

    private const ALLOWED_JOIN_POLICIES = [
        'invite_only',
        'participants_can_invite',
        'anyone_with_permission',
        'public_join',
    ];

    private const ALLOWED_PARTICIPANT_ROLES = ['owner', 'admin', 'member', 'viewer', 'support'];

    public function __construct(
        protected ChatAccessService $accessService,
        protected ChatHistoryImportService $historyImportService,
        protected ChatModerationService $chatModerationService,
        protected ChatWebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    public function createPrivateGroupFromDirect(
        User $actor,
        Conversation $directConversation,
        array $newParticipantIds,
        array $payload
    ): Conversation {
        if ($directConversation->type !== 'direct') {
            throw ValidationException::withMessages([
                'conversation' => ['Source conversation must be a direct chat.'],
            ]);
        }

        if (! $this->accessService->canViewConversation($actor, $directConversation)) {
            throw new AuthorizationException('You are not allowed to create group from this direct conversation.');
        }

        $participant = $this->accessService->getParticipant($directConversation, $actor);
        $canCreateFromDirect = $this->accessService->canManage($actor, $directConversation)
            || $this->accessService->canInvite($actor, $directConversation)
            || in_array($participant?->role, ['owner', 'admin', 'support'], true);

        if (! $canCreateFromDirect) {
            throw new AuthorizationException('You are not allowed to create private group from this direct conversation.');
        }

        $historyMode = (string) ($payload['history_import_mode'] ?? 'none');
        if (! in_array($historyMode, ['none', 'from_date', 'from_message', 'full'], true)) {
            throw ValidationException::withMessages([
                'history_import_mode' => ['Invalid history import mode.'],
            ]);
        }

        $activeSourceParticipantIds = ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $directConversation->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidateParticipantIds = array_values(array_unique(array_merge(
            $activeSourceParticipantIds,
            array_map('intval', $newParticipantIds),
            [$actor->id]
        )));

        $candidateParticipantIds = array_values(array_filter($candidateParticipantIds, fn (int $id) => $id > 0));
        if (count($candidateParticipantIds) < 3) {
            throw ValidationException::withMessages([
                'participant_ids' => ['New private group must have at least 3 unique participants.'],
            ]);
        }

        $users = User::query()
            ->whereIn('id', $candidateParticipantIds)
            ->get()
            ->filter(fn (User $user): bool => $this->canUseUserInCurrentTenant($user))
            ->keyBy('id');
        if ($users->count() !== count($candidateParticipantIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['One or more participants do not exist or are not active in the current tenant.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $directConversation, $payload, $historyMode, $candidateParticipantIds, $users): Conversation {
            $conversation = Conversation::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => 'group',
                'visibility' => 'private',
                'title' => $payload['title'] ?? null,
                'description' => $payload['description'] ?? null,
                'owner_id' => $actor->id,
                'created_by' => $actor->id,
                'created_from_conversation_id' => $directConversation->id,
                'source' => 'internal',
                'status' => 'active',
                'join_policy' => 'invite_only',
                'history_import_mode' => $historyMode,
                'history_import_from_message_id' => $historyMode === 'from_message'
                    ? (int) ($payload['history_import_from_message_id'] ?? 0) ?: null
                    : null,
                'history_import_from_at' => $historyMode === 'from_date'
                    ? ($payload['history_import_from_at'] ?? null)
                    : null,
                'metadata' => null,
            ]);

            foreach ($candidateParticipantIds as $userId) {
                /** @var User $user */
                $user = $users->get($userId);
                $this->upsertParticipant($conversation, $user, [
                    'role' => $user->id === $actor->id ? 'owner' : 'member',
                    'status' => 'active',
                    'access_state' => 'full',
                    'can_invite' => $user->id === $actor->id,
                    'can_remove' => $user->id === $actor->id,
                    'can_send' => true,
                    'can_attach' => true,
                    'can_manage' => $user->id === $actor->id,
                    'can_moderate' => $user->id === $actor->id,
                ]);
            }

            $this->historyImportService->importHistory(
                $actor,
                $directConversation,
                $conversation,
                $historyMode,
                $payload['history_import_from_message_id'] ?? null,
                $payload['history_import_from_at'] ?? null
            );

            $created = $conversation->fresh();
            $this->chatModerationService->logConversationCreated($actor, $created, [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $created->type,
                'conversation_source' => $created->source,
                'visibility' => $created->visibility,
                'status' => $created->status,
                'participants_count' => count($candidateParticipantIds),
                'created_by_role' => 'owner',
                'history_import_mode' => $historyMode,
                'created_from_conversation_id' => $directConversation->id,
            ], 'conversation.group_created');
            $this->webhookDeliveryService->queueEvent(
                'conversation.created',
                $this->buildConversationCreatedWebhookPayload($created)
            );

            return $created;
        });
    }

    public function createDirectConversation(User $creator, int $targetUserId): Conversation
    {
        if ($creator->id === $targetUserId) {
            throw ValidationException::withMessages([
                'user_id' => ['Target user must be different from creator.'],
            ]);
        }

        $targetUser = User::query()->find($targetUserId);
        if (! $targetUser || ! $this->canUseUserInCurrentTenant($targetUser)) {
            throw ValidationException::withMessages([
                'user_id' => ['Target user not found in the current tenant.'],
            ]);
        }

        $existing = $this->findExistingDirectConversation($creator->id, $targetUserId);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($creator, $targetUser): Conversation {
            $conversation = Conversation::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->currentTenantId(),
                'type' => 'direct',
                'visibility' => 'private',
                'title' => null,
                'description' => null,
                'owner_id' => $creator->id,
                'created_by' => $creator->id,
                'created_from_conversation_id' => null,
                'source' => 'internal',
                'status' => 'active',
                'join_policy' => 'invite_only',
                'history_import_mode' => 'none',
                'metadata' => null,
            ]);

            $this->upsertParticipant($conversation, $creator, [
                'role' => 'owner',
                'status' => 'active',
                'access_state' => 'full',
                'can_invite' => false,
                'can_remove' => true,
                'can_send' => true,
                'can_attach' => true,
                'can_manage' => true,
                'can_moderate' => false,
            ]);

            $this->upsertParticipant($conversation, $targetUser, [
                'role' => 'member',
                'status' => 'active',
                'access_state' => 'full',
                'can_invite' => false,
                'can_remove' => false,
                'can_send' => true,
                'can_attach' => true,
                'can_manage' => false,
                'can_moderate' => false,
            ]);

            $created = $conversation->fresh();
            $this->chatModerationService->logConversationCreated($creator, $created, [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $created->type,
                'conversation_source' => $created->source,
                'visibility' => $created->visibility,
                'status' => $created->status,
                'participants_count' => 2,
                'created_by_role' => 'owner',
            ], 'conversation.direct_created');
            $this->webhookDeliveryService->queueEvent(
                'conversation.created',
                $this->buildConversationCreatedWebhookPayload($created)
            );

            return $created;
        });
    }

    public function createGroupConversation(User $creator, array $participantUserIds, array $payload): Conversation
    {
        $visibility = (string) ($payload['visibility'] ?? 'private');
        if (! in_array($visibility, self::ALLOWED_VISIBILITIES, true)) {
            throw ValidationException::withMessages([
                'visibility' => ['Invalid visibility.'],
            ]);
        }

        $joinPolicy = (string) ($payload['join_policy'] ?? 'invite_only');
        if (! in_array($joinPolicy, self::ALLOWED_JOIN_POLICIES, true)) {
            throw ValidationException::withMessages([
                'join_policy' => ['Invalid join policy.'],
            ]);
        }

        $participantUserIds = array_values(array_unique(array_map('intval', $participantUserIds)));
        $participantUserIds = array_values(array_filter($participantUserIds, fn (int $id) => $id > 0 && $id !== $creator->id));
        if (count($participantUserIds) < 1) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Group conversation must include at least one additional participant.'],
            ]);
        }

        $users = User::query()
            ->whereIn('id', $participantUserIds)
            ->get()
            ->filter(fn (User $user): bool => $this->canUseUserInCurrentTenant($user))
            ->keyBy('id');
        if ($users->count() !== count($participantUserIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['One or more participants do not exist or are not active in the current tenant.'],
            ]);
        }

        return DB::transaction(function () use ($creator, $users, $payload, $visibility, $joinPolicy): Conversation {
            $conversation = Conversation::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->currentTenantId(),
                'type' => 'group',
                'visibility' => $visibility,
                'title' => $payload['title'] ?? null,
                'description' => $payload['description'] ?? null,
                'owner_id' => $creator->id,
                'created_by' => $creator->id,
                'created_from_conversation_id' => null,
                'source' => 'internal',
                'status' => 'active',
                'join_policy' => $joinPolicy,
                'history_import_mode' => 'none',
                'metadata' => null,
            ]);

            $this->upsertParticipant($conversation, $creator, [
                'role' => 'owner',
                'status' => 'active',
                'access_state' => 'full',
                'can_invite' => true,
                'can_remove' => true,
                'can_send' => true,
                'can_attach' => true,
                'can_manage' => true,
                'can_moderate' => true,
            ]);

            foreach ($users as $user) {
                $this->upsertParticipant($conversation, $user, [
                    'role' => 'member',
                    'status' => 'active',
                    'access_state' => 'full',
                    'can_invite' => false,
                    'can_remove' => false,
                    'can_send' => true,
                    'can_attach' => true,
                    'can_manage' => false,
                    'can_moderate' => false,
                ]);
            }

            $created = $conversation->fresh();
            $this->chatModerationService->logConversationCreated($creator, $created, [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $created->type,
                'conversation_source' => $created->source,
                'visibility' => $created->visibility,
                'status' => $created->status,
                'participants_count' => (int) ($users->count() + 1),
                'created_by_role' => 'owner',
            ], 'conversation.group_created');
            $this->webhookDeliveryService->queueEvent(
                'conversation.created',
                $this->buildConversationCreatedWebhookPayload($created)
            );

            return $created;
        });
    }

    public function createSupportConversation(User $creator, array $participantUserIds, array $payload): Conversation
    {
        return $this->createTypedConversation($creator, 'support', 'internal', $participantUserIds, $payload);
    }

    public function createExternalConversation(User $creator, array $participantUserIds, array $payload): Conversation
    {
        return $this->createTypedConversation($creator, 'external', 'api', $participantUserIds, $payload);
    }

    public function createSystemConversation(User $actor, array $participantUserIds, array $payload): Conversation
    {
        if (! $actor->hasAnyPermission(['chat.admin.moderate', 'chat.admin.view', 'chat.admin.view_metadata'])) {
            throw new AuthorizationException('You are not allowed to create system conversations.');
        }

        return $this->createTypedConversation($actor, 'system', 'system', $participantUserIds, $payload);
    }

    public function addParticipant(User $actor, Conversation $conversation, int $userId, array $options = []): ConversationParticipant
    {
        if (! $this->accessService->canInvite($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to add participants to this conversation.');
        }

        $user = User::query()->find($userId);
        if (! $user || ! $this->canUseUserInCurrentTenant($user)) {
            throw ValidationException::withMessages([
                'user_id' => ['User not found in the current tenant.'],
            ]);
        }

        if (! in_array((string) $conversation->status, ['active', 'archived', 'closed'], true) || $conversation->status === 'deleted') {
            throw ValidationException::withMessages([
                'conversation' => ['Cannot add participant to deleted conversation.'],
            ]);
        }

        $role = $options['role'] ?? 'member';
        if (! in_array($role, self::ALLOWED_PARTICIPANT_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Invalid participant role.'],
            ]);
        }

        return DB::transaction(function () use ($conversation, $user, $options, $role, $actor): ConversationParticipant {
            $existing = ConversationParticipant::query()
                ->forCurrentTenant()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing && in_array($existing->status, ['active', 'invited', 'blocked'], true)) {
                return $existing;
            }

            $attributes = [
                'role' => $role,
                'status' => 'active',
                'access_state' => 'full',
                'block_display_mode' => null,
                'can_invite' => (bool) ($options['can_invite'] ?? false),
                'can_remove' => (bool) ($options['can_remove'] ?? false),
                'can_send' => (bool) ($options['can_send'] ?? true),
                'can_attach' => (bool) ($options['can_attach'] ?? true),
                'can_manage' => (bool) ($options['can_manage'] ?? false),
                'can_moderate' => (bool) ($options['can_moderate'] ?? false),
                'blocked_by' => null,
                'blocked_at' => null,
                'blocked_reason' => null,
                'joined_at' => now(),
                'left_at' => null,
                'removed_at' => null,
            ];

            $wasExistingParticipant = $existing !== null;
            if ($existing) {
                $existing->fill($attributes)->save();

                $updated = $existing->fresh();
                $this->webhookDeliveryService->queueEvent(
                    'participant.joined',
                    $this->buildParticipantWebhookPayload('participant.joined', $conversation, $updated, $actor)
                );

                return $updated;
            }

            $created = ConversationParticipant::query()->forCurrentTenant()->create(array_merge($attributes, [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ]));
            if (! $wasExistingParticipant) {
                $this->webhookDeliveryService->queueEvent(
                    'participant.joined',
                    $this->buildParticipantWebhookPayload('participant.joined', $conversation, $created, $actor)
                );
            }

            return $created;
        });
    }

    public function removeParticipant(User $actor, Conversation $conversation, int $userId): void
    {
        if (! $this->accessService->canRemoveParticipant($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to remove participants from this conversation.');
        }

        $participant = ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'invited', 'blocked'])
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'user_id' => ['Participant not found in conversation.'],
            ]);
        }

        if ($participant->role === 'owner') {
            $activeOwners = ConversationParticipant::query()
                ->forCurrentTenant()
                ->where('conversation_id', $conversation->id)
                ->where('role', 'owner')
                ->where('status', 'active')
                ->count();

            if ($activeOwners <= 1) {
                throw ValidationException::withMessages([
                    'user_id' => ['Cannot remove the last owner from conversation.'],
                ]);
            }
        }

        $participant->status = 'removed';
        $participant->access_state = 'hidden';
        $participant->removed_at = now();
        $participant->can_invite = false;
        $participant->can_remove = false;
        $participant->can_manage = false;
        $participant->can_moderate = false;
        $participant->save();
        $this->webhookDeliveryService->queueEvent(
            'participant.left',
            $this->buildParticipantWebhookPayload('participant.left', $conversation, $participant->fresh(), $actor)
        );
    }

    public function listParticipants(User $actor, Conversation $conversation): Collection
    {
        if (! $this->accessService->canViewConversation($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to view conversation participants.');
        }

        return ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['active', 'invited', 'blocked'])
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'support', 'member', 'viewer')")
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the safe callable target for a direct conversation.
     *
     * The payload intentionally exposes only browser-safe call metadata so chat
     * features can reuse the shared softphone layer without handling SIP
     * credentials or tenant-specific provider state.
     *
     * @return array{callable: bool, user_id: int|null, display_name: string|null, extension_number: string|null, sip_uri: string|null, target: string|null, reason: string|null}
     */
    public function resolveCallTarget(User $actor, Conversation $conversation): array
    {
        if (! $this->accessService->canViewConversation($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to view conversation call targets.');
        }

        if ($conversation->type !== 'direct') {
            return $this->buildUnavailableCallTarget(null, null, null, 'Call is available only for direct chats.');
        }

        $otherParticipant = ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->where('user_id', '!=', $actor->id)
            ->with('user')
            ->first();

        $otherUser = $otherParticipant?->user;
        if (! $otherUser) {
            return $this->buildUnavailableCallTarget(null, null, null, 'No callable participant is available for this direct chat.');
        }

        $extension = Extension::query()
            ->forCurrentTenant()
            ->where('assigned_user_id', $otherUser->id)
            ->where('status', ExtensionStatus::Active->value)
            ->orderBy('number')
            ->first();

        if (! $extension) {
            return $this->buildUnavailableCallTarget(
                (int) $otherUser->id,
                $this->safeParticipantName($otherUser->name),
                null,
                'The other participant does not have an active callable extension.'
            );
        }

        $extensionNumber = trim((string) $extension->number);
        $sipUri = sprintf('sip:%s@localhost', $extensionNumber);

        return [
            'callable' => true,
            'user_id' => (int) $otherUser->id,
            'display_name' => $this->safeParticipantName($otherUser->name),
            'extension_number' => $extensionNumber,
            'sip_uri' => $sipUri,
            'target' => $sipUri,
            'reason' => null,
        ];
    }

    public function leaveConversation(User $actor, Conversation $conversation): ConversationParticipant
    {
        $participant = ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->first();

        if (! $participant) {
            throw new AuthorizationException('You are not a participant of this conversation.');
        }

        if (in_array($participant->status, ['left', 'removed'], true) || $participant->access_state === 'hidden') {
            throw ValidationException::withMessages([
                'conversation' => ['Participant already left or hidden in this conversation.'],
            ]);
        }

        if ($participant->role === 'owner') {
            $activeOwners = ConversationParticipant::query()
                ->forCurrentTenant()
                ->where('conversation_id', $conversation->id)
                ->where('role', 'owner')
                ->where('status', 'active')
                ->count();

            if ($activeOwners <= 1) {
                throw ValidationException::withMessages([
                    'conversation' => ['Cannot leave conversation as the last owner.'],
                ]);
            }
        }

        $leftParticipant = DB::transaction(function () use ($participant, $conversation, $actor): ConversationParticipant {
            // WHY:
            // Once participant leaves, all management/sending capabilities are disabled
            // to avoid accidental privilege reuse if the same row is reactivated later.
            $participant->status = 'left';
            $participant->access_state = 'hidden';
            $participant->left_at = now();
            $participant->can_send = false;
            $participant->can_attach = false;
            $participant->can_invite = false;
            $participant->can_remove = false;
            $participant->can_manage = false;
            $participant->can_moderate = false;
            $participant->save();

            $this->chatModerationService->logConversationLeft($actor, $conversation, $participant, [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $conversation->type,
                'conversation_source' => $conversation->source,
                'visibility' => $conversation->visibility,
                'status' => $conversation->status,
                'old_participant_status' => 'active',
                'new_participant_status' => 'left',
            ]);

            $this->webhookDeliveryService->queueEvent(
                'participant.left',
                $this->buildParticipantWebhookPayload('participant.left', $conversation, $participant, $actor)
            );

            return $participant->fresh();
        });

        event(new ChatUserLeftConversation(
            conversationId: $conversation->id,
            payload: [
                'conversation_id' => $conversation->id,
                'user_id' => $actor->id,
                'name' => $actor->name,
                'left_at' => now()->toISOString(),
            ]
        ));

        return $leftParticipant;
    }

    public function closeConversation(User $actor, Conversation $conversation): Conversation
    {
        return $this->updateConversationLifecycleStatus($actor, $conversation, 'closed');
    }

    public function archiveConversation(User $actor, Conversation $conversation): Conversation
    {
        return $this->updateConversationLifecycleStatus($actor, $conversation, 'archived');
    }

    private function findExistingDirectConversation(int $userA, int $userB): ?Conversation
    {
        return Conversation::query()
            ->forCurrentTenant()
            ->where('type', 'direct')
            ->where('visibility', 'private')
            ->where('status', 'active')
            ->whereHas('participants', function ($q) use ($userA): void {
                $q->where('user_id', $userA)->where('status', 'active');
            })
            ->whereHas('participants', function ($q) use ($userB): void {
                $q->where('user_id', $userB)->where('status', 'active');
            })
            ->withCount([
                'participants as active_participants_count' => function ($q): void {
                    $q->where('status', 'active');
                },
            ])
            ->get()
            ->first(fn (Conversation $conversation) => (int) $conversation->active_participants_count === 2);
    }

    private function createTypedConversation(
        User $creator,
        string $type,
        string $source,
        array $participantUserIds,
        array $payload
    ): Conversation {
        if (! $creator->hasAnyPermission(['chat.create', 'chat.conversations.create'])) {
            throw new AuthorizationException('You are not allowed to create conversations.');
        }

        $visibility = (string) ($payload['visibility'] ?? 'private');
        if (! in_array($visibility, self::ALLOWED_VISIBILITIES, true)) {
            throw ValidationException::withMessages([
                'visibility' => ['Invalid visibility.'],
            ]);
        }

        if (in_array($type, ['support', 'external', 'system'], true)) {
            $visibility = (string) ($payload['visibility'] ?? 'private');
        }

        $joinPolicy = (string) ($payload['join_policy'] ?? 'invite_only');
        if (! in_array($joinPolicy, self::ALLOWED_JOIN_POLICIES, true)) {
            throw ValidationException::withMessages([
                'join_policy' => ['Invalid join policy.'],
            ]);
        }

        $participantUserIds = array_values(array_unique(array_map('intval', $participantUserIds)));
        $participantUserIds = array_values(array_filter($participantUserIds, fn (int $id) => $id > 0 && $id !== $creator->id));
        if (count($participantUserIds) < 1) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Conversation must include at least one additional participant.'],
            ]);
        }

        $users = User::query()
            ->whereIn('id', $participantUserIds)
            ->get()
            ->filter(fn (User $user): bool => $this->canUseUserInCurrentTenant($user))
            ->keyBy('id');
        if ($users->count() !== count($participantUserIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['One or more participants do not exist or are not active in the current tenant.'],
            ]);
        }

        $supportUserIds = collect((array) ($payload['support_user_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
        $adminUserIds = collect((array) ($payload['admin_user_ids'] ?? []))->map(fn ($id) => (int) $id)->all();

        return DB::transaction(function () use (
            $creator,
            $type,
            $source,
            $visibility,
            $joinPolicy,
            $payload,
            $users,
            $supportUserIds,
            $adminUserIds
        ): Conversation {
            $conversation = Conversation::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->currentTenantId(),
                'type' => $type,
                'visibility' => $visibility,
                'title' => $payload['title'] ?? null,
                'description' => $payload['description'] ?? null,
                'owner_id' => $creator->id,
                'created_by' => $creator->id,
                'created_from_conversation_id' => null,
                'source' => $source,
                'status' => 'active',
                'join_policy' => $joinPolicy,
                'history_import_mode' => 'none',
                'metadata' => null,
            ]);

            $creatorRole = 'owner';
            $this->upsertParticipant($conversation, $creator, [
                'role' => $creatorRole,
                'status' => 'active',
                'access_state' => 'full',
                'can_invite' => true,
                'can_remove' => true,
                'can_send' => true,
                'can_attach' => true,
                'can_manage' => true,
                'can_moderate' => true,
            ]);

            foreach ($users as $user) {
                $role = 'member';
                if (in_array((int) $user->id, $adminUserIds, true)) {
                    $role = 'admin';
                } elseif (in_array((int) $user->id, $supportUserIds, true)) {
                    $role = 'support';
                }

                $isElevated = in_array($role, ['admin', 'support'], true);
                $this->upsertParticipant($conversation, $user, [
                    'role' => $role,
                    'status' => 'active',
                    'access_state' => 'full',
                    'can_invite' => $isElevated,
                    'can_remove' => $isElevated,
                    'can_send' => true,
                    'can_attach' => true,
                    'can_manage' => $isElevated,
                    'can_moderate' => $isElevated,
                ]);
            }

            $created = $conversation->fresh();
            $typedAction = match ($type) {
                'support' => 'conversation.support_created',
                'external' => 'conversation.external_created',
                default => 'conversation.created',
            };

            $this->chatModerationService->logConversationCreated($creator, $created, [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $created->type,
                'conversation_source' => $created->source,
                'visibility' => $created->visibility,
                'status' => $created->status,
                'participants_count' => (int) ($users->count() + 1),
                'created_by_role' => 'owner',
            ], $typedAction);
            $this->webhookDeliveryService->queueEvent(
                'conversation.created',
                $this->buildConversationCreatedWebhookPayload($created)
            );

            return $created;
        });
    }

    private function upsertParticipant(Conversation $conversation, User $user, array $attributes): ConversationParticipant
    {
        $participant = ConversationParticipant::query()->forCurrentTenant()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $participant->fill(array_merge([
            'history_visibility_mode' => 'full',
            'history_visible_from_message_id' => null,
            'history_visible_from_at' => null,
            'history_visible_until_message_id' => null,
            'history_visible_until_at' => null,
            'last_read_message_id' => null,
            'last_read_at' => null,
            'muted_until' => null,
            'metadata' => null,
        ], $attributes));

        if (blank($participant->joined_at)) {
            $participant->joined_at = now();
        }

        $participant->save();

        return $participant->fresh();
    }

    private function updateConversationLifecycleStatus(User $actor, Conversation $conversation, string $targetStatus): Conversation
    {
        $participant = $this->accessService->getParticipant($conversation, $actor);
        $isRoleAllowed = $participant !== null && in_array($participant->role, ['owner', 'admin', 'support'], true);
        $isCapabilityAllowed = $participant !== null && (bool) $participant->can_manage;
        $isPermissionAllowed = match ($targetStatus) {
            'closed' => $actor->hasAnyPermission(['chat.conversations.close', 'chat.admin.close_conversations']),
            'archived' => $actor->hasPermission('chat.conversations.archive'),
            default => false,
        };

        if (! $isPermissionAllowed || (! $this->accessService->canManage($actor, $conversation) && ! $isRoleAllowed && ! $isCapabilityAllowed)) {
            throw new AuthorizationException("You are not allowed to mark conversation as {$targetStatus}.");
        }

        if ($conversation->status === 'deleted') {
            throw ValidationException::withMessages([
                'conversation' => ['Cannot change lifecycle status for deleted conversation.'],
            ]);
        }

        if ($conversation->status === $targetStatus) {
            return $conversation;
        }

        return DB::transaction(function () use ($conversation, $actor, $targetStatus): Conversation {
            $oldStatus = (string) $conversation->status;
            $conversation->status = $targetStatus;
            $conversation->save();

            $metadata = [
                'source' => 'conversation_lifecycle',
                'conversation_type' => $conversation->type,
                'conversation_source' => $conversation->source,
                'visibility' => $conversation->visibility,
                'old_status' => $oldStatus,
                'new_status' => $targetStatus,
            ];

            if ($targetStatus === 'closed') {
                $this->chatModerationService->logConversationClosed($actor, $conversation, metadata: $metadata);
            } else {
                $this->chatModerationService->logConversationArchived($actor, $conversation, metadata: $metadata);
            }

            return $conversation->fresh();
        });
    }

    /**
     * @return array{callable: bool, user_id: int|null, display_name: string|null, extension_number: string|null, sip_uri: string|null, target: string|null, reason: string|null}
     */
    private function buildUnavailableCallTarget(?int $userId, ?string $displayName, ?string $extensionNumber, string $reason): array
    {
        return [
            'callable' => false,
            'user_id' => $userId,
            'display_name' => $displayName,
            'extension_number' => $extensionNumber,
            'sip_uri' => null,
            'target' => null,
            'reason' => $reason,
        ];
    }

    private function safeParticipantName(?string $name): ?string
    {
        $normalized = trim((string) $name);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConversationCreatedWebhookPayload(Conversation $conversation): array
    {
        return [
            'event' => 'conversation.created',
            'conversation_id' => $conversation->id,
            'type' => $conversation->type,
            'visibility' => $conversation->visibility,
            'source' => $conversation->source,
            'status' => $conversation->status,
            'created_by' => $conversation->created_by,
            'created_at' => $conversation->created_at?->toISOString(),
        ];
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

    private function currentTenantId(): string
    {
        $tenantId = app(TenantContext::class)->tenantId();
        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        if (app()->runningUnitTests()) {
            return TenantBootstrapService::DEFAULT_TENANT_UUID;
        }

        throw new AuthorizationException('Tenant context is required for chat conversation operations.');
    }

    private function canUseUserInCurrentTenant(User $user): bool
    {
        $tenantId = $this->currentTenantId();

        if ($user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists()) {
            return true;
        }

        if (app()->runningUnitTests()
            && $tenantId === TenantBootstrapService::DEFAULT_TENANT_UUID
            && ! $user->tenantMemberships()->exists()) {
            return true;
        }

        return false;
    }
}
