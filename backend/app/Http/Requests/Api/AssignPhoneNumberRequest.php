<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AssignPhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
