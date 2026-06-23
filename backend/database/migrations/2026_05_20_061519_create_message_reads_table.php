<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create message reads table.
     *
     * WHY:
     * This table stores aggregated per-user read receipts.
     * It answers the question: "Has this user read this message?"
     *
     * Device-level read state is stored separately in message_device_reads.
     */
    public function up(): void
    {
        Schema::create('message_reads', function (Blueprint $table) {
            $table->id()
                ->comment('Primary message read ID.');

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Message that was read by the user.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation where the message belongs.');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('User who read the message.');

            $table->timestamp('read_at')
                ->index()
                ->comment('Timestamp when the user first read the message on any device.');

            $table->string('read_source', 32)
                ->default('user')
                ->comment('Read source: user, device, admin, system.');

            $table->timestamps();

            /**
             * One aggregated read receipt per user/message.
             */
            $table->unique(['message_id', 'user_id'], 'message_reads_unique_user_message');

            /**
             * Fast lookups for unread/read state.
             */
            $table->index(['conversation_id', 'user_id'], 'message_reads_conversation_user_idx');
            $table->index(['conversation_id', 'read_at'], 'message_reads_conversation_read_at_idx');
            $table->index(['user_id', 'read_at'], 'message_reads_user_read_at_idx');
            $table->index(['user_id', 'read_source'], 'message_reads_user_source_idx');
        });

        DB::statement("ALTER TABLE message_reads COMMENT = 'Stores aggregated per-user read receipts for chat messages.'");
    }

    /**
     * Drop message reads table.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};