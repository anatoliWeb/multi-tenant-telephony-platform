<?php

namespace App\Http\Requests\Api;

use App\Enums\RingGroups\RingGroupMemberType;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRingGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'member_type' => ['required', Rule::in(RingGroupMemberType::values())],
            'extension_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->input('member_type') === RingGroupMemberType::Extension->value),
                'prohibited_unless:member_type,extension',
                Rule::exists('extensions', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->input('member_type') === RingGroupMemberType::User->value),
                'prohibited_unless:member_type,user',
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
