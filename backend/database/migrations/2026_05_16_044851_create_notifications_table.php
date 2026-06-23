<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create notifications table.
     *
     * WHY:
     * This table stores Laravel database notifications for users or other notifiable models.
     * It is used for system notifications, unread counters, read state, and notification UI.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')
                ->primary()
                ->comment('Primary notification UUID used by Laravel database notifications.');

            $table->string('type')
                ->comment('Notification class/type name.');

            /**
             * Polymorphic notifiable relation.
             *
             * Laravel uses notifiable_type and notifiable_id to support notifications
             * for users or any other notifiable model.
             */
            $table->morphs('notifiable');

            $table->text('data')
                ->comment('Notification payload stored as JSON text by Laravel database notifications.');

            $table->timestamp('read_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when the notification was read. Null means unread.');

            $table->timestamps();

            /**
             * Common indexes for notification lists and unread counters.
             */
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_notifiable_created_idx');
        });

        DB::statement("ALTER TABLE notifications COMMENT = 'Stores database notifications for users and other notifiable models.'");
    }

    /**
     * Drop notifications table.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};