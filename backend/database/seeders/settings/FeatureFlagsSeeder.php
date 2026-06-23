<?php

namespace Database\Seeders\settings;

/**
 * Seeds dynamic feature flags.
 *
 * WHY:
 * Feature flags allow gradual rollout, experimentation, beta testing,
 * and runtime feature management without redeploying the application.
 *
 * This architecture prepares the platform for:
 * - staged releases
 * - tenant-specific enablement
 * - beta features
 * - A/B testing
 * - realtime feature activation
 */
class FeatureFlagsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'features.new_dashboard',
                'label' => 'Enable New Dashboard',
                'group' => 'features',
                'description' => 'Controls availability of the modern Vue dashboard experience.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.command_palette',
                'label' => 'Enable Command Palette',
                'group' => 'features',
                'description' => 'Enables keyboard-first command palette workflows.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'features.realtime_activity',
                'label' => 'Enable Realtime Activity',
                'group' => 'features',
                'description' => 'Enables realtime activity stream updates across admin panels.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.realtime_notifications',
                'label' => 'Enable Realtime Notifications',
                'group' => 'features',
                'description' => 'Enables websocket-driven in-app notifications.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.experimental_forms',
                'label' => 'Enable Experimental Forms',
                'group' => 'features',
                'description' => 'Allows access to next-generation dynamic form workflows.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'features.beta_settings_ui',
                'label' => 'Enable Beta Settings UI',
                'group' => 'features',
                'description' => 'Controls access to the next-generation settings management interface.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'features.ai_tools',
                'label' => 'Enable AI Tools',
                'group' => 'features',
                'description' => 'Controls visibility of AI-powered platform functionality.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.activity_timeline',
                'label' => 'Enable Activity Timeline',
                'group' => 'features',
                'description' => 'Enables advanced timeline visualization in activity modules.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'features.websocket_presence',
                'label' => 'Enable Websocket Presence',
                'group' => 'features',
                'description' => 'Enables online user presence indicators and live counters.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.optimistic_ui',
                'label' => 'Enable Optimistic UI',
                'group' => 'features',
                'description' => 'Enables optimistic frontend state updates before server confirmation.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'features.audit_exports',
                'label' => 'Enable Audit Export',
                'group' => 'features',
                'description' => 'Allows exporting activity and audit logs from admin panels.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'features.advanced_rbac',
                'label' => 'Enable Advanced RBAC',
                'group' => 'features',
                'description' => 'Enables advanced role and permission inheritance workflows.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],
        ]);
    }
}
