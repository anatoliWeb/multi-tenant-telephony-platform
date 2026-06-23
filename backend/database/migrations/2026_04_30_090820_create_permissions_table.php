<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates permissions table.
 *
 * WHY:
 * Permissions represent the lowest-level authorization unit
 * in the RBAC (Role-Based Access Control) system.
 *
 * Examples:
 * - users.view
 * - users.create
 * - users.edit
 * - users.delete
 * - tokens.manage
 * - settings.update
 *
 * Permissions may be:
 * - assigned directly to users
 * - inherited through roles
 * - explicitly denied through override tables
 *
 * This architecture supports:
 * - scalable enterprise authorization
 * - fine-grained access control
 * - modular permission grouping
 * - dynamic permission inheritance
 * - future policy expansion
 *
 * IMPORTANT:
 * Permission names should remain machine-readable and stable.
 *
 * Recommended convention:
 * resource.action
 *
 * Example:
 * users.view
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Permission Identity
            |--------------------------------------------------------------------------
            |
            | Machine-readable permission key.
            |
            | Examples:
            | - users.view
            | - roles.manage
            | - settings.update
            */

            $table->string('name', 160)
                ->unique()
                ->comment('Unique machine-readable permission key.');

            /*
            |--------------------------------------------------------------------------
            | Human Description
            |--------------------------------------------------------------------------
            */

            $table->string('description')
                ->nullable()
                ->comment('Optional human-readable permission description.');

            /*
            |--------------------------------------------------------------------------
            | Audit Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['created_at'],
                'permissions_created_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
