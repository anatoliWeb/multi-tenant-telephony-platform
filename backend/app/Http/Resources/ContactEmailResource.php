<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactEmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'label' => $this->label,
            'email' => $this->email,
            'normalized_email' => $this->normalized_email,
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
