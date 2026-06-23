<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create chat webhook endpoints table.
     *
     * This table stores external webhook URLs.
     * The system will send message/conversation events to these endpoints.
     */
    public function up(): void
    {
        Schema::create('chat_webhook_endpoints', function (Blueprint $table) {
            $table->id()
                ->comment('Primary webhook endpoint ID.');

            $table->uuid('uuid')
                ->unique()
                ->comment('Public unique webhook endpoint identifier.');

            /**
             * Endpoint identity.
             */
            $table->string('name')
                ->comment('Human-readable webhook endpoint name.');

            $table->string('url', 2048)
                ->comment('External URL where webhook events will be delivered.');

            /**
             * Security.
             *
             * Secret should be encrypted at application level before storing,
             * or protected with a dedicated cast/service.
             */
            $table->text('secret')
                ->comment('Webhook signing secret used for HMAC signatures. Store encrypted if possible.');

            /**
             * Subscribed events.
             *
             * Example:
             * [
             *   "message.created",
             *   "message.delivered",
             *   "message.read"
             * ]
             */
            $table->json('events')
                ->comment('List of webhook events this endpoint is subscribed to.');

            /**
             * Endpoint state.
             */
            $table->boolean('is_active')
                ->default(true)
                ->index()
                ->comment('Whether this webhook endpoint is active.');

            $table->string('status', 32)
                ->default('active')
                ->index()
                ->comment('Endpoint status: active, disabled, failed.');

            $table->unsignedInteger('failure_count')
                ->default(0)
                ->comment('Consecutive delivery failure count.');

            /**
             * Ownership.
             */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User/admin who created this webhook endpoint.');

            /**
             * Usage timestamps.
             */
            $table->timestamp('last_used_at')
                ->nullable()
                ->index()
                ->comment('Timestamp when this endpoint was last used.');

            $table->timestamp('last_success_at')
                ->nullable()
                ->index()
                ->comment('Timestamp of the latest successful delivery.');

            $table->timestamp('last_failure_at')
                ->nullable()
                ->index()
                ->comment('Timestamp of the latest failed delivery.');

            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe webhook endpoint metadata.');

            $table->timestamps();

            $table->softDeletes()
                ->comment('Soft delete marker for webhook endpoints.');

            /**
             * Common lookup indexes.
             */
            $table->index(['is_active', 'status'], 'chat_webhook_endpoints_active_status_idx');
            $table->index(['created_by', 'created_at'], 'chat_webhook_endpoints_created_by_created_idx');
        });

        DB::statement("ALTER TABLE chat_webhook_endpoints COMMENT = 'Stores external webhook endpoints subscribed to chat events.'");
    }

    /**
     * Drop chat webhook endpoints table.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_webhook_endpoints');
    }
};