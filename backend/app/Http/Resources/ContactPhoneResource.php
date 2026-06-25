<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactPhoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'label' => $this->label,
            'raw_number' => $this->raw_number,
            'normalized_number' => $this->normalized_number,
            'extension' => $this->extension,
            'is_primary' => (bool) $this->is_primary,
            'is_sms_capable' => (bool) $this->is_sms_capable,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
