<?php

namespace App\Http\Resources;

use App\Models\CallEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CallEvent
 */
class CallEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider_event_id' => $this->provider_event_id,
            'provider_id' => $this->provider_id,
            'type' => $this->type?->value ?? $this->type,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'sequence' => $this->sequence,
            'summary' => [
                'disposition' => $this->payload['disposition'] ?? null,
                'hangup_cause' => $this->payload['hangup_cause'] ?? null,
                'failure_code' => $this->payload['failure_code'] ?? null,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
