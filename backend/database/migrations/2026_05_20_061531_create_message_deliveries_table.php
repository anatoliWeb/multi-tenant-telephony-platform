<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create message deliveries table.
     *
     * This table stores per-recipient delivery status.
     * It is useful for group chats, external APIs, and webhook callbacks.
     */
    public function up(): void
    {
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id()
                ->comment('Primary message delivery ID.');

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Message being delivered.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Conversation where the message belongs.');

            /**
             * User recipient.
             *
             * Nullable because some deliveries may target external systems
             * instead of local users.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Local user recipient. Nullable for external delivery targets.');

            /**
             * Optional external recipient identifier.
             *
             * Useful when delivery status is tracked for API/webhook clients,
             * bots, CRMs, or other integrations.
             */
            $table->string('external_recipient_id')
                ->nullable()
                ->index()
                ->comment('External recipient identifier for API/webhook delivery tracking.');

            $table->string('recipient_type', 32)
                ->default('user')
                ->index()
                ->comment('Recipient type: user, external, webhook, system.');

            /**
             * Delivery lifecycle.
             */
            $table->string('status', 32)
                ->default('pending')
                ->index()
                ->comment('Delivery status: pending, delivered, failed, skipped.');

            $table->timestamp('delivered_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when the message was delivered to this recipient.');

            $table->timestamp('failed_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when delivery failed.');

            $table->text('failure_reason')
                ->nullable()
                ->comment('Human-readable delivery failure reason.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe delivery metadata.');

            $table->timestamps();

            /**
             * Local user delivery should be unique per message.
             *
             * MySQL allows multiple NULL values, so external deliveries with null user_id
             * are handled separately through indexes and external_recipient_id.
             */
            $table->unique(['message_id', 'user_id'], 'message_deliveries_unique_user_message');

            /**
             * Fast delivery status queries.
             */
            $table->index(['conversation_id', 'status'], 'message_deliveries_conversation_status_idx');
            $table->index(['message_id', 'status'], 'message_deliveries_message_status_idx');
            $table->index(['user_id', 'status'], 'message_deliveries_user_status_idx');
            $table->index(['recipient_type', 'status'], 'message_deliveries_recipient_status_idx');
        });

        DB::statement("ALTER TABLE message_deliveries COMMENT = 'Stores per-recipient message delivery state for local users and external integrations.'");
    }

    /**
     * Drop message deliveries table.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};