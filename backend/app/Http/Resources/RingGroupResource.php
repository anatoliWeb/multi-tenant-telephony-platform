<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RingGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'strategy' => $this->strategy?->value ?? $this->strategy,
            'status' => $this->status?->value ?? $this->status,
            'ring_timeout_seconds' => $this->ring_timeout_seconds,
            'max_ring_duration_seconds' => $this->max_ring_duration_seconds,
            'failover_destination_type' => $this->failover_destination_type,
            'failover_destination_id' => $this->failover_destination_id,
            'settings' => $this->settings ?? [],
            'metadata' => $this->metadata ?? [],
            'members_count' => $this->members_count ?? null,
            'active_members_count' => $this->active_members_count ?? null,
            'members' => $this->whenLoaded('members', fn (): array => RingGroupMemberResource::collection($this->members)->resolve()),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
