<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestIvrMenuRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input_type' => ['sometimes', 'string', Rule::in(['digit', 'timeout', 'invalid'])],
            'digit' => ['nullable', 'string', 'regex:/^[0-9*#]$/'],
        ];
    }
}
