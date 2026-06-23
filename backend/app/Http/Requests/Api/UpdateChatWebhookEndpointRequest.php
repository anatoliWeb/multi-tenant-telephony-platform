<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatWebhookEndpointRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:191'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1', 'max:20'],
            'events.*' => ['required_with:events', 'string', 'distinct', Rule::in([
                'message.created',
                'message.updated',
                'message.deleted',
                'message.read',
                'message.device_read',
                'message.delivery.updated',
                'conversation.created',
                'participant.joined',
                'participant.left',
                'participant.blocked',
                'participant.unblocked',
                'attachment.created',
                'participant.access_changed',
            ])],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled', 'failed'])],
            'scopes' => ['sometimes', 'array', 'min:1', 'max:20'],
            'scopes.*' => ['required_with:scopes', 'string', 'distinct', Rule::in((array) config('chat.external_api.scopes.allowed', []))],
        ];
    }
}
