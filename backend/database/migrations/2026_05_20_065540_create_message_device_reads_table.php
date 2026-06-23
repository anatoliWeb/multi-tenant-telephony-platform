<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create message device reads table.
     *
     * WHY:
     * This table stores per-device read receipts.
     * It answers the question:
     * "On which user device was this message read?"
     */
    public function up(): void
    {
        Schema::create('message_device_reads', function (Blueprint $table) {
            $table->id()
                ->comment('Primary message device read ID.');

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Message that was read on the device.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation where the message belongs.');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('User who owns the device.');

            $table->foreignId('chat_user_device_id')
                ->nullable()
                ->constrained('chat_user_devices')
                ->nullOnDelete()
                ->comment('Registered chat device that read the message.');

            /**
             * Device snapshot.
             *
             * WHY:
             * We keep device_key snapshot even if the device record is deleted later.
             */
            $table->string('device_key', 128)
                ->nullable()
                ->index()
                ->comment('Device key snapshot used when the message was read.');

            $table->string('device_type', 32)
                ->nullable()
                ->index()
                ->comment('Device type snapshot: browser, mobile, desktop, tablet, api, unknown.');

            $table->string('platform', 64)
                ->nullable()
                ->comment('Platform snapshot, for example Windows, macOS, iOS, Android, Linux.');

            $table->string('browser', 64)
                ->nullable()
                ->comment('Browser snapshot, for example Chrome, Firefox, Safari, Edge.');

            $table->timestamp('read_at')
                ->index()
                ->comment('Timestamp when this device read the message.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe metadata for device read tracking.');

            $table->timestamps();

            /**
             * One read receipt per message/device.
             */
            $table->unique(['message_id', 'chat_user_device_id'], 'message_device_reads_message_device_unique');

            /**
             * Fallback uniqueness for clients that send only device_key.
             *
             * MySQL allows multiple NULL values in unique indexes,
             * so registered devices and unknown devices can coexist safely.
             */
            $table->unique(['message_id', 'user_id', 'device_key'], 'message_device_reads_message_user_device_key_unique');

            /**
             * Common lookup indexes.
             */
            $table->index(['conversation_id', 'user_id'], 'message_device_reads_conversation_user_idx');
            $table->index(['conversation_id', 'read_at'], 'message_device_reads_conversation_read_at_idx');
            $table->index(['user_id', 'read_at'], 'message_device_reads_user_read_at_idx');
            $table->index(['chat_user_device_id', 'read_at'], 'message_device_reads_device_read_at_idx');
        });

        DB::statement("ALTER TABLE message_device_reads COMMENT = 'Stores per-device read receipts for chat messages.'");
    }

    /**
     * Drop message device reads table.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_device_reads');
    }
};