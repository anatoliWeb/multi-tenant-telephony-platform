<?php

namespace App\Http\Requests\Api;

use App\Enums\Ivr\IvrActionType;
use App\Enums\Ivr\IvrDestinationType;
use App\Enums\Ivr\IvrMenuStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIvrMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('ivr_menus', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(IvrMenuStatus::values())],
            'greeting_text' => ['nullable', 'string'],
            'greeting_audio_path' => ['nullable', 'string', 'max:255'],
            'repeat_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'input_timeout_seconds' => ['sometimes', 'integer', 'min:3', 'max:120'],
            'max_invalid_attempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'timeout_action_type' => ['sometimes', Rule::in(IvrActionType::values())],
            'timeout_destination_type' => ['nullable', Rule::in(IvrDestinationType::values())],
            'timeout_destination_id' => ['nullable', 'integer', 'min:1'],
            'invalid_action_type' => ['sometimes', Rule::in(IvrActionType::values())],
            'invalid_destination_type' => ['nullable', Rule::in(IvrDestinationType::values())],
            'invalid_destination_id' => ['nullable', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
