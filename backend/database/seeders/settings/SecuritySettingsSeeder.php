<?php

namespace Database\Seeders\settings;

/**
 * Seeds security and authentication defaults.
 *
 * WHY:
 * Security settings centralize authentication rules, password policies,
 * session behavior, access protection, and security-related platform controls.
 *
 * This architecture prepares the platform for:
 * - enterprise authentication policies
 * - dynamic security hardening
 * - future MFA/2FA support
 * - tenant-specific security profiles
 */
class SecuritySettingsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'security.password.min_length',
                'label' => 'Minimum Password Length',
                'group' => 'security',
                'description' => 'Minimum allowed password length for user accounts.',
                'type' => 'integer',
                'value' => 8,
                'default_value' => 8,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.password.require_uppercase',
                'label' => 'Require Uppercase Characters',
                'group' => 'security',
                'description' => 'Controls whether passwords must contain uppercase letters.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.password.require_numbers',
                'label' => 'Require Numbers',
                'group' => 'security',
                'description' => 'Controls whether passwords must contain numeric characters.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.password.require_special',
                'label' => 'Require Special Characters',
                'group' => 'security',
                'description' => 'Controls whether passwords must contain special characters.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.password.expiration_days',
                'label' => 'Password Expiration Days',
                'group' => 'security',
                'description' => 'Number of days before passwords expire.',
                'type' => 'integer',
                'value' => 0,
                'default_value' => 0,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.auth.max_login_attempts',
                'label' => 'Maximum Login Attempts',
                'group' => 'security',
                'description' => 'Maximum failed login attempts before temporary lockout.',
                'type' => 'integer',
                'value' => 5,
                'default_value' => 5,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.auth.lockout_minutes',
                'label' => 'Login Lockout Duration',
                'group' => 'security',
                'description' => 'Temporary lockout duration after too many failed login attempts.',
                'type' => 'integer',
                'value' => 15,
                'default_value' => 15,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.session.timeout_minutes',
                'label' => 'Session Timeout',
                'group' => 'security',
                'description' => 'Session inactivity timeout in minutes.',
                'type' => 'integer',
                'value' => 120,
                'default_value' => 120,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.session.single_device_only',
                'label' => 'Single Device Sessions',
                'group' => 'security',
                'description' => 'Restricts users to a single active authenticated session.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.2fa.enabled',
                'label' => 'Enable Two-Factor Authentication',
                'group' => 'security',
                'description' => 'Globally enables two-factor authentication support.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => true,
                'is_backend' => true,
            ],

            [
                'key' => 'security.2fa.required_for_admins',
                'label' => 'Require 2FA For Admins',
                'group' => 'security',
                'description' => 'Forces administrator accounts to use two-factor authentication.',
                'type' => 'boolean',
                'value' => false,
                'default_value' => false,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.audit.enable_ip_logging',
                'label' => 'Enable IP Logging',
                'group' => 'security',
                'description' => 'Stores client IP addresses in security and activity logs.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.audit.enable_user_agent_logging',
                'label' => 'Enable User-Agent Logging',
                'group' => 'security',
                'description' => 'Stores browser and device information in audit logs.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],

            [
                'key' => 'security.api.require_https',
                'label' => 'Require HTTPS',
                'group' => 'security',
                'description' => 'Controls whether API requests must use HTTPS in production.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => false,
                'is_backend' => true,
            ],
        ]);
    }
}
