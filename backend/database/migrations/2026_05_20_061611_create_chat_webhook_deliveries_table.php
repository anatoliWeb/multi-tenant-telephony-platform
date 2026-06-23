<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create chat webhook deliveries table.
     *
     * This table stores every outgoing webhook delivery attempt.
     * It is used for retries, debugging, external API status callbacks,
     * and admin monitoring.
     */
    public function up(): void
    {
        Schema::create('chat_webhook_deliveries', function (Blueprint $table) {
            $table->id()
                ->comment('Primary webhook delivery ID.');

            $table->foreignId('webhook_endpoint_id')
                ->constrained('chat_webhook_endpoints')
                ->cascadeOnDelete()
                ->comment('Webhook endpoint used for this delivery.');

            /**
             * Optional chat references.
             *
             * These fields allow admin/debug screens to connect webhook delivery
             * back to a conversation or message.
             */
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete()
                ->comment('Related conversation ID, if the webhook event belongs to a conversation.');

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete()
                ->comment('Related message ID, if the webhook event belongs to a message.');

            /**
             * Event identity.
             */
            $table->string('event', 128)
                ->index()
                ->comment('Webhook event name, for example message.created, message.read, message.failed.');

            $table->uuid('delivery_uuid')
                ->unique()
                ->comment('Public unique delivery identifier used for tracing and idempotency.');

            /**
             * Payload and signature.
             */
            $table->json('payload')
                ->comment('Webhook payload that was sent or will be sent.');

            $table->string('signature', 255)
                ->nullable()
                ->comment('HMAC signature generated for this webhook delivery.');

            /**
             * Delivery lifecycle.
             */
            $table->string('status', 32)
                ->default('pending')
                ->index()
                ->comment('Delivery status: pending, sent, failed, retrying, cancelled.');

            $table->unsignedInteger('attempts')
                ->default(0)
                ->comment('Number of delivery attempts.');

            $table->timestamp('next_retry_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when the next retry should be attempted.');

            $table->timestamp('sent_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when delivery was successfully sent.');

            $table->timestamp('failed_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when delivery finally failed.');

            /**
             * HTTP response details.
             */
            $table->unsignedSmallInteger('response_status')
                ->nullable()
                ->index()
                ->comment('HTTP status code returned by the webhook endpoint.');

            $table->text('response_body')
                ->nullable()
                ->comment('Response body returned by the webhook endpoint. Store truncated/safe data only.');

            $table->text('error_message')
                ->nullable()
                ->comment('Error message if webhook delivery failed.');

            /**
             * Safe technical metadata.
             */
            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe delivery metadata for debugging and retries.');

            $table->timestamps();

            /**
             * Common lookup indexes.
             */
            $table->index(['webhook_endpoint_id', 'status'], 'chat_webhook_deliveries_endpoint_status_idx');
            $table->index(['conversation_id', 'created_at'], 'chat_webhook_deliveries_conversation_created_idx');
            $table->index(['message_id', 'created_at'], 'chat_webhook_deliveries_message_created_idx');
            $table->index(['event', 'status'], 'chat_webhook_deliveries_event_status_idx');
            $table->index(['status', 'next_retry_at'], 'chat_webhook_deliveries_retry_idx');
        });

        DB::statement("ALTER TABLE chat_webhook_deliveries COMMENT = 'Stores outgoing chat webhook delivery attempts, statuses, responses and retry information.'");
    }

    /**
     * Drop chat webhook deliveries table.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_webhook_deliveries');
    }
};