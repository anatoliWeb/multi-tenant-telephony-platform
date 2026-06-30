<?php

namespace App\Http\Requests\Api;

use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Models\RingGroup;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $ringGroup = $this->route('ringGroup');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('ring_groups', 'slug')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($ringGroup instanceof RingGroup ? $ringGroup->getKey() : null),
            ],
            'description' => ['nullable', 'string'],
            'strategy' => ['sometimes', Rule::in(RingGroupStrategy::values())],
            'status' => ['sometimes', Rule::in(RingGroupStatus::values())],
            'ring_timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'max_ring_duration_seconds' => ['sometimes', 'integer', 'min:1', 'max:3600'],
            'failover_destination_type' => ['nullable', Rule::in(['extension', 'user'])],
            'failover_destination_id' => ['nullable', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
