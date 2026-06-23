<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatAccessService;

class ConversationPolicy
{
    public function __construct(
        protected ChatAccessService $access
    ) {
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->access->canViewConversation($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['chat.create', 'chat.conversations.create']);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->hasPermission('chat.conversations.edit')
            && $this->access->canManage($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $user->hasAnyPermission(['chat.delete', 'chat.conversations.delete'])
            && $this->access->canManage($user, $conversation);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->access->canSendMessage($user, $conversation);
    }

    public function attachFile(User $user, Conversation $conversation): bool
    {
        return $this->access->canAttachFile($user, $conversation);
    }

    public function inviteParticipant(User $user, Conversation $conversation): bool
    {
        return $this->access->canInvite($user, $conversation);
    }

    public function removeParticipant(User $user, Conversation $conversation): bool
    {
        return $this->access->canRemoveParticipant($user, $conversation);
    }

    public function manageParticipants(User $user, Conversation $conversation): bool
    {
        return $this->access->canManage($user, $conversation);
    }

    public function moderate(User $user, Conversation $conversation): bool
    {
        return $this->access->canModerate($user, $conversation);
    }

    public function viewAdminMetadata(User $user, Conversation $conversation): bool
    {
        return $this->access->canViewConversation($user, $conversation)
            && $this->access->canViewAdminMetadata($user);
    }

    public function close(User $user, Conversation $conversation): bool
    {
        return $user->hasAnyPermission(['chat.conversations.close', 'chat.admin.close_conversations'])
            && $this->access->canManage($user, $conversation);
    }

    public function archive(User $user, Conversation $conversation): bool
    {
        return $user->hasPermission('chat.conversations.archive')
            && $this->access->canManage($user, $conversation);
    }
}
