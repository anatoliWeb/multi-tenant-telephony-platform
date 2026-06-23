<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChatConversationQueryService
{
    public function __construct(
        protected ChatAccessService $access
    ) {
    }

    /**
     * Build conversation query constrained by participant visibility/access rules.
     *
     * Non-privileged users are restricted to active/allowed participant rows; admin
     * moderation permissions can browse across broader conversation scope.
     *
     * @param array<string, mixed> $filters
     */
    public function visibleConversationsFor(User $user, array $filters = []): Builder
    {
        $query = Conversation::query()
            ->where('status', '!=', 'deleted');

        if ($this->canAdminBrowseConversations($user)) {
            return $this->applyConversationFilters($query, $filters);
        }

        if (! $user->hasAnyPermission(['chat.view', 'chat.conversations.view'])) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('participants', function (Builder $participantQuery) use ($user): void {
            $participantQuery
                ->where('user_id', $user->id)
                ->where(function (Builder $statusQuery): void {
                    $statusQuery
                        ->where(function (Builder $activeQuery): void {
                            $activeQuery
                                ->where('status', 'active')
                                ->where('access_state', '!=', 'hidden');
                        })
                        ->orWhere(function (Builder $blockedQuery): void {
                            $blockedQuery
                                ->where('status', 'blocked')
                                ->where('access_state', 'blocked')
                                ->whereIn('block_display_mode', ['show_notice', 'show_read_only_history']);
                        });
                });
        });

        return $this->applyConversationFilters($query, $filters);
    }

    /**
     * Build message query constrained by conversation access and visible history bounds.
     */
    public function visibleMessagesFor(User $user, Conversation $conversation): Builder
    {
        if (! $this->access->canViewMessages($user, $conversation)) {
            return Message::query()->whereRaw('1 = 0');
        }

        $query = Message::query()
            ->where('conversation_id', $conversation->id);

        if (! $this->canAdminBrowseConversations($user)) {
            $query->whereNull('deleted_at')
                ->where('status', '!=', 'deleted');
        }

        $bounds = $this->access->getVisibleHistoryBounds($user, $conversation);

        if ($bounds['from_message_id'] !== null) {
            $query->where('id', '>=', $bounds['from_message_id']);
        }

        if ($bounds['until_message_id'] !== null) {
            $query->where('id', '<=', $bounds['until_message_id']);
        }

        if ($bounds['from_at'] !== null) {
            $query->where('created_at', '>=', $bounds['from_at']);
        }

        if ($bounds['until_at'] !== null) {
            $query->where('created_at', '<=', $bounds['until_at']);
        }

        return $query->orderBy('id');
    }

    public function visibleMessagesCountFor(User $user, Conversation $conversation): int
    {
        return $this->visibleMessagesFor($user, $conversation)->count();
    }

    public function searchVisibleMessages(User $user, Conversation $conversation, array $filters = []): Builder
    {
        $query = $this->visibleMessagesFor($user, $conversation);

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where('body', 'like', '%'.$term.'%');
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        if (! empty($filters['sender_id'])) {
            $query->where('sender_id', (int) $filters['sender_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (array_key_exists('imported', $filters) && $filters['imported'] !== null) {
            $query->where('is_imported', (bool) $filters['imported']);
        }

        if (array_key_exists('has_attachments', $filters) && $filters['has_attachments'] !== null) {
            $query->whereHas('attachments', function (Builder $attachmentQuery): void {
                $attachmentQuery
                    ->whereNull('deleted_at')
                    ->where('status', 'active');
            }, $filters['has_attachments'] ? '>' : '=', 0);
        }

        return $query;
    }

    public function unreadCountFor(User $user, Conversation $conversation): int
    {
        $participant = $this->access->getParticipant($conversation, $user);
        if (! $participant) {
            return 0;
        }

        $query = $this->visibleMessagesFor($user, $conversation)
            ->where(function (Builder $senderQuery) use ($user): void {
                // WHY:
                // In chat UX, own messages are considered already read by sender
                // and should not inflate unread badges/counters.
                $senderQuery
                    ->whereNull('sender_id')
                    ->orWhere('sender_id', '!=', $user->id);
            });

        if ($participant->last_read_message_id !== null) {
            $query->where('id', '>', $participant->last_read_message_id);
        } elseif ($participant->last_read_at !== null) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }

    /**
     * Batch unread counters for a set of conversations in a single grouped query.
     *
     * @param array<int, int> $conversationIds
     * @return array<int, int>
     */
    public function unreadCountsForConversations(User $user, array $conversationIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $conversationIds)));
        if ($ids === []) {
            return [];
        }

        // Admin browse can include conversations where user is not a participant.
        // For such rows unread should remain 0 (same behavior as unreadCountFor).
        if ($this->canAdminBrowseConversations($user)) {
            $participantConversationIds = ConversationParticipant::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'blocked'])
                ->whereIn('conversation_id', $ids)
                ->pluck('conversation_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = $participantConversationIds;
        }

        if ($ids === []) {
            return [];
        }

        $counts = Message::query()
            // WHY:
            // Compute unread counters for all visible conversations in one grouped query
            // to avoid per-conversation count queries (N+1) in chat list responses.
            ->select('messages.conversation_id', DB::raw('COUNT(*) as unread_count'))
            ->join('conversation_participants as cp', function ($join) use ($user): void {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', '=', $user->id)
                    ->whereIn('cp.status', ['active', 'blocked']);
            })
            ->whereIn('messages.conversation_id', $ids)
            ->whereNull('messages.deleted_at')
            ->where('messages.status', '!=', 'deleted')
            ->where(function (Builder $senderQuery) use ($user): void {
                $senderQuery
                    ->whereNull('messages.sender_id')
                    ->orWhere('messages.sender_id', '!=', $user->id);
            })
            ->where(function (Builder $bounds): void {
                $bounds->whereNull('cp.history_visible_from_message_id')
                    ->orWhereColumn('messages.id', '>=', 'cp.history_visible_from_message_id');
            })
            ->where(function (Builder $bounds): void {
                $bounds->whereNull('cp.history_visible_until_message_id')
                    ->orWhereColumn('messages.id', '<=', 'cp.history_visible_until_message_id');
            })
            ->where(function (Builder $bounds): void {
                $bounds->whereNull('cp.history_visible_from_at')
                    ->orWhereColumn('messages.created_at', '>=', 'cp.history_visible_from_at');
            })
            ->where(function (Builder $bounds): void {
                $bounds->whereNull('cp.history_visible_until_at')
                    ->orWhereColumn('messages.created_at', '<=', 'cp.history_visible_until_at');
            })
            ->where(function (Builder $readBounds): void {
                $readBounds
                    ->where(function (Builder $q): void {
                        $q->whereNotNull('cp.last_read_message_id')
                            ->whereColumn('messages.id', '>', 'cp.last_read_message_id');
                    })
                    ->orWhere(function (Builder $q): void {
                        $q->whereNull('cp.last_read_message_id')
                            ->whereNotNull('cp.last_read_at')
                            ->whereColumn('messages.created_at', '>', 'cp.last_read_at');
                    })
                    ->orWhere(function (Builder $q): void {
                        $q->whereNull('cp.last_read_message_id')
                            ->whereNull('cp.last_read_at');
                    });
            })
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'messages.conversation_id');

        // WHY:
        // Unread values depend on high-churn read markers and message arrival.
        // Returning direct query results avoids stale global cache artifacts in active chats.
        return collect($counts)
            ->mapWithKeys(fn ($count, $conversationId) => [(int) $conversationId => (int) $count])
            ->all();
    }

    /**
     * @throws AuthorizationException
     */
    public function adminConversationsFor(User $user, array $filters = []): Builder
    {
        if (! $user->hasPermission('chat.admin.view')) {
            throw new AuthorizationException('You are not authorized to view admin chat conversations.');
        }

        $query = Conversation::query()
            ->where('status', '!=', 'deleted');

        return $this->applyConversationFilters($query, $filters);
    }

    public function applyAdminMetadataGate(User $user, Conversation $conversation): bool
    {
        return $this->access->canViewConversation($user, $conversation)
            && $user->hasPermission('chat.admin.view_metadata');
    }

    private function canAdminBrowseConversations(User $user): bool
    {
        return $user->hasAnyPermission(['chat.admin.view', 'chat.admin.view_metadata']);
    }

    private function applyConversationFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (($filters['unread'] ?? false) === true && isset($filters['user']) && $filters['user'] instanceof User) {
            $user = $filters['user'];
            $query->whereHas('participants', function (Builder $participantQuery) use ($user): void {
                $participantQuery
                    ->where('user_id', $user->id)
                    ->where(function (Builder $readQuery): void {
                        $readQuery
                            ->where(function (Builder $idQuery): void {
                                $idQuery
                                    ->whereColumn('conversation_participants.last_read_message_id', '<', 'conversations.last_message_id');
                            })
                            ->orWhere(function (Builder $timeQuery): void {
                                $timeQuery
                                    ->whereNotNull('conversation_participants.last_read_at')
                                    ->whereNotNull('conversations.last_message_at')
                                    ->whereColumn('conversation_participants.last_read_at', '<', 'conversations.last_message_at');
                            })
                            ->orWhere(function (Builder $freshUnreadQuery): void {
                                $freshUnreadQuery
                                    ->whereNull('conversation_participants.last_read_message_id')
                                    ->whereNull('conversation_participants.last_read_at')
                                    ->whereNotNull('conversations.last_message_id');
                            });
                    });
            });
        }

        return $query;
    }
}
