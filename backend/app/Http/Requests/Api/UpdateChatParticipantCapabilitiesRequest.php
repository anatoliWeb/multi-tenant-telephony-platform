<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatParticipantCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'can_invite' => ['nullable', 'boolean'],
            'can_remove' => ['nullable', 'boolean'],
            'can_send' => ['nullable', 'boolean'],
            'can_attach' => ['nullable', 'boolean'],
            'can_manage' => ['nullable', 'boolean'],
            'can_moderate' => ['nullable', 'boolean'],
        ];
    }
}

