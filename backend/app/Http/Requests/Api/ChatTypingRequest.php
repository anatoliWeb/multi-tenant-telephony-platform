<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatTypingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_key' => ['nullable', 'string', 'max:191'],
            'device_type' => ['nullable', 'string', Rule::in(['browser', 'mobile', 'desktop', 'tablet', 'api', 'unknown'])],
        ];
    }
}
