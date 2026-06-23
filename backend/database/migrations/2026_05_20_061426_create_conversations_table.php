<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create conversations table.
     *
     * This table stores all chat containers:
     * direct chats, group chats, support/admin chats, external/API chats.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id()
                ->comment('Primary conversation ID.');

            $table->uuid('uuid')
                ->unique()
                ->comment('Public unique conversation identifier.');

            /**
             * Conversation classification.
             */
            $table->string('type', 32)
                ->default('direct')
                ->index()
                ->comment('Conversation type: direct, group, support, external, system.');

            $table->string('visibility', 32)
                ->default('private')
                ->index()
                ->comment('Conversation visibility: private or public.');

            $table->string('title')
                ->nullable()
                ->comment('Optional conversation title, mainly used for group/support chats.');

            $table->text('description')
                ->nullable()
                ->comment('Optional conversation description.');

            /**
             * Ownership and creation.
             */
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Current owner or responsible user for this conversation.');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who originally created this conversation.');

            $table->foreignId('created_from_conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete()
                ->comment('Source conversation ID when a group chat is created from a direct chat.');

            /**
             * Source and lifecycle.
             */
            $table->string('source', 32)
                ->default('internal')
                ->index()
                ->comment('Conversation source: internal, api, webhook, system.');

            $table->string('status', 32)
                ->default('active')
                ->index()
                ->comment('Conversation status: active, archived, closed, deleted.');

            $table->string('join_policy', 64)
                ->default('invite_only')
                ->comment('Join policy: invite_only, participants_can_invite, anyone_with_permission, public_join.');

            /**
             * History import settings.
             *
             * Used when a new private group chat is created from an existing direct chat.
             * Message references are stored here as raw IDs first.
             * Foreign keys to messages are added later because messages table does not exist yet.
             */
            $table->string('history_import_mode', 32)
                ->nullable()
                ->comment('History import mode: none, from_date, from_message, full.');

            $table->unsignedBigInteger('history_import_from_message_id')
                ->nullable()
                ->index()
                ->comment('Message ID from which history import starts. FK is added later.');

            $table->timestamp('history_import_from_at')
                ->nullable()
                ->index()
                ->comment('Date/time from which history import starts.');

            /**
             * Last message cache.
             *
             * Stored for fast conversation list sorting.
             * Foreign key is added after messages table exists.
             */
            $table->unsignedBigInteger('last_message_id')
                ->nullable()
                ->index()
                ->comment('Last message ID for quick conversation list rendering. FK is added later.');

            $table->timestamp('last_message_at')
                ->nullable()
                ->index()
                ->comment('Timestamp of the latest message in this conversation.');

            /**
             * Flexible metadata.
             */
            $table->json('metadata')
                ->nullable()
                ->comment('Optional technical/admin metadata for this conversation.');

            $table->timestamps();

            $table->softDeletes()
                ->comment('Soft delete marker for archived/removed conversations.');
        });

        DB::statement("ALTER TABLE conversations COMMENT = 'Stores all chat conversations: direct, group, support, external and system chats.'");
    }

    /**
     * Drop conversations table.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};