<?php

namespace Database\Seeders\settings;

/**
 * Seeds dashboard and widget configuration defaults.
 *
 * WHY:
 * Dashboard settings control SPA layout behavior, widgets, refresh intervals,
 * and personalization defaults without requiring frontend rebuilds.
 */
class DashboardSettingsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'dashboard.default_layout',
                'label' => 'Default Dashboard Layout',
                'group' => 'dashboard',
                'description' => 'Default dashboard layout template used for new sessions.',
                'type' => 'string',
                'value' => 'analytics',
                'default_value' => 'analytics',
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_widgets',
                'label' => 'Enable Dashboard Widgets',
                'group' => 'dashboard',
                'description' => 'Controls whether dashboard widgets are enabled globally.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.default_widgets',
                'label' => 'Default Dashboard Widgets',
                'group' => 'dashboard',
                'description' => 'Default widget set shown on first dashboard visit.',
                'type' => 'json',
                'value' => [
                    'stats',
                    'activity',
                    'users',
                    'tokens',
                    'system_status',
                    'realtime',
                ],
                'default_value' => [
                    'stats',
                    'activity',
                    'users',
                    'tokens',
                    'system_status',
                    'realtime',
                ],
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.refresh_interval',
                'label' => 'Dashboard Refresh Interval',
                'group' => 'dashboard',
                'description' => 'Automatic dashboard refresh interval in seconds.',
                'type' => 'integer',
                'value' => 60,
                'default_value' => 60,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_realtime_widgets',
                'label' => 'Enable Realtime Widgets',
                'group' => 'dashboard',
                'description' => 'Controls whether realtime dashboard widgets are enabled.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_activity_feed',
                'label' => 'Enable Activity Feed Widget',
                'group' => 'dashboard',
                'description' => 'Controls visibility of the activity feed widget.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_system_status',
                'label' => 'Enable System Status Widget',
                'group' => 'dashboard',
                'description' => 'Controls visibility of backend/frontend status indicators.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_quick_actions',
                'label' => 'Enable Quick Actions',
                'group' => 'dashboard',
                'description' => 'Controls visibility of dashboard quick action shortcuts.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_global_search',
                'label' => 'Enable Global Search',
                'group' => 'dashboard',
                'description' => 'Controls visibility of the global search bar in top navigation.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.enable_command_palette',
                'label' => 'Enable Command Palette',
                'group' => 'dashboard',
                'description' => 'Controls keyboard-driven command palette functionality.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.sidebar.default_collapsed',
                'label' => 'Sidebar Collapsed By Default',
                'group' => 'dashboard',
                'description' => 'Controls initial sidebar state for new sessions.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'dashboard.show_environment_badge',
                'label' => 'Show Environment Badge',
                'group' => 'dashboard',
                'description' => 'Displays environment badge in admin shell.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
        ]);
    }
}
