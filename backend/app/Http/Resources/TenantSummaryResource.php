<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'uuid' => data_get($this->resource, 'uuid', data_get($this->resource, 'id')),
            'name' => data_get($this->resource, 'name'),
            'slug' => data_get($this->resource, 'slug'),
            'status' => data_get($this->resource, 'status'),
            'timezone' => data_get($this->resource, 'timezone'),
            'locale' => data_get($this->resource, 'locale'),
            'currency' => data_get($this->resource, 'currency'),
            'settings' => data_get($this->resource, 'settings', []),
            'activated_at' => data_get($this->resource, 'activated_at'),
            'suspended_at' => data_get($this->resource, 'suspended_at'),
            'created_at' => data_get($this->resource, 'created_at'),
            'updated_at' => data_get($this->resource, 'updated_at'),
        ];
    }
}
