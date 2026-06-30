<?php

namespace App\Http\Requests\Api;

use App\Enums\Ivr\IvrDestinationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIvrOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'digit' => ['required', 'string', 'regex:/^[0-9*#]$/'],
            'label' => ['required', 'string', 'max:255'],
            'destination_type' => ['required', Rule::in(IvrDestinationType::values())],
            'destination_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
