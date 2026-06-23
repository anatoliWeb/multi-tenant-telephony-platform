<?php

namespace Database\Seeders\settings;

/**
 * Seeds frontend behavior defaults used by SPA shell.
 */
class FrontendSettingsSeeder extends BaseSettingsSeeder
{
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'frontend.theme.default',
                'label' => 'Default Theme',
                'group' => 'appearance',
                'description' => 'Initial SPA theme before user-specific overrides are applied.',
                'type' => 'string',
                'value' => 'dark',
                'default_value' => 'dark',
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.theme.allow_switching',
                'label' => 'Theme Switching Enabled',
                'group' => 'appearance',
                'description' => 'Controls whether end users can switch UI themes.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.sidebar.collapsed',
                'label' => 'Sidebar Collapsed by Default',
                'group' => 'dashboard',
                'description' => 'Default navigation density for new admin sessions.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.dashboard.default_layout',
                'label' => 'Dashboard Default Layout',
                'group' => 'dashboard',
                'description' => 'Initial dashboard layout template used on first visit.',
                'type' => 'string',
                'value' => 'analytics',
                'default_value' => 'analytics',
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.enable_realtime',
                'label' => 'Enable Realtime UI',
                'group' => 'realtime',
                'description' => 'Controls realtime subscriptions in the admin SPA.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.enable_command_palette',
                'label' => 'Enable Command Palette',
                'group' => 'dashboard',
                'description' => 'Enables keyboard-first command palette in admin.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.enable_notifications',
                'label' => 'Enable Notifications',
                'group' => 'notifications',
                'description' => 'Toggles in-app notification surfaces.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'frontend.enable_activity_feed',
                'label' => 'Enable Activity Feed',
                'group' => 'activity',
                'description' => 'Controls visibility of activity feed widgets.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
        ]);
    }
}
