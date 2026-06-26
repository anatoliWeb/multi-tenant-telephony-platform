<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:32'],
            'display_number' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', Rule::in(array_column(PhoneNumberType::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(PhoneNumberStatus::cases(), 'value'))],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_primary' => ['nullable', 'boolean'],
            'provider_name' => ['nullable', 'string', 'max:64'],
            'provider_reference' => ['nullable', 'string', 'max:128'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'capabilities' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'purchased_at' => ['nullable', 'date'],
        ];
    }
}
