<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates explicit user-level denied permissions table.
 *
 * WHY:
 * Laravel Permission package supports:
 * - role permissions
 * - direct permissions
 *
 * But enterprise RBAC systems often additionally require:
 * explicit permission denial overrides.
 *
 * Example:
 *
 * Role:
 * admin
 * → grants users.delete
 *
 * Specific user:
 * John
 * → should NOT have users.delete
 *
 * This table introduces negative permission overrides.
 *
 * Resolution example:
 *
 * denied permission
 *   ↓
 * direct permission
 *   ↓
 * role permission
 *
 * IMPORTANT:
 * Denied permissions must always have highest priority during
 * effective permission resolution.
 *
 * This architecture prepares the platform for:
 * - enterprise RBAC
 * - exception-based security
 * - temporary access restrictions
 * - compliance workflows
 * - high-granularity access control
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_denied_permissions', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Permission Denial Scope
            |--------------------------------------------------------------------------
            |
            | Defines:
            | which user
            | is explicitly denied
            | which permission.
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('User receiving explicit permission denial.');

            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Permission explicitly denied to user.');

            /*
            |--------------------------------------------------------------------------
            | Audit Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Uniqueness Constraint
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate denied permission assignments
            | for the same user/permission combination.
            */

            $table->unique(
                ['user_id', 'permission_id'],
                'user_denied_permissions_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_denied_permissions');
    }
};
