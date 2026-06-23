<?php

namespace App\Http\Resources\Chat;

use App\Models\ConversationParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    protected ?ConversationParticipant $currentParticipant = null;

    protected bool $canViewAdminMetadata = false;

    public function forParticipant(?ConversationParticipant $participant): self
    {
        $this->currentParticipant = $participant;

        return $this;
    }

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
        $participant = $this->currentParticipant;

        $source = null;
        if (in_array($this->source, ['internal', 'system'], true) || $this->canViewAdminMetadata) {
            $source = $this->source;
        }

        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'title' => $this->title,
            'status' => $this->status,
            'source' => $source,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'participants_count' => (int) ($this->participants_count ?? 0),
            'current_user_access' => [
                'role' => $participant?->role,
                'status' => $participant?->status,
                'access_state' => $participant?->access_state,
                'block_display_mode' => $participant?->block_display_mode,
                'can_send' => (bool) ($participant?->can_send ?? false),
                'can_attach' => (bool) ($participant?->can_attach ?? false),
                'can_invite' => (bool) ($participant?->can_invite ?? false),
                'can_remove' => (bool) ($participant?->can_remove ?? false),
                'can_manage' => (bool) ($participant?->can_manage ?? false),
                'can_moderate' => (bool) ($participant?->can_moderate ?? false),
            ],
        ];

        if ($this->canViewAdminMetadata) {
            $data['admin_metadata'] = [
                'owner_id' => $this->owner_id,
                'created_by' => $this->created_by,
                'created_from_conversation_id' => $this->created_from_conversation_id,
                'join_policy' => $this->join_policy,
                'history_import_mode' => $this->history_import_mode,
                'source' => $this->source,
                'external_marker' => in_array($this->source, ['api', 'webhook'], true),
            ];
        }

        return $data;
    }
}
