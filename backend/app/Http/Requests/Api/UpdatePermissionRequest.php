<?php

namespace App\Http\Requests\Api;

use App\Enums\Rbac\PermissionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scope = $this->input('scope', $this->route('permission')?->scope?->value ?? PermissionScope::Platform->value);
        $scopeReference = $this->input('scope_reference', $this->route('permission')?->scope_reference ?? $scope);
        if ($scope === PermissionScope::Tenant->value && $this->filled('tenant_id')) {
            $scopeReference = (string) $this->input('tenant_id');
        }

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->ignore($this->route('permission')?->getKey())
                    ->where(fn ($query) => $query
                        ->where('scope', $scope)
                        ->where('scope_reference', $scopeReference)),
            ],
            'scope' => ['sometimes', 'string', 'in:'.PermissionScope::Platform->value.','.PermissionScope::Tenant->value],
            'scope_reference' => ['sometimes', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'translations' => ['sometimes', 'array'],
            'translations.*.label' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
