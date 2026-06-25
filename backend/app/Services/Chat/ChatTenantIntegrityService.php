<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\DB;

class ChatTenantIntegrityService
{
    /**
     * @var array<int, string>
     */
    private const TABLES = [
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

    /**
     * @var array<string, string>
     */
    private const OWNERSHIP_RULES = [
        'conversations' => 'Conversation is the root tenant-owned chat aggregate.',
        'messages' => 'Message tenant must match the owning conversation tenant.',
        'conversation_participants' => 'Participant tenant must match the owning conversation tenant.',
        'message_attachments' => 'Attachment tenant must match the owning message and conversation tenant.',
        'message_reads' => 'Read receipt tenant must match the owning message tenant.',
        'message_device_reads' => 'Device read tenant must match the owning message tenant.',
        'message_deliveries' => 'Delivery tenant must match the owning message tenant.',
        'external_message_mappings' => 'External mapping tenant must match the owning conversation and message tenant.',
        'chat_webhook_endpoints' => 'Webhook endpoint is explicitly owned by one tenant.',
        'chat_webhook_deliveries' => 'Webhook delivery tenant must match the endpoint tenant and any linked chat records.',
        'chat_user_devices' => 'Chat device uniqueness is tenant-scoped per user and device key.',
    ];

    public function inspect(): array
    {
        $schema = (string) DB::getDatabaseName();
        $tableList = $this->quotedList(self::TABLES);
        $columnRows = collect(DB::select("
            SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME IN ({$tableList})
              AND COLUMN_NAME = 'tenant_id'
        ", [$schema]))->keyBy('TABLE_NAME');

        $fkRows = collect(DB::select("
            SELECT
                kcu.TABLE_NAME,
                kcu.CONSTRAINT_NAME,
                kcu.REFERENCED_TABLE_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
               AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.TABLE_SCHEMA = ?
              AND kcu.TABLE_NAME IN ({$tableList})
              AND kcu.COLUMN_NAME = 'tenant_id'
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ", [$schema]))->groupBy('TABLE_NAME');

        $indexRows = collect(DB::select("
            SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME IN ({$tableList})
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
        ", [$schema]))->groupBy('TABLE_NAME');

        $tables = [];
        foreach (self::TABLES as $table) {
            $column = $columnRows->get($table);
            $indexes = $this->formatIndexes(collect($indexRows->get($table, [])));
            $tables[$table] = [
                'tenant_id_exists' => $column !== null,
                'tenant_id_nullable' => $column?->IS_NULLABLE === 'YES' ? 'YES' : ($column ? 'NO' : 'ABSENT'),
                'foreign_key' => $this->formatForeignKey(collect($fkRows->get($table, []))),
                'indexes' => array_values(array_filter($indexes, static fn (array $index): bool => ! $index['unique'])),
                'unique_constraints' => array_values(array_filter($indexes, static fn (array $index): bool => $index['unique'])),
                'total_rows' => (int) DB::table($table)->count(),
                'tenant_id_null_rows' => $column ? (int) DB::table($table)->whereNull('tenant_id')->count() : null,
                'ownership_rule' => self::OWNERSHIP_RULES[$table],
            ];
        }

        $mismatches = [
            'messages_vs_conversations' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM messages m
                INNER JOIN conversations c ON c.id = m.conversation_id
                WHERE m.tenant_id <> c.tenant_id
                   OR m.tenant_id IS NULL
                   OR c.tenant_id IS NULL
            "),
            'participants_vs_conversations' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM conversation_participants cp
                INNER JOIN conversations c ON c.id = cp.conversation_id
                WHERE cp.tenant_id <> c.tenant_id
                   OR cp.tenant_id IS NULL
                   OR c.tenant_id IS NULL
            "),
            'attachments_vs_messages' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM message_attachments ma
                INNER JOIN messages m ON m.id = ma.message_id
                INNER JOIN conversations c ON c.id = ma.conversation_id
                WHERE ma.tenant_id <> m.tenant_id
                   OR ma.tenant_id <> c.tenant_id
                   OR m.tenant_id <> c.tenant_id
                   OR m.conversation_id <> ma.conversation_id
                   OR ma.tenant_id IS NULL
                   OR m.tenant_id IS NULL
                   OR c.tenant_id IS NULL
            "),
            'reads_vs_messages' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM message_reads mr
                INNER JOIN messages m ON m.id = mr.message_id
                WHERE mr.tenant_id <> m.tenant_id
                   OR mr.conversation_id <> m.conversation_id
                   OR mr.tenant_id IS NULL
                   OR m.tenant_id IS NULL
            "),
            'device_reads_vs_messages' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM message_device_reads mdr
                INNER JOIN messages m ON m.id = mdr.message_id
                WHERE mdr.tenant_id <> m.tenant_id
                   OR mdr.conversation_id <> m.conversation_id
                   OR mdr.tenant_id IS NULL
                   OR m.tenant_id IS NULL
            "),
            'deliveries_vs_messages' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM message_deliveries md
                INNER JOIN messages m ON m.id = md.message_id
                WHERE md.tenant_id <> m.tenant_id
                   OR md.conversation_id <> m.conversation_id
                   OR md.tenant_id IS NULL
                   OR m.tenant_id IS NULL
            "),
            'external_mappings_vs_chat_records' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM external_message_mappings emm
                INNER JOIN messages m ON m.id = emm.message_id
                INNER JOIN conversations c ON c.id = emm.conversation_id
                WHERE emm.tenant_id <> m.tenant_id
                   OR emm.tenant_id <> c.tenant_id
                   OR m.tenant_id <> c.tenant_id
                   OR m.conversation_id <> emm.conversation_id
                   OR emm.tenant_id IS NULL
                   OR m.tenant_id IS NULL
                   OR c.tenant_id IS NULL
            "),
            'webhook_endpoints_vs_deliveries' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM chat_webhook_deliveries cwd
                INNER JOIN chat_webhook_endpoints che ON che.id = cwd.webhook_endpoint_id
                LEFT JOIN conversations c ON c.id = cwd.conversation_id
                LEFT JOIN messages m ON m.id = cwd.message_id
                WHERE cwd.tenant_id <> che.tenant_id
                   OR (c.id IS NOT NULL AND cwd.tenant_id <> c.tenant_id)
                   OR (m.id IS NOT NULL AND cwd.tenant_id <> m.tenant_id)
                   OR (m.id IS NOT NULL AND c.id IS NOT NULL AND m.conversation_id <> c.id)
                   OR cwd.tenant_id IS NULL
                   OR che.tenant_id IS NULL
                   OR (c.id IS NOT NULL AND c.tenant_id IS NULL)
                   OR (m.id IS NOT NULL AND m.tenant_id IS NULL)
            "),
            'chat_user_devices_duplicate_scope_rows' => $this->countQuery("
                SELECT COUNT(*) AS aggregate
                FROM (
                    SELECT tenant_id, user_id, device_key, COUNT(*) AS duplicate_rows
                    FROM chat_user_devices
                    GROUP BY tenant_id, user_id, device_key
                    HAVING COUNT(*) > 1
                ) duplicates
            "),
        ];

        return [
            'tables' => $tables,
            'mismatches' => $mismatches,
            'has_failures' => $this->hasFailures($tables, $mismatches),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     * @param array<string, int> $mismatches
     */
    private function hasFailures(array $tables, array $mismatches): bool
    {
        foreach ($tables as $table) {
            if (! $table['tenant_id_exists']) {
                return true;
            }

            if (($table['tenant_id_null_rows'] ?? 0) > 0) {
                return true;
            }
        }

        foreach ($mismatches as $count) {
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    private function countQuery(string $sql): int
    {
        $row = DB::selectOne($sql);

        return (int) ($row->aggregate ?? 0);
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows
     * @return array<int, array{name: string, unique: bool, columns: array<int, string>}>
     */
    private function formatIndexes($rows): array
    {
        return $rows
            ->groupBy('INDEX_NAME')
            ->map(function ($indexRows, string $name): array {
                return [
                    'name' => $name,
                    'unique' => (int) $indexRows->first()->NON_UNIQUE === 0,
                    'columns' => $indexRows->pluck('COLUMN_NAME')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows
     * @return array<string, string>|null
     */
    private function formatForeignKey($rows): ?array
    {
        $row = $rows->first();
        if (! $row) {
            return null;
        }

        return [
            'constraint' => (string) $row->CONSTRAINT_NAME,
            'references' => (string) $row->REFERENCED_TABLE_NAME,
            'on_delete' => (string) $row->DELETE_RULE,
            'on_update' => (string) $row->UPDATE_RULE,
        ];
    }

    /**
     * @param array<int, string> $values
     */
    private function quotedList(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => DB::getPdo()->quote($value),
            $values
        ));
    }
}
