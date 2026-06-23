<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePrivateGroupFromDirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'min:1', 'distinct', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'history_import_mode' => ['required', 'string', Rule::in(['none', 'from_date', 'from_message', 'full'])],
            'history_import_from_message_id' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:history_import_mode,from_message',
                'exists:messages,id',
            ],
            'history_import_from_at' => [
                'nullable',
                'date',
                'required_if:history_import_mode,from_date',
            ],
        ];
    }
}

