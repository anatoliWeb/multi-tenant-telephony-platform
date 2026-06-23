<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add message foreign keys to conversations.
     *
     * These references are added separately because conversations table
     * is created before messages table.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('history_import_from_message_id', 'conversations_history_import_message_fk')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();

            $table->foreign('last_message_id', 'conversations_last_message_fk')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    /**
     * Remove message foreign keys from conversations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign('conversations_history_import_message_fk');
            $table->dropForeign('conversations_last_message_fk');
        });
    }
};