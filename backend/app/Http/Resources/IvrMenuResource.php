<?php

namespace App\Http\Resources;

use App\Models\IvrMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IvrMenu
 */
class IvrMenuResource extends JsonResource
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
            'status' => $this->status?->value ?? $this->status,
            'greeting_text' => $this->greeting_text,
            'greeting_audio_path' => $this->greeting_audio_path,
            'repeat_count' => $this->repeat_count,
            'input_timeout_seconds' => $this->input_timeout_seconds,
            'max_invalid_attempts' => $this->max_invalid_attempts,
            'timeout_action_type' => $this->timeout_action_type,
            'timeout_destination_type' => $this->timeout_destination_type,
            'timeout_destination_id' => $this->timeout_destination_id,
            'invalid_action_type' => $this->invalid_action_type,
            'invalid_destination_type' => $this->invalid_destination_type,
            'invalid_destination_id' => $this->invalid_destination_id,
            'timeout_destination_summary' => $this->resolveDestinationSummary($this->timeout_destination_type, $this->timeout_destination_id),
            'invalid_destination_summary' => $this->resolveDestinationSummary($this->invalid_destination_type, $this->invalid_destination_id),
            'settings' => $this->settings,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'options_count' => $this->whenCounted('options'),
            'active_options_count' => $this->whenCounted('activeOptions'),
            'options' => $this->whenLoaded('options', fn (): array => IvrOptionResource::collection($this->options)->resolve()),
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
