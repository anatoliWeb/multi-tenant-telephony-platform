<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGroupConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'string', Rule::in(['private', 'public'])],
            'join_policy' => ['nullable', 'string', Rule::in(['invite_only', 'participants_can_invite', 'anyone_with_permission', 'public_join'])],
            'participant_ids' => ['required', 'array', 'min:1', 'max:100'],
            'participant_ids.*' => ['integer', 'min:1', 'distinct', 'exists:users,id'],
        ];
    }
}
