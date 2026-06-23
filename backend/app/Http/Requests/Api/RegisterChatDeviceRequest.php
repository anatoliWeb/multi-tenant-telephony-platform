<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterChatDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_key' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', Rule::in(['browser', 'mobile', 'desktop', 'tablet', 'api', 'unknown'])],
            'platform' => ['nullable', 'string', 'max:64'],
            'browser' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
