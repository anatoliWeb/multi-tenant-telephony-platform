<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates role-permission assignment pivot table.
 *
 * WHY:
 * This table forms the core of the RBAC (Role-Based Access Control) system.
 *
 * Roles aggregate permissions into reusable access profiles.
 *
 * Examples:
 *
 * Role:
 * admin
 * → users.view
 * → users.edit
 * → roles.manage
 *
 * Role:
 * support
 * → tickets.view
 * → tickets.reply
 *
 * This architecture provides:
 * - scalable authorization management
 * - reusable permission groups
 * - simplified access administration
 * - enterprise RBAC workflows
 *
 * IMPORTANT:
 * Role permissions act as inherited/default permissions.
 *
 * Effective permission resolution typically becomes:
 *
 * denied permission
 *   ↓
 * direct user permission
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
        Schema::create('permission_role', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Permission Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Permission assigned to role.');

            /*
            |--------------------------------------------------------------------------
            | Role Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Role receiving permission assignment.');

            /*
            |--------------------------------------------------------------------------
            | Composite Primary Key
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate permission assignments for same role.
            */

            $table->primary(
                ['permission_id', 'role_id'],
                'permission_role_primary'
            );

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['role_id'],
                'permission_role_role_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
