<?php

namespace Database\Seeders\settings;

/**
 * Seeds global platform defaults shared across frontend and backend.
 *
 * WHY:
 * General settings define the base platform identity and behavior before any
 * user, role, permission, frontend, or backend-specific overrides are applied.
 */
class GeneralSettingsSeeder extends BaseSettingsSeeder
{
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'app.name',
                'label' => 'Application Name',
                'group' => 'general',
                'description' => 'Human-readable platform name displayed across admin and client interfaces.',
                'type' => 'string',
                'value' => 'SaaS Admin',
                'default_value' => 'SaaS Admin',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.environment_label',
                'label' => 'Environment Label',
                'group' => 'general',
                'description' => 'Visible environment label used to distinguish local, staging, and production systems.',
                'type' => 'string',
                'value' => 'local',
                'default_value' => 'local',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.timezone',
                'label' => 'Default Timezone',
                'group' => 'general',
                'description' => 'Default timezone used for displaying dates and scheduling backend processes.',
                'type' => 'string',
                'value' => 'Europe/Kyiv',
                'default_value' => 'Europe/Kyiv',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.date_format',
                'label' => 'Date Format',
                'group' => 'general',
                'description' => 'Default date format used across the admin UI.',
                'type' => 'string',
                'value' => 'Y-m-d',
                'default_value' => 'Y-m-d',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.datetime_format',
                'label' => 'Date Time Format',
                'group' => 'general',
                'description' => 'Default date and time format used across the platform.',
                'type' => 'string',
                'value' => 'Y-m-d H:i',
                'default_value' => 'Y-m-d H:i',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.maintenance_mode',
                'label' => 'Maintenance Mode',
                'group' => 'general',
                'description' => 'Controls whether the platform should behave as temporarily unavailable.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'app.support_email',
                'label' => 'Support Email',
                'group' => 'general',
                'description' => 'Default support contact email shown in system messages and admin screens.',
                'type' => 'string',
                'value' => 'support@example.com',
                'default_value' => 'support@example.com',
                'is_frontend' => true,
                'is_backend' => true,
            ],
        ]);
    }
}
