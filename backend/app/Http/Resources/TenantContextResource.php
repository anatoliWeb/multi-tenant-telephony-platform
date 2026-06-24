<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant' => TenantSummaryResource::make(data_get($this->resource, 'tenant')),
            'membership' => TenantMembershipResource::make(data_get($this->resource, 'membership')),
            'current_tenant_id' => data_get($this->resource, 'current_tenant_id'),
        ];
    }
}
