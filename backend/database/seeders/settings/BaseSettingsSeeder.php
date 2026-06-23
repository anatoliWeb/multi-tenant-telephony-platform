<?php

namespace Database\Seeders\settings;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Shared settings seeder helper.
 *
 * WHY:
 * Centralizing upsert logic guarantees idempotent bootstrap behavior and keeps
 * all setting categories aligned to the same persistence contract.
 */
abstract class BaseSettingsSeeder extends Seeder
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function seedSettings(array $items): void
    {
        foreach ($items as $item) {
            $scope = [
                'key' => $item['key'],
                'scope_user_id' => $item['scope_user_id'] ?? null,
                'scope_role_id' => $item['scope_role_id'] ?? null,
                'scope_permission_id' => $item['scope_permission_id'] ?? null,
            ];

            $payload = [
                'label' => $item['label'],
                'group' => $item['group'],
                'description' => $item['description'] ?? null,
                'type' => $item['type'],
                'value' => $this->serializeValue($item['value'] ?? null),
                'default_value' => $this->serializeValue($item['default_value'] ?? null),
                'is_frontend' => $item['is_frontend'] ?? true,
                'is_backend' => $item['is_backend'] ?? true,
                'priority' => $item['priority'] ?? 100,
                'is_active' => $item['is_active'] ?? true,
                'is_system' => $item['is_system'] ?? true,
                'created_by' => null,
                'updated_by' => null,
            ];

            SystemSetting::updateOrCreate($scope, $payload);
        }
    }

    protected function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
