<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add message foreign keys to conversation participants.
     *
     * These fields control participant-level history visibility and read state.
     * They are added separately because conversation_participants table
     * is created before messages table.
     */
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->foreign('history_visible_from_message_id', 'participants_history_from_message_fk')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();

            $table->foreign('history_visible_until_message_id', 'participants_history_until_message_fk')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();

            $table->foreign('last_read_message_id', 'participants_last_read_message_fk')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    /**
     * Remove message foreign keys from conversation participants.
     */
    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropForeign('participants_history_from_message_fk');
            $table->dropForeign('participants_history_until_message_fk');
            $table->dropForeign('participants_last_read_message_fk');
        });
    }
};