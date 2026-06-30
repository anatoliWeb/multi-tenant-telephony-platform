<?php

namespace App\Http\Requests\Api;

use App\Enums\CallQueues\CallQueueMemberType;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallQueueMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'member_type' => ['sometimes', Rule::in(CallQueueMemberType::values())],
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
                Rule::exists('tenant_memberships', 'user_id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')),
            ],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'penalty' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
