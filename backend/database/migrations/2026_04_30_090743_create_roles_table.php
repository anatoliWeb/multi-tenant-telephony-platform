<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates roles table.
 *
 * WHY:
 * Roles represent reusable permission groups within the RBAC
 * (Role-Based Access Control) system.
 *
 * Instead of assigning many permissions directly to users,
 * roles aggregate permissions into manageable access profiles.
 *
 * Examples:
 * - admin
 * - manager
 * - support
 * - moderator
 * - customer
 *
 * Roles provide:
 * - scalable authorization management
 * - simplified onboarding
 * - centralized access control
 * - reusable security policies
 * - easier permission auditing
 *
 * IMPORTANT:
 * Roles should remain business-oriented and human-readable.
 *
 * Permissions define capabilities.
 * Roles define responsibility groups.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Role Identity
            |--------------------------------------------------------------------------
            |
            | Human-readable role key.
            |
            | Examples:
            | - admin
            | - support
            | - manager
            */

            $table->string('name', 160)
                ->unique()
                ->comment('Unique machine-readable role name.');

            /*
            |--------------------------------------------------------------------------
            | Human Description
            |--------------------------------------------------------------------------
            */

            $table->string('description')
                ->nullable()
                ->comment('Optional human-readable role description.');

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
                'roles_created_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
