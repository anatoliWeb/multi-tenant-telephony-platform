<?php

namespace App\Http\Requests\Api;

use App\Enums\Rbac\RoleScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scope = $this->input('scope', $this->route('role')?->scope?->value ?? RoleScope::Platform->value);
        $scopeReference = $this->input('scope_reference', $this->route('role')?->scope_reference ?? $scope);
        if ($scope === RoleScope::Tenant->value && $this->filled('tenant_id')) {
            $scopeReference = (string) $this->input('tenant_id');
        }

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($this->route('role')?->getKey())
                    ->where(fn ($query) => $query
                        ->where('scope', $scope)
                        ->where('scope_reference', $scopeReference)),
            ],
            'scope' => ['sometimes', 'string', 'in:'.RoleScope::Platform->value.','.RoleScope::Tenant->value],
            'scope_reference' => ['sometimes', 'string', 'max:64'],
            'tenant_id' => ['sometimes', 'nullable', 'uuid', 'exists:tenants,id'],
            'is_system' => ['sometimes', 'boolean'],
            'is_protected' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'translations' => ['sometimes', 'array'],
            'translations.*.label' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
