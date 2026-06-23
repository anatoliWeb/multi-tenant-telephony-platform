<?php

namespace Database\Seeders\settings;

/**
 * Seeds backend infrastructure and runtime defaults.
 *
 * WHY:
 * Backend settings centralize operational behavior for queues, cache,
 * API throttling, jobs, and internal platform services. This allows
 * runtime tuning without changing application code.
 */
class BackendSettingsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'backend.cache.default_ttl',
                'label' => 'Default Cache TTL',
                'group' => 'backend',
                'description' => 'Default cache lifetime in seconds used by backend services.',
                'type' => 'integer',
                'value' => 3600,
                'default_value' => 3600,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.cache.enable_tags',
                'label' => 'Enable Cache Tags',
                'group' => 'backend',
                'description' => 'Controls whether tagged cache invalidation is enabled.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.queue.default_connection',
                'label' => 'Default Queue Connection',
                'group' => 'queue',
                'description' => 'Primary queue driver used for async jobs.',
                'type' => 'string',
                'value' => 'redis',
                'default_value' => 'redis',
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.queue.default_retry_after',
                'label' => 'Queue Retry After',
                'group' => 'queue',
                'description' => 'Seconds before failed queue jobs are retried.',
                'type' => 'integer',
                'value' => 90,
                'default_value' => 90,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.queue.failed_job_retention_days',
                'label' => 'Failed Job Retention',
                'group' => 'queue',
                'description' => 'How many days failed jobs should be retained.',
                'type' => 'integer',
                'value' => 14,
                'default_value' => 14,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.api.rate_limit',
                'label' => 'API Rate Limit',
                'group' => 'api',
                'description' => 'Maximum API requests per minute for authenticated users.',
                'type' => 'integer',
                'value' => 120,
                'default_value' => 120,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.api.enable_request_logging',
                'label' => 'Enable API Request Logging',
                'group' => 'api',
                'description' => 'Controls whether API requests are written to activity logs.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.activity.retention_days',
                'label' => 'Activity Retention Days',
                'group' => 'activity',
                'description' => 'How long activity/audit logs should be retained.',
                'type' => 'integer',
                'value' => 90,
                'default_value' => 90,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.activity.enable_audit_log',
                'label' => 'Enable Audit Logging',
                'group' => 'activity',
                'description' => 'Controls whether audit activity is recorded.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.system.enable_debug_toolbar',
                'label' => 'Enable Debug Toolbar',
                'group' => 'system',
                'description' => 'Controls visibility of development debugging utilities.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.system.enable_horizon',
                'label' => 'Enable Queue Horizon',
                'group' => 'system',
                'description' => 'Controls whether Laravel Horizon integration is enabled.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'backend.system.default_log_channel',
                'label' => 'Default Log Channel',
                'group' => 'system',
                'description' => 'Primary logging channel used by the backend.',
                'type' => 'string',
                'value' => 'stack',
                'default_value' => 'stack',
                'is_frontend' => false,
                'is_backend' => true,
            ],
        ]);
    }
}
