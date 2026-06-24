<?php

namespace App\Http\Resources;

use App\Models\Permission;
use App\Services\Localization\RbacLocalizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permission = $this->resource;
        $localization = app(RbacLocalizationService::class);

        if ($permission instanceof Permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'scope' => $permission->scope?->value ?? $permission->scope,
                'label' => $localization->getPermissionLabel($permission),
                'description' => $localization->getPermissionDescription($permission),
                'translations' => $localization->getPermissionTranslations($permission),
            ];
        }

        if (is_string($permission)) {
            return [
                'id' => null,
                'name' => $permission,
                'label' => $permission,
                'description' => null,
                'translations' => [],
            ];
        }

        if (is_array($permission)) {
            $name = data_get($permission, 'name');
            $description = data_get($permission, 'description');

            return [
                'id' => data_get($permission, 'id'),
                'name' => is_string($name) ? $name : '',
                'scope' => data_get($permission, 'scope'),
                'label' => is_string($name) ? $name : '',
                'description' => is_string($description) ? $description : null,
                'translations' => data_get($permission, 'translations', []),
            ];
        }

            return [
                'id' => null,
                'name' => '',
                'scope' => null,
                'label' => '',
                'description' => null,
                'translations' => [],
            ];
        }
}
