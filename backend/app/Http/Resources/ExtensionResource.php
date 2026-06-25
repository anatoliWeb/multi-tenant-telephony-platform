<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $providerState = (array) data_get($this->metadata, 'provider_state', []);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'number' => $this->number,
            'label' => $this->label,
            'status' => $this->status?->value ?? $this->status,
            'provisioning_status' => $this->provisioning_status?->value ?? $this->provisioning_status,
            'registration_status' => $this->registration_status?->value ?? $this->registration_status,
            'endpoint_key' => $this->endpoint_key,
            'provider_name' => $this->provider_name,
            'provider_resource_id' => $this->provider_resource_id,
            'credential_username' => $this->credential_username,
            'credential' => $this->whenLoaded('credential', function (): ?array {
                if (! $this->credential) {
                    return null;
                }

                return [
                    'username' => $this->credential->username,
                    'secret_hint' => $this->credential->secret_hint,
                    'version' => $this->credential->version,
                    'rotated_at' => $this->credential->rotated_at?->toISOString(),
                ];
            }),
            'assigned_user' => $this->whenLoaded('assignedUser', function (): ?array {
                if (! $this->assignedUser) {
                    return null;
                }

                return [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->name,
                    'email' => $this->assignedUser->email,
                ];
            }),
            'assigned_contact' => $this->whenLoaded('assignedContact', function (): ?array {
                if (! $this->assignedContact) {
                    return null;
                }

                return [
                    'id' => $this->assignedContact->id,
                    'display_name' => $this->assignedContact->display_name,
                    'company_name' => $this->assignedContact->company_name,
                ];
            }),
            'provider_state' => [
                'provider' => $providerState['provider'] ?? $this->provider_name,
                'endpoint_status' => $providerState['endpoint_status'] ?? null,
                'registration_status' => $providerState['registration_status'] ?? ($this->registration_status?->value ?? $this->registration_status),
                'address' => $providerState['address'] ?? null,
                'updated_at' => $providerState['updated_at'] ?? null,
            ],
            'last_provisioned_at' => $this->last_provisioned_at?->toISOString(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
