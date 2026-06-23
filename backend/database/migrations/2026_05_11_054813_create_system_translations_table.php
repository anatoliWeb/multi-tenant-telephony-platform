<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates dynamic translations table.
 *
 * WHY:
 * This table stores runtime/business translations that cannot live
 * safely in static language files.
 *
 * Examples:
 * - role labels
 * - permission labels
 * - settings labels
 * - CMS content
 * - dynamic menu items
 * - category names
 * - tenant-specific translations
 *
 * IMPORTANT:
 * Static UI/system translations should still remain in language files.
 *
 * This table is intended ONLY for dynamic/business translations.
 *
 * Architecture goals:
 * - scalable localization
 * - runtime translation editing
 * - admin-managed translations
 * - future AI translation support
 * - translation caching
 * - tenant-ready localization
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_translations', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Translation Locale
            |--------------------------------------------------------------------------
            |
            | Examples:
            | - en
            | - uk
            | - de
            */

            $table->string('locale', 10)
                ->comment('Translation locale code.');

            /*
            |--------------------------------------------------------------------------
            | Translation Namespace / Group
            |--------------------------------------------------------------------------
            |
            | Examples:
            | - roles
            | - permissions
            | - settings
            | - dashboard
            | - cms
            */

            $table->string('group', 100)
                ->default('general')
                ->comment('Logical translation namespace/group.');

            /*
            |--------------------------------------------------------------------------
            | Translation Key
            |--------------------------------------------------------------------------
            |
            | Examples:
            | - role.admin
            | - permission.users.view
            | - settings.theme.dark
            */

            $table->string('key', 191)
                ->comment('Machine-readable translation key.');

            /*
            |--------------------------------------------------------------------------
            | Translation Value
            |--------------------------------------------------------------------------
            */

            $table->longText('value')
                ->comment('Translated human-readable value.');

            /*
            |--------------------------------------------------------------------------
            | Translation Source
            |--------------------------------------------------------------------------
            |
            | Helps separate:
            | - frontend translations
            | - backend translations
            | - database entity translations
            | - settings translations
            */

            $table->string('source', 50)
                ->default('database')
                ->comment('Translation source identifier.');

            /*
            |--------------------------------------------------------------------------
            | Translation Description
            |--------------------------------------------------------------------------
            */

            $table->text('description')
                ->nullable()
                ->comment('Optional internal translation description.');

            /*
            |--------------------------------------------------------------------------
            | Frontend / Backend Visibility
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_frontend')
                ->default(true)
                ->comment('Indicates frontend visibility.');

            $table->boolean('is_backend')
                ->default(true)
                ->comment('Indicates backend visibility.');

            /*
            |--------------------------------------------------------------------------
            | Runtime Flags
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_system')
                ->default(false)
                ->comment('Marks protected system translation.');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Controls whether translation is active.');

            $table->boolean('is_auto_generated')
                ->default(false)
                ->comment('Marks translations automatically created when missing key is detected.');

            $table->boolean('is_translated')
                ->default(true)
                ->comment('Indicates whether translation value was manually/properly translated.');

            /*
            |--------------------------------------------------------------------------
            | Audit Ownership
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who created translation.');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who last updated translation.');

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
                ['locale'],
                'system_translations_locale_idx'
            );

            $table->index(
                ['group'],
                'system_translations_group_idx'
            );

            $table->index(
                ['key'],
                'system_translations_key_idx'
            );

            $table->index(
                ['source'],
                'system_translations_source_idx'
            );

            $table->index(
                ['is_active'],
                'system_translations_active_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | Translation Uniqueness
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate translations for same:
            | locale + group + key
            */

            $table->unique(
                ['locale', 'group', 'key'],
                'system_translations_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_translations');
    }
};
