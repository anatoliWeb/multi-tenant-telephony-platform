<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create conversation participants table.
     *
     * This table stores user participation, role, permissions,
     * access restrictions, read state, and history visibility rules.
     */
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id()
                ->comment('Primary participant row ID.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation this participant belongs to.');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('User participating in the conversation.');

            /**
             * Role and lifecycle state.
             */
            $table->string('role', 32)
                ->default('member')
                ->index()
                ->comment('Participant role: owner, admin, member, viewer, support.');

            $table->string('status', 32)
                ->default('active')
                ->index()
                ->comment('Participant status: active, invited, left, removed, blocked.');

            /**
             * Access state controls what the participant can see or do.
             */
            $table->string('access_state', 32)
                ->default('full')
                ->index()
                ->comment('Access state: full, read_only, hidden, blocked.');

            $table->string('block_display_mode', 64)
                ->nullable()
                ->comment('Blocked display mode: hide_chat, show_notice, show_read_only_history.');

            /**
             * Fine-grained participant capabilities.
             */
            $table->boolean('can_invite')
                ->default(false)
                ->comment('Whether participant can invite new users.');

            $table->boolean('can_remove')
                ->default(false)
                ->comment('Whether participant can remove other users.');

            $table->boolean('can_send')
                ->default(true)
                ->comment('Whether participant can send messages.');

            $table->boolean('can_attach')
                ->default(true)
                ->comment('Whether participant can attach files.');

            $table->boolean('can_manage')
                ->default(false)
                ->comment('Whether participant can manage conversation settings.');

            $table->boolean('can_moderate')
                ->default(false)
                ->comment('Whether participant can moderate messages or participants.');

            /**
             * Blocking information.
             */
            $table->text('blocked_reason')
                ->nullable()
                ->comment('Reason why participant was blocked or restricted.');

            $table->foreignId('blocked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User/admin who blocked or restricted this participant.');

            $table->timestamp('blocked_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when participant was blocked or restricted.');

            /**
             * History visibility rules.
             *
             * Allows scenarios such as:
             * - participant sees history from join date
             * - participant sees history from selected message
             * - participant sees history from selected date
             * - participant sees full history
             * - participant sees history only until block date/message
             *
             * Message FK constraints are added later after messages table exists.
             */
            $table->string('history_visibility_mode', 32)
                ->default('from_join')
                ->comment('History visibility mode: from_join, from_date, from_message, full.');

            $table->unsignedBigInteger('history_visible_from_message_id')
                ->nullable()
                ->index()
                ->comment('First visible message ID for this participant. FK is added later.');

            $table->timestamp('history_visible_from_at')
                ->nullable()
                ->index()
                ->comment('First visible timestamp for this participant.');

            $table->unsignedBigInteger('history_visible_until_message_id')
                ->nullable()
                ->index()
                ->comment('Last visible message ID for this participant. FK is added later.');

            $table->timestamp('history_visible_until_at')
                ->nullable()
                ->index()
                ->comment('Last visible timestamp for this participant.');

            /**
             * Participation timestamps.
             */
            $table->timestamp('joined_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when participant joined the conversation.');

            $table->timestamp('left_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when participant left the conversation.');

            $table->timestamp('removed_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when participant was removed from the conversation.');

            /**
             * Read state cache.
             *
             * last_read_message_id FK is added later after messages table exists.
             */
            $table->unsignedBigInteger('last_read_message_id')
                ->nullable()
                ->index()
                ->comment('Last message read by this participant. FK is added later.');

            $table->timestamp('last_read_at')
                ->nullable()
                ->index()
                ->comment('Last read timestamp for unread counter calculations.');

            $table->timestamp('muted_until')
                ->nullable()
                ->index()
                ->comment('Notification mute expiration timestamp for this participant.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional participant-specific metadata.');

            $table->timestamps();

            /**
             * One user can participate only once in the same conversation.
             */
            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_unique_user');
        });

        DB::statement("ALTER TABLE conversation_participants COMMENT = 'Stores users participating in conversations, including roles, permissions, restrictions and read state.'");
    }

    /**
     * Drop conversation participants table.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};