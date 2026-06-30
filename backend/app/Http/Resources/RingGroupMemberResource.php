<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RingGroupMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'ring_group_id' => $this->ring_group_id,
            'member_type' => $this->member_type?->value ?? $this->member_type,
            'extension_id' => $this->extension_id,
            'user_id' => $this->user_id,
            'extension' => $this->whenLoaded('extension', function (): ?array {
                if (! $this->extension) {
                    return null;
                }

                return [
                    'id' => $this->extension->id,
                    'number' => $this->extension->number,
                    'label' => $this->extension->label,
                    'status' => $this->extension->status?->value ?? $this->extension->status,
                ];
            }),
            'user' => $this->whenLoaded('user', function (): ?array {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'priority' => $this->priority,
            'delay_seconds' => $this->delay_seconds,
            'timeout_seconds' => $this->timeout_seconds,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
