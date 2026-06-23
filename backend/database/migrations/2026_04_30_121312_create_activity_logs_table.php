<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates centralized activity and audit logs table.
 *
 * WHY:
 * Activity logs provide a unified audit trail for:
 * - authentication events
 * - RBAC changes
 * - CRUD operations
 * - security actions
 * - system operations
 * - API events
 *
 * This table acts as the foundation for:
 * - audit history
 * - realtime activity streams
 * - compliance tracking
 * - user accountability
 * - admin debugging
 * - future analytics
 *
 * IMPORTANT:
 * Activity logs are intentionally semi-structured.
 *
 * The `meta` JSON field allows attaching contextual runtime data
 * without requiring constant schema changes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Actor Information
            |--------------------------------------------------------------------------
            |
            | User responsible for triggering activity.
            |
            | Nullable because:
            | - guest actions
            | - system actions
            | - scheduled jobs
            | - queue workers
            | may generate activity entries.
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->comment('User responsible for activity event.');

            /*
            |--------------------------------------------------------------------------
            | Activity Classification
            |--------------------------------------------------------------------------
            |
            | Machine-readable action key.
            |
            | Examples:
            | - user.created
            | - token.deleted
            | - role.updated
            | - auth.login
            | - settings.updated
            */

            $table->string('action', 160)
                ->comment('Machine-readable activity action key.');

            /*
            |--------------------------------------------------------------------------
            | Human Description
            |--------------------------------------------------------------------------
            */

            $table->text('description')
                ->nullable()
                ->comment('Optional human-readable activity description.');

            /*
            |--------------------------------------------------------------------------
            | Context Metadata
            |--------------------------------------------------------------------------
            |
            | Flexible structured payload.
            |
            | Examples:
            | - changed fields
            | - old/new values
            | - IP address
            | - request context
            | - browser/device info
            | - related entity identifiers
            */

            $table->json('meta')
                ->nullable()
                ->comment('Structured contextual activity metadata.');

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
                ['action'],
                'activity_logs_action_idx'
            );

            $table->index(
                ['user_id'],
                'activity_logs_user_idx'
            );

            $table->index(
                ['created_at'],
                'activity_logs_created_at_idx'
            );

            $table->index(
                ['user_id', 'created_at'],
                'activity_logs_user_created_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
