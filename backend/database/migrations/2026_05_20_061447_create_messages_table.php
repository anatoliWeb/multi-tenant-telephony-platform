<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create messages table.
     *
     * This table stores all messages for direct, group, support,
     * external/API and system conversations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id()
                ->comment('Primary message ID.');

            $table->uuid('uuid')
                ->unique()
                ->comment('Public unique message identifier.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation where this message belongs.');

            /**
             * Sender information.
             *
             * sender_id can be null for system or external messages.
             * sender_type explains who/what created this message.
             */
            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who sent the message. Nullable for system/external messages.');

            $table->string('sender_type', 32)
                ->default('user')
                ->index()
                ->comment('Sender type: user, admin, external, system.');

            /**
             * External API idempotency.
             *
             * external_id is used when the message comes from an external API,
             * CRM, bot, webhook, or third-party integration.
             */
            $table->string('external_id')
                ->nullable()
                ->index()
                ->comment('External message identifier for API/webhook idempotency.');

            /**
             * Replies.
             */
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete()
                ->comment('Parent message ID when this message is a reply.');

            /**
             * Message content and type.
             */
            $table->string('type', 32)
                ->default('text')
                ->index()
                ->comment('Message type: text, file, mixed, system.');

            $table->longText('body')
                ->nullable()
                ->comment('Message text body. Nullable for file-only or system messages.');

            /**
             * Message lifecycle status.
             *
             * For group chats, per-user delivery/read state is stored
             * in message_deliveries and message_reads.
             */
            $table->string('status', 32)
                ->default('sent')
                ->index()
                ->comment('Message status: pending, sent, delivered, read, failed, deleted.');

            /**
             * Imported history.
             *
             * Used when a new private group chat is created from a direct chat
             * and selected old messages are copied into the new group chat.
             */
            $table->boolean('is_imported')
                ->default(false)
                ->index()
                ->comment('Whether this message was imported/copied from another conversation.');

            $table->foreignId('imported_from_conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete()
                ->comment('Original conversation ID if this message was imported.');

            $table->foreignId('imported_from_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete()
                ->comment('Original message ID if this message was imported.');

            /**
             * Message timestamps.
             */
            $table->timestamp('sent_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when the message was sent.');

            $table->timestamp('delivered_at')
                ->nullable()
                ->index()
                ->comment('Global delivery timestamp for simple/direct cases.');

            $table->timestamp('read_at')
                ->nullable()
                ->index()
                ->comment('Global read timestamp for simple/direct cases.');

            $table->timestamp('edited_at')
                ->nullable()
                ->comment('Timestamp when the message was edited.');

            $table->timestamp('deleted_at')
                ->nullable()
                ->index()
                ->comment('Soft delete marker for message-level deletion.');

            /**
             * Flexible metadata.
             *
             * Keep this safe. Do not store secrets or unfiltered external payloads here.
             */
            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe technical metadata for message rendering or integrations.');

            $table->timestamps();

            /**
             * Indexes for common chat queries.
             */
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_idx');
            $table->index(['conversation_id', 'sent_at'], 'messages_conversation_sent_idx');
            $table->index(['conversation_id', 'status'], 'messages_conversation_status_idx');
            $table->index(['conversation_id', 'type'], 'messages_conversation_type_idx');
            $table->index(['sender_id', 'created_at'], 'messages_sender_created_idx');
            $table->index(['is_imported', 'imported_from_conversation_id'], 'messages_import_source_idx');

            /**
             * Prevent duplicated external messages inside the same conversation.
             *
             * MySQL allows multiple NULL values in a unique index,
             * so normal internal messages without external_id are safe.
             */
            $table->unique(['conversation_id', 'external_id'], 'messages_conversation_external_unique');
        });

        DB::statement("ALTER TABLE messages COMMENT = 'Stores chat messages, including text, system, imported and external/API messages.'");
    }

    /**
     * Drop messages table.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};