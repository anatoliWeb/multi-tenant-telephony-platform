<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:16'],
            'label' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'archived'])],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ];
    }
}
