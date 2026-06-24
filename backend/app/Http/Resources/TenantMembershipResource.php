<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'tenant_id' => data_get($this->resource, 'tenant_id'),
            'user_id' => data_get($this->resource, 'user_id'),
            'status' => data_get($this->resource, 'status'),
            'invited_by' => data_get($this->resource, 'invited_by'),
            'invited_at' => data_get($this->resource, 'invited_at'),
            'accepted_at' => data_get($this->resource, 'accepted_at'),
            'activated_at' => data_get($this->resource, 'activated_at'),
            'suspended_at' => data_get($this->resource, 'suspended_at'),
            'tenant' => TenantSummaryResource::make(data_get($this->resource, 'tenant')),
            'created_at' => data_get($this->resource, 'created_at'),
            'updated_at' => data_get($this->resource, 'updated_at'),
        ];
    }
}
