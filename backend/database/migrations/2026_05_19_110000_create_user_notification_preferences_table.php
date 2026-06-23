<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create user notification preferences table.
     *
     * WHY:
     * This table stores per-user notification settings.
     * Preferences control whether system, realtime, email, or activity notifications are enabled.
     */
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id()
                ->comment('Primary notification preference ID.');

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('User who owns these notification preferences.');

            $table->json('preferences')
                ->comment('User notification preferences as JSON, for example system/realtime/email/activity settings.');

            $table->timestamps();

            /**
             * One preferences record per user.
             */
            $table->unique('user_id', 'user_notification_preferences_user_unique');
        });

        DB::statement("ALTER TABLE user_notification_preferences COMMENT = 'Stores per-user notification preference settings.'");
    }

    /**
     * Drop user notification preferences table.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};