<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatParticipantResource extends JsonResource
{
    protected bool $canViewAdminMetadata = false;

    public function withAdminMetadata(bool $allowed): self
    {
        $this->canViewAdminMetadata = $allowed;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'user_id' => $this->user_id,
            'role' => $this->role,
            'status' => $this->status,
            'access_state' => $this->access_state,
            'block_display_mode' => $this->block_display_mode,
            'joined_at' => $this->joined_at?->toISOString(),
            'last_read_at' => $this->last_read_at?->toISOString(),
        ];

        if (! $this->canViewAdminMetadata) {
            return $base;
        }

        return array_merge($base, [
            'can_send' => (bool) $this->can_send,
            'can_attach' => (bool) $this->can_attach,
            'can_invite' => (bool) $this->can_invite,
            'can_remove' => (bool) $this->can_remove,
            'can_manage' => (bool) $this->can_manage,
            'can_moderate' => (bool) $this->can_moderate,
            'blocked_by' => $this->blocked_by,
            'blocked_at' => $this->blocked_at?->toISOString(),
            'blocked_reason' => $this->blocked_reason,
            'history_visible_from_message_id' => $this->history_visible_from_message_id,
            'history_visible_from_at' => $this->history_visible_from_at?->toISOString(),
            'history_visible_until_message_id' => $this->history_visible_until_message_id,
            'history_visible_until_at' => $this->history_visible_until_at?->toISOString(),
        ]);
    }
}
