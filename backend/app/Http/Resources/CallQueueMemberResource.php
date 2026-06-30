<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallQueueMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'call_queue_id' => $this->call_queue_id,
            'member_type' => $this->member_type?->value ?? $this->member_type,
            'member_id' => $this->member_id,
            'extension_id' => $this->extension_id,
            'user_id' => $this->user_id,
            'extension' => $this->whenLoaded('extension', fn (): ?array => $this->extension ? [
                'id' => $this->extension->id,
                'number' => $this->extension->number,
                'label' => $this->extension->label,
                'status' => $this->extension->status?->value ?? $this->extension->status,
            ] : null),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'priority' => $this->priority,
            'penalty' => $this->penalty,
            'is_active' => (bool) $this->is_active,
            'is_paused' => (bool) $this->is_paused,
            'paused_at' => $this->paused_at?->toISOString(),
            'pause_reason' => $this->pause_reason,
            'last_call_at' => $this->last_call_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
