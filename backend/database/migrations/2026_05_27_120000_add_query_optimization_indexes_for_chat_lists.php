<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'id'], 'messages_conversation_id_id_idx');
        });

        Schema::table('chat_webhook_deliveries', function (Blueprint $table): void {
            $table->index(['conversation_id', 'id'], 'chat_webhook_deliveries_conversation_id_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex('chat_webhook_deliveries_conversation_id_id_idx');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_conversation_id_id_idx');
        });
    }
};

