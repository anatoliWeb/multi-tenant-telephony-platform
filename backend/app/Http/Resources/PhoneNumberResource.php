<?php

namespace App\Http\Resources;

use App\Models\Extension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'normalized_number' => $this->normalized_number,
            'display_number' => $this->display_number,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'assignment_status' => $this->assignment_status?->value ?? $this->assignment_status,
            'is_primary' => (bool) $this->is_primary,
            'provider_name' => $this->provider_name,
            'provider_reference' => $this->provider_reference,
            'country_code' => $this->country_code,
            'capabilities' => $this->capabilities ?? [],
            'assigned_user' => $this->whenLoaded('assignedUser', function (): ?array {
                if (! $this->assignedUser) {
                    return null;
                }

                /** @var Extension|null $extension */
                $extension = $this->assignedUser->assignedExtensions->first();

                return [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->name,
                    'email' => $this->assignedUser->email,
                    'extension' => $extension ? [
                        'id' => $extension->id,
                        'number' => $extension->number,
                        'label' => $extension->label,
                    ] : null,
                ];
            }),
            'activated_at' => $this->activated_at?->toISOString(),
            'purchased_at' => $this->purchased_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
