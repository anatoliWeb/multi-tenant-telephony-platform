<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallQueueResource extends JsonResource
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
            'max_wait_time_seconds' => $this->max_wait_time_seconds,
            'ring_timeout_seconds' => $this->ring_timeout_seconds,
            'retry_delay_seconds' => $this->retry_delay_seconds,
            'max_attempts' => $this->max_attempts,
            'music_on_hold' => $this->music_on_hold,
            'announce_position' => (bool) $this->announce_position,
            'announce_estimated_wait' => (bool) $this->announce_estimated_wait,
            'overflow_destination_type' => $this->overflow_destination_type,
            'overflow_destination_id' => $this->overflow_destination_id,
            'settings' => $this->settings ?? [],
            'metadata' => $this->metadata ?? [],
            'members_count' => $this->members_count ?? null,
            'active_members_count' => $this->active_members_count ?? null,
            'paused_members_count' => $this->paused_members_count ?? null,
            'members' => $this->whenLoaded('members', fn (): array => CallQueueMemberResource::collection($this->members)->resolve()),
            'overflow_destination_summary' => $this->overflow_destination_type && $this->overflow_destination_id
                ? sprintf('%s:%s', $this->overflow_destination_type, $this->overflow_destination_id)
                : null,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
