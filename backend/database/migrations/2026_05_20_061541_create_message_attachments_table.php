<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create message attachments table.
     *
     * This table stores files attached to chat messages.
     * Files can be uploaded directly or imported from another conversation.
     */
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id()
                ->comment('Primary attachment ID.');

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Message this attachment belongs to.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation this attachment belongs to.');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who uploaded the attachment. Nullable for imported/system files.');

            /**
             * Storage location.
             */
            $table->string('disk', 64)
                ->default('local')
                ->comment('Laravel filesystem disk where the file is stored.');

            $table->string('path')
                ->comment('Storage path of the file on the configured disk.');

            $table->string('original_name')
                ->comment('Original client filename.');

            /**
             * File metadata.
             */
            $table->string('mime_type', 128)
                ->index()
                ->comment('Detected MIME type of the uploaded file.');

            $table->unsignedBigInteger('size')
                ->comment('File size in bytes.');

            $table->string('checksum', 128)
                ->nullable()
                ->index()
                ->comment('Optional file checksum for duplicate detection or integrity checks.');

            /**
             * Imported/copied attachments.
             *
             * When messages are imported from a direct chat into a new group chat,
             * we create a new attachment row but may reuse the same physical file.
             */
            $table->foreignId('copied_from_attachment_id')
                ->nullable()
                ->constrained('message_attachments')
                ->nullOnDelete()
                ->comment('Original attachment ID if this attachment was copied/imported.');

            $table->boolean('is_imported')
                ->default(false)
                ->index()
                ->comment('Whether this attachment was imported from another message/conversation.');

            /**
             * Attachment state.
             */
            $table->string('status', 32)
                ->default('active')
                ->index()
                ->comment('Attachment status: active, deleted, quarantined, failed.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe attachment metadata such as image dimensions or preview info.');

            $table->timestamps();

            $table->softDeletes()
                ->comment('Soft delete marker for attachment records.');

            /**
             * Common lookup indexes.
             */
            $table->index(['message_id', 'created_at'], 'message_attachments_message_created_idx');
            $table->index(['conversation_id', 'created_at'], 'message_attachments_conversation_created_idx');
            $table->index(['uploaded_by', 'created_at'], 'message_attachments_uploaded_by_created_idx');
            $table->index(['is_imported', 'copied_from_attachment_id'], 'message_attachments_import_source_idx');
        });

        DB::statement("ALTER TABLE message_attachments COMMENT = 'Stores files attached to chat messages, including imported/copied attachments.'");
    }

    /**
     * Drop message attachments table.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};