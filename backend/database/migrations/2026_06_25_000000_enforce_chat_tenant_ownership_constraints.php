<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
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

    public function up(): void
    {
        $this->guardBaseTenantMigrationExists();

        DB::statement('
            UPDATE messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            SET m.tenant_id = c.tenant_id
            WHERE m.tenant_id IS NULL
        ');

        DB::statement('
            UPDATE conversation_participants cp
            INNER JOIN conversations c ON c.id = cp.conversation_id
            SET cp.tenant_id = c.tenant_id
            WHERE cp.tenant_id IS NULL
        ');

        DB::statement('
            UPDATE message_attachments ma
            INNER JOIN messages m ON m.id = ma.message_id
            INNER JOIN conversations c ON c.id = ma.conversation_id
            SET ma.tenant_id = c.tenant_id
            WHERE ma.tenant_id IS NULL
              AND m.conversation_id = c.id
              AND (m.tenant_id = c.tenant_id OR m.tenant_id IS NULL)
        ');

        DB::statement('
            UPDATE message_reads mr
            INNER JOIN messages m ON m.id = mr.message_id
            SET mr.tenant_id = m.tenant_id
            WHERE mr.tenant_id IS NULL
              AND mr.conversation_id = m.conversation_id
        ');

        DB::statement('
            UPDATE message_device_reads mdr
            INNER JOIN messages m ON m.id = mdr.message_id
            SET mdr.tenant_id = m.tenant_id
            WHERE mdr.tenant_id IS NULL
              AND mdr.conversation_id = m.conversation_id
        ');

        DB::statement('
            UPDATE message_deliveries md
            INNER JOIN messages m ON m.id = md.message_id
            SET md.tenant_id = m.tenant_id
            WHERE md.tenant_id IS NULL
              AND md.conversation_id = m.conversation_id
        ');

        DB::statement('
            UPDATE external_message_mappings emm
            INNER JOIN messages m ON m.id = emm.message_id
            INNER JOIN conversations c ON c.id = emm.conversation_id
            SET emm.tenant_id = c.tenant_id
            WHERE emm.tenant_id IS NULL
              AND m.conversation_id = c.id
              AND (m.tenant_id = c.tenant_id OR m.tenant_id IS NULL)
        ');

        DB::statement('
            UPDATE chat_webhook_deliveries cwd
            INNER JOIN chat_webhook_endpoints che ON che.id = cwd.webhook_endpoint_id
            LEFT JOIN conversations c ON c.id = cwd.conversation_id
            LEFT JOIN messages m ON m.id = cwd.message_id
            SET cwd.tenant_id = COALESCE(m.tenant_id, c.tenant_id, che.tenant_id)
            WHERE cwd.tenant_id IS NULL
              AND (m.id IS NULL OR c.id IS NULL OR m.conversation_id = c.id)
              AND (m.id IS NULL OR c.id IS NULL OR m.tenant_id = c.tenant_id)
              AND (m.id IS NULL OR m.tenant_id = che.tenant_id)
              AND (c.id IS NULL OR c.tenant_id = che.tenant_id)
        ');

        $this->assertCount('SELECT COUNT(*) FROM conversations WHERE tenant_id IS NULL', 0, 'Cannot enforce chat tenant ownership: conversations with NULL tenant_id remain.');
        $this->assertCount('SELECT COUNT(*) FROM messages WHERE tenant_id IS NULL', 0, 'Cannot enforce chat tenant ownership: messages with NULL tenant_id remain.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE m.tenant_id <> c.tenant_id
        ', 0, 'Cannot enforce chat tenant ownership: message tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM conversation_participants cp
            INNER JOIN conversations c ON c.id = cp.conversation_id
            WHERE cp.tenant_id IS NULL
               OR cp.tenant_id <> c.tenant_id
        ', 0, 'Cannot enforce chat tenant ownership: participant tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM message_attachments ma
            INNER JOIN messages m ON m.id = ma.message_id
            INNER JOIN conversations c ON c.id = ma.conversation_id
            WHERE ma.tenant_id IS NULL
               OR ma.tenant_id <> m.tenant_id
               OR ma.tenant_id <> c.tenant_id
               OR m.tenant_id <> c.tenant_id
               OR ma.conversation_id <> m.conversation_id
        ', 0, 'Cannot enforce chat tenant ownership: attachment tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM message_reads mr
            INNER JOIN messages m ON m.id = mr.message_id
            WHERE mr.tenant_id IS NULL
               OR mr.tenant_id <> m.tenant_id
               OR mr.conversation_id <> m.conversation_id
        ', 0, 'Cannot enforce chat tenant ownership: read-state tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM message_device_reads mdr
            INNER JOIN messages m ON m.id = mdr.message_id
            WHERE mdr.tenant_id IS NULL
               OR mdr.tenant_id <> m.tenant_id
               OR mdr.conversation_id <> m.conversation_id
        ', 0, 'Cannot enforce chat tenant ownership: device-read tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM message_deliveries md
            INNER JOIN messages m ON m.id = md.message_id
            WHERE md.tenant_id IS NULL
               OR md.tenant_id <> m.tenant_id
               OR md.conversation_id <> m.conversation_id
        ', 0, 'Cannot enforce chat tenant ownership: delivery tenant mismatch detected.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM external_message_mappings emm
            INNER JOIN messages m ON m.id = emm.message_id
            INNER JOIN conversations c ON c.id = emm.conversation_id
            WHERE emm.tenant_id IS NULL
               OR emm.tenant_id <> m.tenant_id
               OR emm.tenant_id <> c.tenant_id
               OR m.tenant_id <> c.tenant_id
               OR emm.conversation_id <> m.conversation_id
        ', 0, 'Cannot enforce chat tenant ownership: external mapping tenant mismatch detected.');
        $this->assertCount('SELECT COUNT(*) FROM chat_webhook_endpoints WHERE tenant_id IS NULL', 0, 'Cannot enforce chat tenant ownership: webhook endpoints with NULL tenant_id remain.');
        $this->assertCount('
            SELECT COUNT(*)
            FROM chat_webhook_deliveries cwd
            INNER JOIN chat_webhook_endpoints che ON che.id = cwd.webhook_endpoint_id
            LEFT JOIN conversations c ON c.id = cwd.conversation_id
            LEFT JOIN messages m ON m.id = cwd.message_id
            WHERE cwd.tenant_id IS NULL
               OR cwd.tenant_id <> che.tenant_id
               OR (c.id IS NOT NULL AND cwd.tenant_id <> c.tenant_id)
               OR (m.id IS NOT NULL AND cwd.tenant_id <> m.tenant_id)
               OR (m.id IS NOT NULL AND c.id IS NOT NULL AND m.conversation_id <> c.id)
        ', 0, 'Cannot enforce chat tenant ownership: webhook delivery tenant mismatch detected.');
        $this->assertCount('SELECT COUNT(*) FROM chat_user_devices WHERE tenant_id IS NULL', 0, 'Cannot enforce chat tenant ownership: chat devices with NULL tenant_id remain.');

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_tenant_id_foreign`");
            DB::statement("ALTER TABLE `{$table}` MODIFY `tenant_id` CHAR(36) NOT NULL");
            DB::statement("
                ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$table}_tenant_id_foreign`
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_tenant_id_foreign`");
            DB::statement("ALTER TABLE `{$table}` MODIFY `tenant_id` CHAR(36) NULL");
            DB::statement("
                ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$table}_tenant_id_foreign`
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ");
        }
    }

    private function guardBaseTenantMigrationExists(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                throw new RuntimeException("Cannot enforce chat tenant ownership before tenant_id exists on {$table}.");
            }
        }
    }

    private function assertCount(string $sql, int $expected, string $message): void
    {
        $count = (int) DB::scalar($sql);

        if ($count !== $expected) {
            throw new RuntimeException("{$message} Count: {$count}.");
        }
    }
};
