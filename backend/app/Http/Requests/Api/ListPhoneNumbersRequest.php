<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPhoneNumbersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:64'],
            'number' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', Rule::in(array_column(PhoneNumberType::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(PhoneNumberStatus::cases(), 'value'))],
            'assigned' => ['nullable', Rule::in(['true', 'false', 'assigned', 'unassigned'])],
            'assigned_user' => ['nullable', 'integer'],
            'primary' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'provider' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', Rule::in(['display_number', 'normalized_number', 'status', 'type', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
