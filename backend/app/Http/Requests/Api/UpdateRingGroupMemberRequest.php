<?php

namespace App\Http\Requests\Api;

use App\Enums\RingGroups\RingGroupMemberType;
use App\Models\RingGroup;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRingGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'member_type' => ['sometimes', Rule::in(RingGroupMemberType::values())],
            'extension_id' => [
                'nullable',
                'integer',
                Rule::exists('extensions', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:3600'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
