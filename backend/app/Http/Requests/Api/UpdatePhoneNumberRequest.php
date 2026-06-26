<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'string', 'max:32'],
            'display_number' => ['sometimes', 'nullable', 'string', 'max:32'],
            'type' => ['sometimes', Rule::in(array_column(PhoneNumberType::cases(), 'value'))],
            'status' => ['sometimes', Rule::in(array_column(PhoneNumberStatus::cases(), 'value'))],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'is_primary' => ['sometimes', 'boolean'],
            'provider_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:128'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:8'],
            'capabilities' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
