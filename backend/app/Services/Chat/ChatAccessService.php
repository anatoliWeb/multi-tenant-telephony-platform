<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;

class ChatAccessService
{
    public function getParticipant(Conversation $conversation, User $user): ?ConversationParticipant
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'blocked'])
            ->first();
    }

    public function canViewConversation(User $user, Conversation $conversation): bool
    {
        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        if (! $user->hasAnyPermission(['chat.view', 'chat.conversations.view'])) {
            return false;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        if ($participant->access_state === 'hidden') {
            return false;
        }

        if ($participant->access_state === 'blocked') {
            return in_array(
                $participant->block_display_mode,
                ['show_notice', 'show_read_only_history'],
                true
            );
        }

        return true;
    }

    public function canViewMessages(User $user, Conversation $conversation): bool
    {
        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        if (! $this->canViewConversation($user, $conversation)) {
            return false;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        if ($participant->access_state === 'blocked') {
            return $participant->block_display_mode === 'show_read_only_history';
        }

        return true;
    }

    public function canSendMessage(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.send')) {
            return false;
        }

        if ($this->hasAdminReplyPermission($user)) {
            return true;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        if (! $this->canViewMessages($user, $conversation)) {
            return false;
        }

        if ($participant->access_state === 'read_only') {
            return false;
        }

        return $participant->status === 'active'
            && $participant->access_state === 'full'
            && $participant->can_send;
    }

    public function canAttachFile(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.attachments.upload')) {
            return false;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant) {
            return false;
        }

        return $this->canSendMessage($user, $conversation) && $participant->can_attach;
    }

    public function canInvite(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.participants.add')) {
            return false;
        }

        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant || ! $this->canViewConversation($user, $conversation)) {
            return false;
        }

        return $participant->can_invite
            || in_array($participant->role, ['owner', 'admin', 'support'], true);
    }

    public function canRemoveParticipant(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.participants.remove')) {
            return false;
        }

        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant || ! $this->canViewConversation($user, $conversation)) {
            return false;
        }

        return $participant->can_remove
            || in_array($participant->role, ['owner', 'admin', 'support'], true);
    }

    public function canManage(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.participants.manage')) {
            return false;
        }

        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant || ! $this->canViewConversation($user, $conversation)) {
            return false;
        }

        return $participant->can_manage
            || in_array($participant->role, ['owner', 'admin', 'support'], true);
    }

    public function canModerate(User $user, Conversation $conversation): bool
    {
        if (! $user->hasPermission('chat.admin.moderate')) {
            return false;
        }

        if ($this->hasAdminViewPermission($user)) {
            return true;
        }

        $participant = $this->getParticipant($conversation, $user);
        if (! $participant || ! $this->canViewConversation($user, $conversation)) {
            return false;
        }

        return $participant->can_moderate
            || in_array($participant->role, ['owner', 'admin', 'support'], true);
    }

    /**
     * @return array{
     *     can_view_messages: bool,
     *     notice_only: bool,
     *     from_message_id: int|null,
     *     from_at: \Illuminate\Support\Carbon|null,
     *     until_message_id: int|null,
     *     until_at: \Illuminate\Support\Carbon|null
     * }
     */
    public function getVisibleHistoryBounds(User $user, Conversation $conversation): array
    {
        $participant = $this->getParticipant($conversation, $user);
        if (! $participant) {
            return [
                'can_view_messages' => false,
                'notice_only' => false,
                'from_message_id' => null,
                'from_at' => null,
                'until_message_id' => null,
                'until_at' => null,
            ];
        }

        $canViewMessages = $this->canViewMessages($user, $conversation);
        $noticeOnly = $participant->access_state === 'blocked'
            && $participant->block_display_mode === 'show_notice';

        return [
            'can_view_messages' => $canViewMessages,
            'notice_only' => $noticeOnly,
            'from_message_id' => $participant->history_visible_from_message_id,
            'from_at' => $participant->history_visible_from_at,
            'until_message_id' => $participant->history_visible_until_message_id,
            'until_at' => $participant->history_visible_until_at,
        ];
    }

    public function isMessageVisibleToUser(User $user, Conversation $conversation, Message $message): bool
    {
        if ($message->conversation_id !== $conversation->id) {
            return false;
        }

        $bounds = $this->getVisibleHistoryBounds($user, $conversation);
        if (! $bounds['can_view_messages']) {
            return false;
        }

        if ($bounds['from_message_id'] !== null && $message->id < $bounds['from_message_id']) {
            return false;
        }

        if ($bounds['until_message_id'] !== null && $message->id > $bounds['until_message_id']) {
            return false;
        }

        if ($bounds['from_at'] !== null && $message->created_at?->lt($bounds['from_at'])) {
            return false;
        }

        if ($bounds['until_at'] !== null && $message->created_at?->gt($bounds['until_at'])) {
            return false;
        }

        return true;
    }

    public function canViewAdminMetadata(User $user): bool
    {
        return $user->hasAnyPermission(['chat.admin.view', 'chat.admin.view_metadata']);
    }

    private function hasAdminViewPermission(User $user): bool
    {
        return $user->hasPermission('chat.admin.view');
    }

    private function hasAdminReplyPermission(User $user): bool
    {
        return $user->hasAnyPermission(['chat.admin.reply', 'chat.admin.moderate']);
    }
}
