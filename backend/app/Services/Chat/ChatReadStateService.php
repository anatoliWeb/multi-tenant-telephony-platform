<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageDeviceRead;
use App\Events\Chat\ChatMessageRead;
use App\Models\ChatUserDevice;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDeviceRead;
use App\Models\MessageRead;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatReadStateService
{
    public function __construct(
        protected ChatAccessService $accessService,
        protected ChatConversationQueryService $queryService,
        protected ChatWebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    public function registerOrUpdateDevice(User $user, array $payload): ChatUserDevice
    {
        /** @var ChatUserDevice $device */
        $device = ChatUserDevice::query()->updateOrCreate(
            [
                'tenant_id' => $this->resolveTenantId(),
                'user_id' => $user->id,
                'device_key' => $payload['device_key'],
            ],
            [
                'tenant_id' => $this->resolveTenantId(),
                'uuid' => (string) Str::uuid(),
                'device_name' => $payload['device_name'] ?? null,
                'device_type' => $payload['device_type'] ?? 'browser',
                'platform' => $payload['platform'] ?? null,
                'browser' => $payload['browser'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        if (blank($device->uuid)) {
            $device->uuid = (string) Str::uuid();
            $device->save();
        }

        return $device->fresh();
    }

    public function resolveOwnedDevice(User $user, string $deviceKey): ?ChatUserDevice
    {
        return ChatUserDevice::query()
            ->forCurrentTenant()
            ->where('user_id', $user->id)
            ->where('device_key', $deviceKey)
            ->first();
    }

    public function canMarkConversationRead(User $user, Conversation $conversation): bool
    {
        return $this->accessService->canViewMessages($user, $conversation);
    }

    public function canMarkMessageRead(User $user, Message $message): bool
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            return false;
        }

        return $this->queryService
            ->visibleMessagesFor($user, $conversation)
            ->whereKey($message->id)
            ->exists();
    }

    public function markMessageReadFromDevice(User $user, Message $message, ChatUserDevice $device): void
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            return;
        }

        if ($device->user_id !== $user->id) {
            return;
        }

        if (! $this->canMarkMessageRead($user, $message)) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($user, $message, $conversation, $device, $now): void {
            MessageDeviceRead::query()->updateOrCreate(
                [
                    'tenant_id' => $conversation->tenant_id,
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'chat_user_device_id' => $device->id,
                ],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                    'device_key' => $device->device_key,
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                    'browser' => $device->browser,
                    'read_at' => $now,
                    'metadata' => ['source' => 'device_api'],
                ]
            );

            MessageRead::query()->updateOrCreate(
                [
                    'tenant_id' => $conversation->tenant_id,
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                ],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                    'read_at' => $now,
                    'read_source' => 'device',
                ]
            );

            $this->syncParticipantLastReadFromMessage($user, $conversation, $message, $now);
        });

        event(new ChatMessageRead(
            conversationId: $conversation->id,
            payload: $this->buildReadRealtimePayload($user->id, $message->id, $conversation->id, $now)
        ));
        $this->webhookDeliveryService->queueEvent(
            'message.read',
            $this->buildReadWebhookPayload($user->id, $message, $conversation->id, $now, 'device')
        );

        event(new ChatMessageDeviceRead(
            conversationId: $conversation->id,
            payload: $this->buildDeviceReadRealtimePayload($user->id, $message->id, $conversation->id, $device, $now)
        ));
        $this->webhookDeliveryService->queueEvent(
            'message.device_read',
            $this->buildDeviceReadWebhookPayload($user->id, $message, $conversation->id, $device, $now)
        );
    }

    public function markConversationReadFromDevice(
        User $user,
        Conversation $conversation,
        ChatUserDevice $device,
        ?int $untilMessageId = null
    ): void {
        if ($device->user_id !== $user->id) {
            return;
        }

        if (! $this->canMarkConversationRead($user, $conversation)) {
            return;
        }

        $query = $this->queryService->visibleMessagesFor($user, $conversation)
            ->where(function (Builder $senderQuery) use ($user): void {
                $senderQuery
                    ->whereNull('sender_id')
                    ->orWhere('sender_id', '!=', $user->id);
            });

        if ($untilMessageId !== null) {
            $query->where('id', '<=', $untilMessageId);
        }

        $messages = $query->get();
        if ($messages->isEmpty()) {
            return;
        }

        $now = now();
        $lastMessageId = DB::transaction(function () use ($user, $conversation, $device, $messages, $now): ?int {
            foreach ($messages as $message) {
                MessageDeviceRead::query()->updateOrCreate(
                    [
                        'tenant_id' => $conversation->tenant_id,
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                        'chat_user_device_id' => $device->id,
                    ],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                        'device_key' => $device->device_key,
                        'device_type' => $device->device_type,
                        'platform' => $device->platform,
                        'browser' => $device->browser,
                        'read_at' => $now,
                        'metadata' => ['source' => 'device_api'],
                    ]
                );

                MessageRead::query()->updateOrCreate(
                    [
                        'tenant_id' => $conversation->tenant_id,
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                    ],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                        'read_at' => $now,
                        'read_source' => 'device',
                    ]
                );
            }

            $lastMessage = $messages->sortByDesc('id')->first();
            if ($lastMessage) {
                $this->syncParticipantLastReadFromMessage($user, $conversation, $lastMessage, $now);
                return (int) $lastMessage->id;
            }

            return null;
        });

        if ($lastMessageId === null) {
            return;
        }

        event(new ChatMessageRead(
            conversationId: $conversation->id,
            payload: $this->buildReadRealtimePayload($user->id, $lastMessageId, $conversation->id, $now)
        ));
        $lastMessage = Message::query()
            ->forCurrentTenant()
            ->find($lastMessageId);
        if ($lastMessage) {
            $this->webhookDeliveryService->queueEvent(
                'message.read',
                $this->buildReadWebhookPayload($user->id, $lastMessage, $conversation->id, $now, 'conversation_device')
            );
        }

        event(new ChatMessageDeviceRead(
            conversationId: $conversation->id,
            payload: $this->buildDeviceReadRealtimePayload($user->id, $lastMessageId, $conversation->id, $device, $now)
        ));
        if ($lastMessage) {
            $this->webhookDeliveryService->queueEvent(
                'message.device_read',
                $this->buildDeviceReadWebhookPayload($user->id, $lastMessage, $conversation->id, $device, $now)
            );
        }
    }

    public function syncAggregatedReadState(User $user, Conversation $conversation): void
    {
        $latestRead = MessageRead::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->orderByDesc('message_id')
            ->first();

        if (! $latestRead) {
            return;
        }

            $this->syncParticipantLastReadFromMessage(
            $user,
            $conversation,
            Message::query()->forCurrentTenant()->findOrFail($latestRead->message_id),
            $latestRead->read_at
        );
    }

    private function syncParticipantLastReadFromMessage(
        User $user,
        Conversation $conversation,
        Message $message,
        Carbon $readAt
    ): void {
        /** @var ConversationParticipant|null $participant */
        $participant = $this->accessService->getParticipant($conversation, $user);
        if (! $participant) {
            return;
        }

        $currentLastId = (int) ($participant->last_read_message_id ?? 0);
        if ($message->id > $currentLastId) {
            $participant->last_read_message_id = $message->id;
        }

        if ($participant->last_read_at === null || $readAt->gt($participant->last_read_at)) {
            $participant->last_read_at = $readAt;
        }

        $participant->save();
    }

    private function resolveTenantId(): string
    {
        $tenantId = app(TenantContext::class)->tenantId();
        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        if (app()->runningUnitTests() || app()->runningInConsole()) {
            return TenantBootstrapService::DEFAULT_TENANT_UUID;
        }

        throw new \RuntimeException('Tenant context is required for chat read-state operations.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReadRealtimePayload(
        int $userId,
        int $messageId,
        int $conversationId,
        Carbon $readAt
    ): array {
        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'read_at' => $readAt->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeviceReadRealtimePayload(
        int $userId,
        int $messageId,
        int $conversationId,
        ChatUserDevice $device,
        Carbon $readAt
    ): array {
        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'device_id' => $device->id,
            'device_type' => $device->device_type,
            'read_at' => $readAt->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReadWebhookPayload(
        int $userId,
        Message $message,
        int $conversationId,
        Carbon $readAt,
        string $source
    ): array {
        return [
            'event' => 'message.read',
            'conversation_id' => $conversationId,
            'message_id' => $message->id,
            'message_uuid' => $message->uuid,
            'user_id' => $userId,
            'read_at' => $readAt->toISOString(),
            'read_source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeviceReadWebhookPayload(
        int $userId,
        Message $message,
        int $conversationId,
        ChatUserDevice $device,
        Carbon $readAt
    ): array {
        return [
            'event' => 'message.device_read',
            'conversation_id' => $conversationId,
            'message_id' => $message->id,
            'message_uuid' => $message->uuid,
            'user_id' => $userId,
            'read_at' => $readAt->toISOString(),
            'device_type' => $device->device_type,
        ];
    }
}
