<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockChatParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_display_mode' => ['required', 'string', Rule::in(['hide_chat', 'show_notice', 'show_read_only_history'])],
            'blocked_reason' => ['nullable', 'string', 'max:1000'],
            'history_visible_until_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'history_visible_until_at' => ['nullable', 'date'],
        ];
    }
}

