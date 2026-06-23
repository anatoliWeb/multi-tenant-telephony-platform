<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'conversation_id' => $this->conversation_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'status' => $this->status,
            'is_imported' => (bool) $this->is_imported,
            'preview_metadata' => data_get($this->metadata, 'preview', []),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

