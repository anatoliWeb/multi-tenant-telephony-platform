<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatWebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events ?? [],
            'scopes' => array_values(array_filter((array) data_get($metadata, 'token_scopes', []))),
            'is_active' => (bool) $this->is_active,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
