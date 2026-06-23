<?php

namespace Database\Seeders;

use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatDemoSeeder extends Seeder
{
    /**
     * Seed demo chat conversations and messages.
     *
     * WHY:
     * This seeder gives local/development environments realistic chat data:
     * - direct conversations
     * - private group conversations
     * - public group conversations
     * - support/admin conversations
     * - external/API conversations
     * - message reads
     * - message deliveries
     *
     * SAFETY:
     * This seeder is guarded against production usage.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('ChatDemoSeeder skipped: production environment detected.');

            return;
        }

        if (! (bool) env('CHAT_DEMO_SEED', false)) {
            $this->command?->warn('ChatDemoSeeder skipped: CHAT_DEMO_SEED is not enabled.');

            return;
        }

        $users = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($users->count() < 2) {
            $this->command?->warn('ChatDemoSeeder skipped: at least 2 users are required.');

            return;
        }

        $messagesCount = max((int) env('CHAT_DEMO_MESSAGES_COUNT', 320), 320);

        $faker = FakerFactory::create();

        DB::transaction(function () use ($users, $messagesCount, $faker): void {
            /**
             * Optional cleanup.
             *
             * WHY:
             * We delete only demo conversations marked with metadata.demo_seed = true.
             * This keeps manually created local data safer.
             */
            $this->deletePreviousDemoData();

            $conversationIds = [];

            $conversationIds[] = $this->createConversation(
                type: 'direct',
                visibility: 'private',
                title: null,
                ownerId: $users[0]->id,
                createdBy: $users[0]->id,
                source: 'internal',
                joinPolicy: 'invite_only',
                participantUsers: $users->slice(0, 2)->values()->all(),
            );

            $conversationIds[] = $this->createConversation(
                type: 'group',
                visibility: 'private',
                title: 'Private project discussion',
                ownerId: $users[0]->id,
                createdBy: $users[0]->id,
                source: 'internal',
                joinPolicy: 'participants_can_invite',
                participantUsers: $users->slice(0, min(5, $users->count()))->values()->all(),
            );

            $conversationIds[] = $this->createConversation(
                type: 'group',
                visibility: 'public',
                title: 'Public team room',
                ownerId: $users[1]->id,
                createdBy: $users[1]->id,
                source: 'internal',
                joinPolicy: 'anyone_with_permission',
                participantUsers: $users->slice(0, min(8, $users->count()))->values()->all(),
            );

            $conversationIds[] = $this->createConversation(
                type: 'support',
                visibility: 'private',
                title: 'Support conversation',
                ownerId: $users[0]->id,
                createdBy: $users[min(2, $users->count() - 1)]->id,
                source: 'internal',
                joinPolicy: 'invite_only',
                participantUsers: $users->slice(0, min(4, $users->count()))->values()->all(),
            );

            $conversationIds[] = $this->createConversation(
                type: 'external',
                visibility: 'private',
                title: 'External API conversation',
                ownerId: $users[0]->id,
                createdBy: $users[0]->id,
                source: 'api',
                joinPolicy: 'invite_only',
                participantUsers: $users->slice(0, min(3, $users->count()))->values()->all(),
            );

            /**
             * Imported-history example.
             *
             * WHY:
             * This simulates our selected Variant B:
             * direct chat remains unchanged, and a new private group chat receives imported history.
             */
            $importedGroupId = $this->createConversation(
                type: 'group',
                visibility: 'private',
                title: 'Imported direct history example',
                ownerId: $users[0]->id,
                createdBy: $users[0]->id,
                source: 'internal',
                joinPolicy: 'invite_only',
                participantUsers: $users->slice(0, min(3, $users->count()))->values()->all(),
                createdFromConversationId: $conversationIds[0],
                historyImportMode: 'from_date',
                historyImportFromAt: Carbon::now()->subDays(3),
            );

            $conversationIds[] = $importedGroupId;

            $this->seedMessages(
                conversationIds: $conversationIds,
                users: $users,
                messagesCount: $messagesCount,
                faker: $faker,
            );

            $this->seedDevicesAndDeviceReads($conversationIds);
        });

        $this->command?->info("ChatDemoSeeder completed: seeded {$messagesCount}+ demo chat messages.");
    }

    /**
     * Delete previously generated demo chat data.
     *
     * WHY:
     * Allows the seeder to be safely re-run in local/dev environments.
     */
    private function deletePreviousDemoData(): void
    {
        $demoConversationIds = DB::table('conversations')
            ->where('metadata->demo_seed', true)
            ->pluck('id');

        if ($demoConversationIds->isEmpty()) {
            return;
        }

        DB::table('chat_moderation_logs')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('chat_webhook_deliveries')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('external_message_mappings')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('message_deliveries')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('message_device_reads')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('message_reads')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('message_attachments')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('messages')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('conversation_participants')
            ->whereIn('conversation_id', $demoConversationIds)
            ->delete();

        DB::table('conversations')
            ->whereIn('id', $demoConversationIds)
            ->delete();

        DB::table('message_device_reads')
            ->whereIn('chat_user_device_id', function ($query): void {
                $query->select('id')
                    ->from('chat_user_devices')
                    ->where('metadata->demo_seed', true);
            })
            ->delete();

        DB::table('chat_user_devices')
            ->where('metadata->demo_seed', true)
            ->delete();
    }

    /**
     * Create a demo conversation with participants.
     */
    private function createConversation(
        string $type,
        string $visibility,
        ?string $title,
        int $ownerId,
        int $createdBy,
        string $source,
        string $joinPolicy,
        array $participantUsers,
        ?int $createdFromConversationId = null,
        ?string $historyImportMode = null,
        ?Carbon $historyImportFromAt = null,
    ): int {
        $now = Carbon::now();

        $conversationId = DB::table('conversations')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'visibility' => $visibility,
            'title' => $title,
            'description' => $title ? 'Demo chat conversation generated for local development.' : null,
            'owner_id' => $ownerId,
            'created_by' => $createdBy,
            'created_from_conversation_id' => $createdFromConversationId,
            'source' => $source,
            'status' => 'active',
            'join_policy' => $joinPolicy,
            'history_import_mode' => $historyImportMode,
            'history_import_from_message_id' => null,
            'history_import_from_at' => $historyImportFromAt,
            'last_message_id' => null,
            'last_message_at' => null,
            'metadata' => json_encode([
                'demo_seed' => true,
                'seed_key' => 'chat_demo_seed_v1',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($participantUsers as $index => $user) {
            $isOwner = $user->id === $ownerId;
            $isFirstAdmin = $index === 1 && $type !== 'direct';

            DB::table('conversation_participants')->insert([
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'role' => $isOwner ? 'owner' : ($isFirstAdmin ? 'admin' : 'member'),
                'status' => 'active',
                'access_state' => 'full',
                'block_display_mode' => null,
                'can_invite' => $isOwner || $isFirstAdmin,
                'can_remove' => $isOwner || $isFirstAdmin,
                'can_send' => true,
                'can_attach' => true,
                'can_manage' => $isOwner,
                'can_moderate' => $isOwner || $isFirstAdmin,
                'blocked_reason' => null,
                'blocked_by' => null,
                'blocked_at' => null,
                'history_visibility_mode' => 'full',
                'history_visible_from_message_id' => null,
                'history_visible_from_at' => null,
                'history_visible_until_message_id' => null,
                'history_visible_until_at' => null,
                'joined_at' => $now,
                'left_at' => null,
                'removed_at' => null,
                'last_read_message_id' => null,
                'last_read_at' => null,
                'muted_until' => null,
                'metadata' => json_encode([
                    'demo_seed' => true,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $conversationId;
    }

    /**
     * Seed chat messages, deliveries and read receipts.
     */
    private function seedMessages(
        array $conversationIds,
        mixed $users,
        int $messagesCount,
        mixed $faker,
    ): void {
        $messagesPerConversation = (int) ceil($messagesCount / count($conversationIds));
        $now = Carbon::now();
        $createdMessageIds = [];

        foreach ($conversationIds as $conversationId) {
            $participants = DB::table('conversation_participants')
                ->where('conversation_id', $conversationId)
                ->where('status', 'active')
                ->get();

            if ($participants->isEmpty()) {
                continue;
            }

            $conversation = DB::table('conversations')
                ->where('id', $conversationId)
                ->first();

            $lastMessageId = null;
            $lastMessageAt = null;

            for ($i = 0; $i < $messagesPerConversation; $i++) {
                $senderParticipant = $participants->random();
                $sentAt = $now->copy()
                    ->subDays(rand(0, 14))
                    ->subMinutes(rand(0, 1440))
                    ->addSeconds($i);

                $isExternalConversation = $conversation?->source === 'api' || $conversation?->type === 'external';
                $isImportedConversation = $conversation?->created_from_conversation_id !== null && $i < 20;

                $messageId = DB::table('messages')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'conversation_id' => $conversationId,
                    'sender_id' => $isExternalConversation && rand(1, 5) === 1
                        ? null
                        : $senderParticipant->user_id,
                    'sender_type' => $isExternalConversation && rand(1, 5) === 1
                        ? 'external'
                        : 'user',
                    'external_id' => $isExternalConversation
                        ? 'demo-ext-' . $conversationId . '-' . $i
                        : null,
                    'reply_to_message_id' => $this->randomReplyToMessageId($createdMessageIds[$conversationId] ?? []),
                    'type' => $this->randomMessageType(),
                    'body' => $this->makeFakeMessageBody($faker),
                    'status' => 'sent',
                    'is_imported' => $isImportedConversation,
                    'imported_from_conversation_id' => $isImportedConversation
                        ? $conversation->created_from_conversation_id
                        : null,
                    'imported_from_message_id' => null,
                    'sent_at' => $sentAt,
                    'delivered_at' => $sentAt->copy()->addSeconds(rand(1, 20)),
                    'read_at' => rand(1, 100) <= 70
                        ? $sentAt->copy()->addMinutes(rand(1, 180))
                        : null,
                    'edited_at' => rand(1, 100) <= 5
                        ? $sentAt->copy()->addMinutes(rand(5, 90))
                        : null,
                    'deleted_at' => null,
                    'metadata' => json_encode([
                        'demo_seed' => true,
                        'seed_key' => 'chat_demo_seed_v1',
                    ]),
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]);

                $createdMessageIds[$conversationId][] = $messageId;

                $this->seedDeliveries($messageId, $conversationId, $participants, $sentAt);
                $this->seedReads($messageId, $conversationId, $participants, $sentAt);

                $lastMessageId = $messageId;
                $lastMessageAt = $sentAt;
            }

            if ($lastMessageId !== null) {
                DB::table('conversations')
                    ->where('id', $conversationId)
                    ->update([
                        'last_message_id' => $lastMessageId,
                        'last_message_at' => $lastMessageAt,
                        'updated_at' => Carbon::now(),
                    ]);

                foreach ($participants as $participant) {
                    DB::table('conversation_participants')
                        ->where('id', $participant->id)
                        ->update([
                            'last_read_message_id' => rand(1, 100) <= 75 ? $lastMessageId : null,
                            'last_read_at' => rand(1, 100) <= 75 ? $lastMessageAt : null,
                            'updated_at' => Carbon::now(),
                        ]);
                }
            }
        }
    }

    /**
     * Create delivery records for participants.
     */
    private function seedDeliveries(
        int $messageId,
        int $conversationId,
        mixed $participants,
        Carbon $sentAt,
    ): void {
        foreach ($participants as $participant) {
            $isFailed = rand(1, 100) <= 3;

            DB::table('message_deliveries')->insert([
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'user_id' => $participant->user_id,
                'external_recipient_id' => null,
                'recipient_type' => 'user',
                'status' => $isFailed ? 'failed' : 'delivered',
                'delivered_at' => $isFailed ? null : $sentAt->copy()->addSeconds(rand(1, 30)),
                'failed_at' => $isFailed ? $sentAt->copy()->addSeconds(rand(1, 30)) : null,
                'failure_reason' => $isFailed ? 'Demo delivery failure.' : null,
                'metadata' => json_encode([
                    'demo_seed' => true,
                ]),
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }
    }

    /**
     * Create read receipts for a random part of participants.
     */
    private function seedReads(
        int $messageId,
        int $conversationId,
        mixed $participants,
        Carbon $sentAt,
    ): void {
        foreach ($participants as $participant) {
            if (rand(1, 100) > 65) {
                continue;
            }

            DB::table('message_reads')->insert([
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'user_id' => $participant->user_id,
                'read_at' => $sentAt->copy()->addMinutes(rand(1, 240)),
                'read_source' => 'user',
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }
    }

    /**
     * Seed demo devices and device-level reads.
     *
     * WHY:
     * Device-level read API and UI need realistic per-device records.
     * We build them from existing aggregated message_reads to keep consistency.
     */
    private function seedDevicesAndDeviceReads(array $conversationIds): void
    {
        if (empty($conversationIds)) {
            return;
        }

        $participantUserIds = DB::table('conversation_participants')
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($participantUserIds->isEmpty()) {
            return;
        }

        $deviceProfiles = [
            ['type' => 'browser', 'name' => 'Web Browser', 'platform' => 'Windows', 'browser' => 'Chrome', 'app_version' => '1.0.0-web'],
            ['type' => 'mobile', 'name' => 'Mobile App', 'platform' => 'Android', 'browser' => null, 'app_version' => '1.0.0-mobile'],
            ['type' => 'desktop', 'name' => 'Desktop App', 'platform' => 'macOS', 'browser' => null, 'app_version' => '1.0.0-desktop'],
            ['type' => 'tablet', 'name' => 'Tablet App', 'platform' => 'iPadOS', 'browser' => 'Safari', 'app_version' => '1.0.0-tablet'],
        ];

        $devicesByUser = [];
        $now = Carbon::now();

        foreach ($participantUserIds as $userId) {
            $devicesCount = rand(1, 3);

            for ($index = 1; $index <= $devicesCount; $index++) {
                $profile = $deviceProfiles[($index - 1) % count($deviceProfiles)];
                $deviceKey = "demo-user-{$userId}-device-{$index}";
                $lastSeenAt = $now->copy()->subMinutes(rand(0, 720));

                DB::table('chat_user_devices')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'device_key' => $deviceKey,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'device_name' => "{$profile['name']} #{$index}",
                        'device_type' => $profile['type'],
                        'platform' => $profile['platform'],
                        'browser' => $profile['browser'],
                        'app_version' => $profile['app_version'],
                        'ip_address' => "10.0.{$userId}.{$index}",
                        'user_agent' => 'ChatDemoSeeder/' . $profile['type'],
                        'is_active' => true,
                        'last_seen_at' => $lastSeenAt,
                        'metadata' => json_encode([
                            'demo_seed' => true,
                        ]),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }

            $devicesByUser[$userId] = DB::table('chat_user_devices')
                ->where('user_id', $userId)
                ->where('metadata->demo_seed', true)
                ->get(['id', 'device_key', 'device_type', 'platform', 'browser']);
        }

        $reads = DB::table('message_reads')
            ->whereIn('conversation_id', $conversationIds)
            ->orderBy('id')
            ->get(['message_id', 'conversation_id', 'user_id', 'read_at', 'created_at']);

        foreach ($reads as $read) {
            $devices = $devicesByUser[$read->user_id] ?? collect();

            if ($devices->isEmpty()) {
                continue;
            }

            // Keep dataset realistic: not every aggregated read must exist on every device.
            $selectedDevices = $devices->filter(fn (): bool => rand(1, 100) <= 70)->values();

            if ($selectedDevices->isEmpty()) {
                $selectedDevices = collect([$devices->first()]);
            }

            foreach ($selectedDevices as $device) {
                $readAt = Carbon::parse($read->read_at ?? $read->created_at)
                    ->copy()
                    ->addSeconds(rand(0, 120));

                DB::table('message_device_reads')->updateOrInsert(
                    [
                        'message_id' => $read->message_id,
                        'chat_user_device_id' => $device->id,
                    ],
                    [
                        'conversation_id' => $read->conversation_id,
                        'user_id' => $read->user_id,
                        'device_key' => $device->device_key,
                        'device_type' => $device->device_type,
                        'platform' => $device->platform,
                        'browser' => $device->browser,
                        'read_at' => $readAt,
                        'metadata' => json_encode([
                            'demo_seed' => true,
                        ]),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }
    }

    /**
     * Randomly return a previous message ID for reply simulation.
     */
    private function randomReplyToMessageId(array $messageIds): ?int
    {
        if (count($messageIds) < 5 || rand(1, 100) > 12) {
            return null;
        }

        return $messageIds[array_rand($messageIds)];
    }

    /**
     * Pick a simple demo message type.
     */
    private function randomMessageType(): string
    {
        $types = ['text', 'text', 'text', 'text', 'system'];

        return $types[array_rand($types)];
    }

    /**
     * Generate fake message text.
     */
    private function makeFakeMessageBody(mixed $faker): string
    {
        $templates = [
            $faker->sentence(rand(6, 14)),
            $faker->paragraph(rand(1, 3)),
            'Demo message: ' . $faker->sentence(rand(5, 12)),
            'Please check this when you have time. ' . $faker->sentence(rand(4, 10)),
            'Status update: ' . $faker->sentence(rand(5, 12)),
        ];

        return $templates[array_rand($templates)];
    }
}
