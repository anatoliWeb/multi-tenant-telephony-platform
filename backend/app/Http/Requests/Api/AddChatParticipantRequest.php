<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddChatParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'role' => ['nullable', 'string', Rule::in(['admin', 'member', 'viewer', 'support'])],
            'capabilities' => ['nullable', 'array'],
            'capabilities.can_invite' => ['nullable', 'boolean'],
            'capabilities.can_remove' => ['nullable', 'boolean'],
            'capabilities.can_send' => ['nullable', 'boolean'],
            'capabilities.can_attach' => ['nullable', 'boolean'],
            'capabilities.can_manage' => ['nullable', 'boolean'],
            'capabilities.can_moderate' => ['nullable', 'boolean'],
        ];
    }
}
