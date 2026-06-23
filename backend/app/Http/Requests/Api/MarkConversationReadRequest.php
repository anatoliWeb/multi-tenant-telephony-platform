<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MarkConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_key' => ['required', 'string', 'max:128'],
            'until_message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
