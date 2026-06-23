<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates hierarchical dynamic system settings table.
 *
 * WHY:
 * This table acts as a centralized runtime configuration engine for the platform.
 *
 * The architecture supports:
 * - global defaults
 * - role-based overrides
 * - permission-based overrides
 * - user-specific overrides
 * - frontend/backend separation
 * - feature flags
 * - runtime configuration changes
 * - future tenant-aware configuration
 *
 * Resolution priority example:
 *
 * user override
 *   ↓
 * permission override
 *   ↓
 * role override
 *   ↓
 * global default
 *
 * IMPORTANT:
 * The table is intentionally flexible and schema-light because enterprise
 * configuration systems evolve continuously over time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Scope Resolution Layer
            |--------------------------------------------------------------------------
            |
            | These columns define where the setting applies.
            |
            | NULL values mean broader/global scope.
            |
            | Examples:
            |
            | NULL/NULL/NULL
            | → global default
            |
            | role_id=1
            | → applies to all users with role
            |
            | permission_id=5
            | → applies to users with permission
            |
            | user_id=10
            | → highest-priority user override
            */

            $table->foreignId('scope_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Optional user-specific override scope.');

            $table->foreignId('scope_role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete()
                ->comment('Optional role-based override scope.');

            $table->foreignId('scope_permission_id')
                ->nullable()
                ->constrained('permissions')
                ->nullOnDelete()
                ->comment('Optional permission-based override scope.');

            /*
            |--------------------------------------------------------------------------
            | Setting Identity
            |--------------------------------------------------------------------------
            */

            $table->string('key', 160)
                ->comment('Unique machine-readable setting key.');

            $table->string('label', 160)
                ->comment('Human-readable setting name displayed in admin UI.');

            $table->string('group', 100)
                ->default('general')
                ->comment('Logical settings category/group.');

            $table->text('description')
                ->nullable()
                ->comment('Detailed explanation of setting purpose.');

            /*
            |--------------------------------------------------------------------------
            | Typed Configuration Values
            |--------------------------------------------------------------------------
            |
            | Values are stored serialized in text format because:
            | - JSON
            | - arrays
            | - booleans
            | - integers
            | - enums
            | may all exist in same table.
            */

            $table->string('type', 50)
                ->default('string')
                ->comment('Declared setting value type.');

            $table->text('value')
                ->nullable()
                ->comment('Resolved runtime value for this scope.');

            $table->text('default_value')
                ->nullable()
                ->comment('Fallback default value.');

            /*
            |--------------------------------------------------------------------------
            | Frontend / Backend Separation
            |--------------------------------------------------------------------------
            |
            | Allows separating:
            | - SPA-only settings
            | - backend-only settings
            | - shared platform settings
            */

            $table->boolean('is_frontend')
                ->default(true)
                ->comment('Indicates whether frontend may consume this setting.');

            $table->boolean('is_backend')
                ->default(true)
                ->comment('Indicates whether backend services may consume this setting.');

            /*
            |--------------------------------------------------------------------------
            | Public Exposure & Encryption Preparation
            |--------------------------------------------------------------------------
            |
            | is_public:
            | Allows marking settings as safe for public frontend bootstrap.
            | This does not expose the setting automatically.
            | Actual exposure rules will be handled later by settings services.
            |
            | is_encrypted:
            | Reserves support for encrypted-at-rest values.
            | Actual encryption/decryption logic will be implemented later.
            */

            $table->boolean('is_public')
                ->default(false)
                ->comment('Safe for public frontend bootstrap if needed.');

            $table->boolean('is_encrypted')
                ->default(false)
                ->comment('Reserved for encrypted-at-rest setting values.');

            /*
            |--------------------------------------------------------------------------
            | Resolution & Runtime Flags
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('priority')
                ->default(100)
                ->comment('Explicit priority override for conflict resolution.');

            $table->string('inheritance_source', 50)
                ->nullable()
                ->comment('Resolved inheritance source identifier for debugging and effective value previews.');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Controls whether setting is active.');

            $table->boolean('is_system')
                ->default(false)
                ->comment('Marks protected core/system configuration.');

            /*
            |--------------------------------------------------------------------------
            | Audit Ownership
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who originally created setting.');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who last updated setting.');

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['key', 'is_active'],
                'settings_key_active_idx'
            );

            $table->index(
                ['group', 'is_active'],
                'settings_group_active_idx'
            );

            $table->index(
                ['is_frontend', 'is_active'],
                'settings_frontend_active_idx'
            );

            $table->index(
                ['is_backend', 'is_active'],
                'settings_backend_active_idx'
            );

            $table->index(
                ['scope_user_id', 'scope_role_id', 'scope_permission_id'],
                'settings_scope_idx'
            );

            $table->index(
                ['key', 'priority', 'is_active'],
                'settings_resolution_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | Scope Uniqueness
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate setting definitions for same scope combination.
            */

            $table->unique(
                ['key', 'scope_user_id', 'scope_role_id', 'scope_permission_id'],
                'settings_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
