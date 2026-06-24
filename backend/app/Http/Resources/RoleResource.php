<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Services\Localization\RbacLocalizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;
        $localization = app(RbacLocalizationService::class);

        if ($role instanceof Role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'scope' => $role->scope?->value ?? $role->scope,
                'tenant_id' => $role->tenant_id,
                'is_system' => (bool) $role->is_system,
                'is_protected' => (bool) $role->is_protected,
                'label' => $localization->getRoleLabel($role),
                'description' => $localization->getRoleDescription($role),
                'translations' => $localization->getRoleTranslations($role),
            ];
        }

        if (is_string($role)) {
            return [
                'id' => null,
                'name' => $role,
                'label' => $role,
                'description' => null,
                'translations' => [],
            ];
        }

        if (is_array($role)) {
            $name = data_get($role, 'name');
            $description = data_get($role, 'description');

            return [
                'id' => data_get($role, 'id'),
                'name' => is_string($name) ? $name : '',
                'scope' => data_get($role, 'scope'),
                'tenant_id' => data_get($role, 'tenant_id'),
                'is_system' => (bool) data_get($role, 'is_system', false),
                'is_protected' => (bool) data_get($role, 'is_protected', false),
                'label' => is_string($name) ? $name : '',
                'description' => is_string($description) ? $description : null,
                'translations' => data_get($role, 'translations', []),
            ];
        }

            return [
                'id' => null,
                'name' => '',
                'scope' => null,
                'tenant_id' => null,
                'is_system' => false,
                'is_protected' => false,
                'label' => '',
                'description' => null,
                'translations' => [],
            ];
        }
}
