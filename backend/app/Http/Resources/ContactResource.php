<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'company_name' => $this->company_name,
            'job_title' => $this->job_title,
            'notes' => $this->notes,
            'status' => $this->status?->value ?? $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'phones' => ContactPhoneResource::collection($this->whenLoaded('phones'))->resolve(),
            'emails' => ContactEmailResource::collection($this->whenLoaded('emails'))->resolve(),
            'tags' => ContactTagResource::collection($this->whenLoaded('tags'))->resolve(),
            'primary_phone' => $this->whenLoaded('phones', function (): ?array {
                $phone = $this->phones->firstWhere('is_primary', true) ?? $this->phones->first();

                return $phone ? (new ContactPhoneResource($phone))->resolve() : null;
            }),
            'primary_email' => $this->whenLoaded('emails', function (): ?array {
                $email = $this->emails->firstWhere('is_primary', true) ?? $this->emails->first();

                return $email ? (new ContactEmailResource($email))->resolve() : null;
            }),
        ];
    }
}
