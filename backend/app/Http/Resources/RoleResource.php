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
                'label' => is_string($name) ? $name : '',
                'description' => is_string($description) ? $description : null,
                'translations' => data_get($role, 'translations', []),
            ];
        }

        return [
            'id' => null,
            'name' => '',
            'label' => '',
            'description' => null,
            'translations' => [],
        ];
    }
}
