<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates core authentication and session infrastructure tables.
 *
 * WHY:
 * These tables form the foundation of the authentication system:
 * - users
 * - password recovery
 * - session persistence
 *
 * The architecture prepares the platform for:
 * - SPA authentication
 * - RBAC authorization
 * - API authentication
 * - audit logging
 * - multi-device sessions
 * - future profile expansion
 *
 * IMPORTANT:
 * Authentication-related tables are security-critical and must remain
 * highly indexed and normalized.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Users Table
        |--------------------------------------------------------------------------
        |
        | Core authenticatable platform users.
        */

        Schema::create('users', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Identity Information
            |--------------------------------------------------------------------------
            */

            $table->string('name', 160)
                ->comment('Human-readable display name.');

            $table->string('email', 190)
                ->unique()
                ->comment('Unique authentication email.');

            /*
            |--------------------------------------------------------------------------
            | Email Verification
            |--------------------------------------------------------------------------
            */

            $table->timestamp('email_verified_at')
                ->nullable()
                ->comment('Timestamp when email was verified.');

            /*
            |--------------------------------------------------------------------------
            | Authentication Secret
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Passwords must always be securely hashed.
            */

            $table->string('password')
                ->comment('Securely hashed user password.');

            /*
            |--------------------------------------------------------------------------
            | Remember Me Token
            |--------------------------------------------------------------------------
            */

            $table->rememberToken();

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
                ['email_verified_at'],
                'users_email_verified_at_idx'
            );

            $table->index(
                ['created_at'],
                'users_created_at_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Password Reset Tokens Table
        |--------------------------------------------------------------------------
        |
        | Stores temporary password reset tokens.
        */

        Schema::create('password_reset_tokens', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Email Identifier
            |--------------------------------------------------------------------------
            */

            $table->string('email', 190)
                ->primary()
                ->comment('User email requesting password reset.');

            /*
            |--------------------------------------------------------------------------
            | Reset Token
            |--------------------------------------------------------------------------
            */

            $table->string('token')
                ->comment('Password reset token.');

            /*
            |--------------------------------------------------------------------------
            | Token Creation Timestamp
            |--------------------------------------------------------------------------
            */

            $table->timestamp('created_at')
                ->nullable()
                ->comment('Timestamp when reset token was created.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['created_at'],
                'password_reset_tokens_created_at_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Sessions Table
        |--------------------------------------------------------------------------
        |
        | Database-backed session storage.
        |
        | Used for:
        | - SPA authentication
        | - session persistence
        | - multi-device tracking
        | - session invalidation
        */

        Schema::create('sessions', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Session Identifier
            |--------------------------------------------------------------------------
            */

            $table->string('id')
                ->primary()
                ->comment('Unique session identifier.');

            /*
            |--------------------------------------------------------------------------
            | Session User Reference
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->comment('Authenticated user attached to session.');

            /*
            |--------------------------------------------------------------------------
            | Client Network Information
            |--------------------------------------------------------------------------
            */

            $table->string('ip_address', 45)
                ->nullable()
                ->comment('Client IP address.');

            /*
            |--------------------------------------------------------------------------
            | Client Device Information
            |--------------------------------------------------------------------------
            */

            $table->text('user_agent')
                ->nullable()
                ->comment('Browser and device information.');

            /*
            |--------------------------------------------------------------------------
            | Serialized Session Payload
            |--------------------------------------------------------------------------
            */

            $table->longText('payload')
                ->comment('Serialized session payload.');

            /*
            |--------------------------------------------------------------------------
            | Activity Tracking
            |--------------------------------------------------------------------------
            */

            $table->integer('last_activity')
                ->index()
                ->comment('Unix timestamp of last session activity.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['user_id', 'last_activity'],
                'sessions_user_last_activity_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');

        Schema::dropIfExists('password_reset_tokens');

        Schema::dropIfExists('sessions');
    }
};
