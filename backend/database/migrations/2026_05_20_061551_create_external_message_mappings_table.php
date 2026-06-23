<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create external message mappings table.
     *
     * This table links internal messages to external systems:
     * CRM messages, bots, third-party APIs, webhooks, or support tools.
     *
     * It is mainly used for idempotency and callback tracking.
     */
    public function up(): void
    {
        Schema::create('external_message_mappings', function (Blueprint $table) {
            $table->id()
                ->comment('Primary external mapping ID.');

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Internal chat message linked to the external message.');

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete()
                ->comment('Internal conversation linked to the external message.');

            /**
             * External provider identity.
             */
            $table->string('provider', 64)
                ->index()
                ->comment('External provider name, for example crm, bot, api_client, webhook.');

            $table->string('external_id')
                ->comment('External message ID from the provider.');

            $table->string('external_conversation_id')
                ->nullable()
                ->index()
                ->comment('External conversation/thread/ticket ID from the provider.');

            /**
             * Direction.
             */
            $table->string('direction', 32)
                ->index()
                ->comment('Message direction: inbound or outbound.');

            /**
             * Idempotency / integrity.
             */
            $table->string('idempotency_key')
                ->nullable()
                ->index()
                ->comment('Optional idempotency key supplied by external API client.');

            $table->string('payload_hash', 128)
                ->nullable()
                ->comment('Optional hash of normalized external payload.');

            /**
             * Safe technical metadata only.
             */
            $table->json('metadata')
                ->nullable()
                ->comment('Optional safe mapping metadata. Do not store secrets here.');

            $table->timestamps();

            /**
             * Prevent duplicate external messages from the same provider.
             */
            $table->unique(['provider', 'external_id'], 'external_message_provider_external_unique');

            /**
             * Prevent duplicate idempotency keys per provider when present.
             *
             * MySQL allows multiple NULL values in a unique index,
             * so rows without idempotency_key are safe.
             */
            $table->unique(['provider', 'idempotency_key'], 'external_message_provider_idempotency_unique');

            /**
             * Common lookup indexes.
             */
            $table->index(['conversation_id', 'created_at'], 'external_message_conversation_created_idx');
            $table->index(['message_id', 'direction'], 'external_message_message_direction_idx');
            $table->index(['provider', 'direction'], 'external_message_provider_direction_idx');
        });

        DB::statement("ALTER TABLE external_message_mappings COMMENT = 'Maps internal chat messages to external API, CRM, bot or webhook message identifiers.'");
    }

    /**
     * Drop external message mappings table.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_message_mappings');
    }
};