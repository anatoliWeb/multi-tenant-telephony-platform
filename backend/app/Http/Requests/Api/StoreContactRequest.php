<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['active', 'archived', 'blocked'])],
            'phones' => ['sometimes', 'array', 'max:10'],
            'phones.*.label' => ['nullable', 'string', 'max:64'],
            'phones.*.raw_number' => ['required_with:phones', 'string', 'max:64'],
            'phones.*.extension' => ['nullable', 'string', 'max:32'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'phones.*.is_sms_capable' => ['nullable', 'boolean'],
            'phones.*.is_active' => ['nullable', 'boolean'],
            'emails' => ['sometimes', 'array', 'max:10'],
            'emails.*.label' => ['nullable', 'string', 'max:64'],
            'emails.*.email' => ['required_with:emails', 'email:rfc', 'max:255'],
            'emails.*.is_primary' => ['nullable', 'boolean'],
            'emails.*.is_active' => ['nullable', 'boolean'],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'exists:contact_tags,id'],
        ];
    }
}
