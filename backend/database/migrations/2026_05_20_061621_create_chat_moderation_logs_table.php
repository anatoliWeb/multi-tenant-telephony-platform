<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create chat moderation logs table.
     *
     * This table stores admin/moderation actions:
     * blocking participants, deleting messages, closing conversations,
     * importing old direct chat history into a new group chat, etc.
     */
    public function up(): void
    {
        Schema::create('chat_moderation_logs', function (Blueprint $table) {
            $table->id()
                ->comment('Primary chat moderation log ID.');

            /**
             * Optional chat references.
             */
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete()
                ->comment('Conversation affected by this moderation action.');

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete()
                ->comment('Message affected by this moderation action.');

            /**
             * Actor and target.
             */
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User/admin who performed the moderation action.');

            $table->foreignId('target_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User affected by the moderation action, if applicable.');

            /**
             * Action details.
             */
            $table->string('action', 128)
                ->index()
                ->comment('Moderation action name, for example participant_blocked, message_deleted, history_imported.');

            $table->text('reason')
                ->nullable()
                ->comment('Optional human-readable reason for the moderation action.');

            /**
             * Old/new values are useful for audit screens.
             */
            $table->json('old_values')
                ->nullable()
                ->comment('Optional previous state before moderation action.');

            $table->json('new_values')
                ->nullable()
                ->comment('Optional new state after moderation action.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe metadata related to the moderation action.');

            $table->timestamps();

            /**
             * Common lookup indexes for admin monitoring.
             */
            $table->index(['conversation_id', 'created_at'], 'chat_moderation_logs_conversation_created_idx');
            $table->index(['message_id', 'created_at'], 'chat_moderation_logs_message_created_idx');
            $table->index(['actor_id', 'created_at'], 'chat_moderation_logs_actor_created_idx');
            $table->index(['target_user_id', 'created_at'], 'chat_moderation_logs_target_created_idx');
            $table->index(['action', 'created_at'], 'chat_moderation_logs_action_created_idx');
        });

        DB::statement("ALTER TABLE chat_moderation_logs COMMENT = 'Stores chat moderation and audit actions for conversations, messages, participants and history imports.'");
    }

    /**
     * Drop chat moderation logs table.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_logs');
    }
};