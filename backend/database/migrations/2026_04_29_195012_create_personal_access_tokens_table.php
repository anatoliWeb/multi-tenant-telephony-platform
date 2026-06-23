<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates personal access tokens table.
 *
 * WHY:
 * Personal access tokens provide API authentication for:
 * - SPA sessions
 * - mobile applications
 * - external integrations
 * - automation scripts
 * - CLI tools
 * - third-party services
 *
 * This table is used by Laravel Sanctum token authentication.
 *
 * IMPORTANT:
 * Tokens should NEVER store raw unhashed values.
 *
 * The `token` column stores only hashed token values
 * for security reasons.
 *
 * The polymorphic `tokenable` relation allows tokens to be
 * attached to different authenticatable models in future.
 *
 * Examples:
 * - User
 * - Service Account
 * - API Client
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Token Owner (Polymorphic)
            |--------------------------------------------------------------------------
            |
            | Defines which authenticatable entity owns the token.
            |
            | Typically:
            | - App\Models\User
            */

            $table->morphs('tokenable');

            /*
            |--------------------------------------------------------------------------
            | Token Metadata
            |--------------------------------------------------------------------------
            */

            $table->text('name')
                ->comment('Human-readable token label.');

            /*
            |--------------------------------------------------------------------------
            | Token Hash
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Only hashed token values are stored.
            */

            $table->string('token', 64)
                ->unique()
                ->comment('Hashed token value.');

            /*
            |--------------------------------------------------------------------------
            | Token Abilities / Scopes
            |--------------------------------------------------------------------------
            |
            | JSON-encoded list of token capabilities.
            |
            | Examples:
            | - ["users.read"]
            | - ["*"]
            */

            $table->text('abilities')
                ->nullable()
                ->comment('Serialized token abilities/scopes.');

            /*
            |--------------------------------------------------------------------------
            | Usage Tracking
            |--------------------------------------------------------------------------
            */

            $table->timestamp('last_used_at')
                ->nullable()
                ->comment('Last successful token usage timestamp.');

            /*
            |--------------------------------------------------------------------------
            | Expiration
            |--------------------------------------------------------------------------
            */

            $table->timestamp('expires_at')
                ->nullable()
                ->index()
                ->comment('Optional token expiration timestamp.');

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
                ['tokenable_type', 'tokenable_id'],
                'personal_access_tokens_tokenable_idx'
            );

            $table->index(
                ['last_used_at'],
                'personal_access_tokens_last_used_idx'
            );

            $table->index(
                ['created_at'],
                'personal_access_tokens_created_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
