<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
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
        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'type' => $this->type,
            'body' => $this->body,
            'status' => $this->status,
            'is_imported' => (bool) $this->is_imported,
            'sent_at' => $this->sent_at?->toISOString(),
            'edited_at' => $this->edited_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'attachments_count' => (int) ($this->attachments_count ?? 0),
            'attachments' => ChatAttachmentResource::collection($this->whenLoaded('attachments')),
        ];

        if ($this->type === 'system' && is_array($this->metadata) && data_get($this->metadata, 'event') === 'call_started') {
            $data['metadata'] = array_filter([
                'event' => data_get($this->metadata, 'event'),
                'call_direction' => data_get($this->metadata, 'call_direction'),
                'initiator_user_id' => data_get($this->metadata, 'initiator_user_id'),
                'target_user_id' => data_get($this->metadata, 'target_user_id'),
                'target_display_name' => data_get($this->metadata, 'target_display_name'),
                'target_extension' => data_get($this->metadata, 'target_extension'),
                'started_at' => data_get($this->metadata, 'started_at'),
            ], static fn ($value) => $value !== null);
        }

        if ($this->canViewAdminMetadata) {
            $data['imported_from_conversation_id'] = $this->imported_from_conversation_id;
            $data['imported_from_message_id'] = $this->imported_from_message_id;
            $data['device_read_count'] = (int) ($this->device_read_count ?? 0);
            $data['device_reads'] = $this->resource->relationLoaded('deviceReads')
                ? $this->deviceReads
                    ->map(static fn ($read): array => [
                        'user_id' => $read->user_id,
                        'read_at' => $read->read_at?->toISOString(),
                        'device_type' => $read->device_type,
                    ])
                    ->values()
                    ->all()
                : [];
        }

        return $data;
    }
}
