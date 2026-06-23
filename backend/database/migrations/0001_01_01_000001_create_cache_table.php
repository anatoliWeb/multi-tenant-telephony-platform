<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates database cache infrastructure tables.
 *
 * WHY:
 * These tables provide database-backed caching support for Laravel.
 *
 * The cache system is used for:
 * - settings caching
 * - API response caching
 * - permission caching
 * - queue coordination
 * - session-like temporary storage
 * - distributed locking
 *
 * IMPORTANT:
 * Database cache is slower than Redis/Memcached,
 * but provides:
 * - local development compatibility
 * - fallback cache storage
 * - infrastructure simplicity
 *
 * In production, Redis is typically preferred.
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
        | Cache Storage Table
        |--------------------------------------------------------------------------
        |
        | Stores serialized cached values.
        */

        Schema::create('cache', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Cache Key
            |--------------------------------------------------------------------------
            |
            | Unique cache identifier.
            */

            $table->string('key')
                ->primary()
                ->comment('Unique cache entry key.');

            /*
            |--------------------------------------------------------------------------
            | Serialized Cache Value
            |--------------------------------------------------------------------------
            */

            $table->mediumText('value')
                ->comment('Serialized cached value.');

            /*
            |--------------------------------------------------------------------------
            | Expiration Timestamp
            |--------------------------------------------------------------------------
            |
            | Unix timestamp used for cache expiration.
            */

            $table->bigInteger('expiration')
                ->index()
                ->comment('Unix expiration timestamp.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['expiration'],
                'cache_expiration_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Distributed Cache Locks Table
        |--------------------------------------------------------------------------
        |
        | Provides atomic locking support.
        |
        | Used for:
        | - queue coordination
        | - preventing race conditions
        | - scheduled task locking
        | - distributed synchronization
        */

        Schema::create('cache_locks', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Lock Key
            |--------------------------------------------------------------------------
            */

            $table->string('key')
                ->primary()
                ->comment('Unique distributed lock key.');

            /*
            |--------------------------------------------------------------------------
            | Lock Owner
            |--------------------------------------------------------------------------
            |
            | Usually worker/process identifier.
            */

            $table->string('owner')
                ->comment('Current lock owner identifier.');

            /*
            |--------------------------------------------------------------------------
            | Expiration Timestamp
            |--------------------------------------------------------------------------
            */

            $table->bigInteger('expiration')
                ->index()
                ->comment('Unix expiration timestamp.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['expiration'],
                'cache_locks_expiration_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');

        Schema::dropIfExists('cache_locks');
    }
};
