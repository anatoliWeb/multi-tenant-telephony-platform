<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates direct user permission assignment pivot table.
 *
 * WHY:
 * RBAC systems typically provide permissions through roles.
 *
 * However, enterprise authorization systems often additionally require:
 * direct per-user permission assignments.
 *
 * Examples:
 *
 * User:
 * John
 *
 * Role:
 * manager
 *
 * Additional direct permission:
 * users.export
 *
 * This table allows:
 * - granular permission overrides
 * - temporary elevated access
 * - exception-based access control
 * - feature-specific user capabilities
 *
 * IMPORTANT:
 * Direct permissions usually have higher priority than role permissions,
 * but lower priority than explicit denied permissions.
 *
 * Effective resolution example:
 *
 * denied permission
 *   ↓
 * direct permission
 *   ↓
 * role permission
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | User Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('User receiving direct permission assignment.');

            /*
            |--------------------------------------------------------------------------
            | Permission Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Permission directly assigned to user.');

            /*
            |--------------------------------------------------------------------------
            | Composite Primary Key
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate permission assignments for same user.
            */

            $table->primary(
                ['user_id', 'permission_id'],
                'permission_user_primary'
            );

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['permission_id'],
                'permission_user_permission_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
