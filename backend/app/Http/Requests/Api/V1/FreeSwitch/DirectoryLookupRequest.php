<?php

namespace App\Http\Requests\Api\V1\FreeSwitch;

use Illuminate\Foundation\Http\FormRequest;

class DirectoryLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user' => trim((string) $this->query('user', '')),
            'domain' => trim((string) $this->query('domain', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'user' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:32'],
            'domain' => ['required', 'string', 'max:255'],
        ];
    }
}
