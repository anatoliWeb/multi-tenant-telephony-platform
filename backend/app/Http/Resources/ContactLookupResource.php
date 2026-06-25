<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactLookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'display_name' => $this->display_name,
            'company_name' => $this->company_name,
            'status' => $this->status?->value ?? $this->status,
            'primary_phone' => $this->phones->firstWhere('is_primary', true)?->raw_number
                ?? $this->phones->first()?->raw_number,
        ];
    }
}
