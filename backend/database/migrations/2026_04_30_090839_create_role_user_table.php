<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates user-role assignment pivot table.
 *
 * WHY:
 * This table connects users to RBAC roles.
 *
 * Roles act as reusable permission groups that simplify authorization
 * management across the platform.
 *
 * Examples:
 *
 * User:
 * John
 *
 * Roles:
 * - admin
 * - support
 *
 * This architecture provides:
 * - scalable access management
 * - reusable authorization profiles
 * - simplified onboarding/offboarding
 * - centralized permission inheritance
 *
 * IMPORTANT:
 * Users may have multiple roles simultaneously.
 *
 * Effective permission resolution typically becomes:
 *
 * denied permission
 *   ↓
 * direct user permission
 *   ↓
 * inherited role permissions
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | User Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('User assigned to role.');

            /*
            |--------------------------------------------------------------------------
            | Role Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Role assigned to user.');

            /*
            |--------------------------------------------------------------------------
            | Composite Primary Key
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate role assignments for same user.
            */

            $table->primary(
                ['user_id', 'role_id'],
                'role_user_primary'
            );

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['role_id'],
                'role_user_role_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
