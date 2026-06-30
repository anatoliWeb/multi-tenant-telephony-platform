<?php

namespace App\Http\Resources;

use App\Models\IvrOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IvrOption
 */
class IvrOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'ivr_menu_id' => $this->ivr_menu_id,
            'digit' => $this->digit,
            'label' => $this->label,
            'destination_type' => $this->destination_type,
            'destination_id' => $this->destination_id,
            'destination_summary' => $this->resolveDestinationSummary($this->destination_type, $this->destination_id),
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }

    private function resolveDestinationSummary(?string $type, ?int $id): ?string
    {
        if (! $type || in_array($type, ['hangup', 'voicemail_placeholder'], true)) {
            return $type;
        }

        return $id ? sprintf('%s:%s', $type, $id) : null;
    }
}
