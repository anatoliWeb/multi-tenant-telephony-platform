<?php

namespace Database\Seeders\settings;

/**
 * Seeds realtime and websocket infrastructure defaults.
 *
 * WHY:
 * Realtime settings centralize websocket behavior, refresh intervals,
 * broadcast providers, and live synchronization features.
 *
 * This architecture prepares the platform for:
 * - websocket broadcasting
 * - live activity streams
 * - online presence
 * - realtime notifications
 * - scalable frontend synchronization
 */
class RealtimeSettingsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'realtime.enabled',
                'label' => 'Realtime Enabled',
                'group' => 'realtime',
                'description' => 'Globally enables realtime websocket functionality.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.provider',
                'label' => 'Realtime Provider',
                'group' => 'realtime',
                'description' => 'Primary realtime broadcasting provider used by the platform.',
                'type' => 'string',
                'value' => 'reverb',
                'default_value' => 'reverb',
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.websocket_path',
                'label' => 'Websocket Path',
                'group' => 'realtime',
                'description' => 'Default websocket endpoint path used by frontend clients.',
                'type' => 'string',
                'value' => '/app',
                'default_value' => '/app',
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.refresh_interval',
                'label' => 'Realtime Refresh Interval',
                'group' => 'realtime',
                'description' => 'Fallback polling refresh interval in seconds when websocket transport is unavailable.',
                'type' => 'integer',
                'value' => 30,
                'default_value' => 30,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.enable_presence',
                'label' => 'Enable Presence Channels',
                'group' => 'realtime',
                'description' => 'Controls whether online presence tracking is enabled.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.enable_notifications',
                'label' => 'Enable Realtime Notifications',
                'group' => 'realtime',
                'description' => 'Controls whether notifications are pushed in realtime.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.enable_activity_stream',
                'label' => 'Enable Activity Stream',
                'group' => 'realtime',
                'description' => 'Controls realtime activity feed synchronization.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.enable_live_dashboard',
                'label' => 'Enable Live Dashboard',
                'group' => 'realtime',
                'description' => 'Enables realtime dashboard widgets and live statistics.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'realtime.enable_user_presence_badges',
                'label' => 'Enable User Presence Badges',
                'group' => 'realtime',
                'description' => 'Displays online/offline indicators across admin UI.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],

            [
                'key' => 'realtime.max_connections_per_user',
                'label' => 'Max Connections Per User',
                'group' => 'realtime',
                'description' => 'Maximum simultaneous realtime connections allowed per user.',
                'type' => 'integer',
                'value' => 5,
                'default_value' => 5,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.connection_timeout',
                'label' => 'Realtime Connection Timeout',
                'group' => 'realtime',
                'description' => 'Connection timeout in seconds before websocket reconnect attempts begin.',
                'type' => 'integer',
                'value' => 15,
                'default_value' => 15,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'realtime.enable_debug_logging',
                'label' => 'Enable Realtime Debug Logging',
                'group' => 'realtime',
                'description' => 'Controls websocket and realtime debug logging output.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => false,
                'is_backend' => true,
            ],
        ]);
    }
}
