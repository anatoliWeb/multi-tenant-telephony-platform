<?php

use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'conversations',
            'messages',
            'conversation_participants',
            'message_attachments',
            'message_reads',
            'message_device_reads',
            'message_deliveries',
            'external_message_mappings',
            'chat_webhook_endpoints',
            'chat_webhook_deliveries',
            'chat_user_devices',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignUuid('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->nullOnDelete();
            });
        }

        DB::statement(
            'UPDATE conversations SET tenant_id = ? WHERE tenant_id IS NULL',
            [TenantBootstrapService::DEFAULT_TENANT_UUID]
        );

        DB::statement(
            'UPDATE messages m INNER JOIN conversations c ON c.id = m.conversation_id SET m.tenant_id = c.tenant_id WHERE m.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE conversation_participants cp INNER JOIN conversations c ON c.id = cp.conversation_id SET cp.tenant_id = c.tenant_id WHERE cp.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE message_attachments ma INNER JOIN conversations c ON c.id = ma.conversation_id SET ma.tenant_id = c.tenant_id WHERE ma.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE message_reads mr INNER JOIN conversations c ON c.id = mr.conversation_id SET mr.tenant_id = c.tenant_id WHERE mr.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE message_device_reads mdr INNER JOIN conversations c ON c.id = mdr.conversation_id SET mdr.tenant_id = c.tenant_id WHERE mdr.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE message_deliveries md INNER JOIN conversations c ON c.id = md.conversation_id SET md.tenant_id = c.tenant_id WHERE md.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE external_message_mappings emm INNER JOIN conversations c ON c.id = emm.conversation_id SET emm.tenant_id = c.tenant_id WHERE emm.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE chat_webhook_deliveries cwd LEFT JOIN conversations c ON c.id = cwd.conversation_id LEFT JOIN chat_webhook_endpoints che ON che.id = cwd.webhook_endpoint_id SET cwd.tenant_id = COALESCE(c.tenant_id, che.tenant_id) WHERE cwd.tenant_id IS NULL'
        );

        DB::statement(
            'UPDATE chat_webhook_endpoints SET tenant_id = ? WHERE tenant_id IS NULL',
            [TenantBootstrapService::DEFAULT_TENANT_UUID]
        );

        DB::statement(
            'UPDATE chat_user_devices SET tenant_id = ? WHERE tenant_id IS NULL',
            [TenantBootstrapService::DEFAULT_TENANT_UUID]
        );

        Schema::table('external_message_mappings', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('external_message_provider_external_unique');
            $blueprint->dropUnique('external_message_provider_idempotency_unique');
            $blueprint->unique(['tenant_id', 'provider', 'external_id'], 'external_message_tenant_provider_external_unique');
            $blueprint->unique(['tenant_id', 'provider', 'idempotency_key'], 'external_message_tenant_provider_idempotency_unique');
        });

        Schema::table('chat_user_devices', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('chat_user_devices_user_device_unique');
            $blueprint->unique(['tenant_id', 'user_id', 'device_key'], 'chat_user_devices_tenant_user_device_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_user_devices', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('chat_user_devices_tenant_user_device_unique');
            $blueprint->unique(['user_id', 'device_key'], 'chat_user_devices_user_device_unique');
        });

        Schema::table('external_message_mappings', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('external_message_tenant_provider_external_unique');
            $blueprint->dropUnique('external_message_tenant_provider_idempotency_unique');
            $blueprint->unique(['provider', 'external_id'], 'external_message_provider_external_unique');
            $blueprint->unique(['provider', 'idempotency_key'], 'external_message_provider_idempotency_unique');
        });

        $tables = [
            'chat_user_devices',
            'chat_webhook_deliveries',
            'chat_webhook_endpoints',
            'external_message_mappings',
            'message_deliveries',
            'message_device_reads',
            'message_reads',
            'message_attachments',
            'conversation_participants',
            'messages',
            'conversations',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
