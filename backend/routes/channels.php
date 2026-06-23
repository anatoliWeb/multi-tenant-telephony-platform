<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Monitoring\RealtimeLogService;
use App\Events\Chat\ChatUserJoinedConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('test-broadcast', static fn () => true);

Broadcast::channel('system.notifications', static function (User $user): bool {
    $allowed = $user->hasPermission('notifications.view');
    if (! $allowed) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'system.notifications',
            'channel_type' => 'private',
            'user_id' => $user->id,
            'reason' => 'missing_permission',
        ]);
    }

    return $allowed;
});

Broadcast::channel('activity.stream', static function (User $user): bool {
    $allowed = $user->hasPermission('activity.view');
    if (! $allowed) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'activity.stream',
            'channel_type' => 'private',
            'user_id' => $user->id,
            'reason' => 'missing_permission',
        ]);
    }

    return $allowed;
});

Broadcast::channel('notifications.user.{userId}', static function (User $user, int $userId): bool {
    $allowed = $user->id === $userId;
    if (! $allowed) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'notifications.user.{userId}',
            'channel_type' => 'private',
            'user_id' => $user->id,
            'target_user_id' => $userId,
            'reason' => 'owner_mismatch',
        ]);
    }

    return $allowed;
});

Broadcast::channel('chat.conversation.{conversationId}', static function (User $user, int $conversationId): bool {
    $conversation = Conversation::query()->find($conversationId);
    if (! $conversation) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'chat.conversation.{conversationId}',
            'channel_type' => 'private',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'conversation_not_found',
        ]);
        return false;
    }

    /** @var ChatAccessService $chatAccessService */
    $chatAccessService = app(ChatAccessService::class);

    $allowed = $chatAccessService->canViewMessages($user, $conversation);
    if (! $allowed) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'chat.conversation.{conversationId}',
            'channel_type' => 'private',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'access_denied',
        ]);
    }

    return $allowed;
});

Broadcast::channel('presence-online', static function (User $user): array {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

Broadcast::channel('presence-dashboard', static function (User $user): array {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

Broadcast::channel('presence-page.{page}', static function (User $user, string $page): array|bool {
    if (! preg_match('/^[a-z0-9._:-]{1,64}$/', $page)) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'presence-page.{page}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'reason' => 'invalid_page',
        ]);
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

Broadcast::channel('presence-typing.{context}', static function (User $user, string $context): array|bool {
    if (! preg_match('/^[a-z0-9._:-]{1,64}$/', $context)) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'presence-typing.{context}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'reason' => 'invalid_context',
        ]);
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

Broadcast::channel('presence-chat.{conversationId}', static function (User $user, int $conversationId): array|bool {
    $conversation = Conversation::query()->find($conversationId);
    if (! $conversation) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'presence-chat.{conversationId}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'conversation_not_found',
        ]);
        return false;
    }

    /** @var ChatPresenceService $chatPresenceService */
    $chatPresenceService = app(ChatPresenceService::class);
    if (! $chatPresenceService->canJoinPresence($user, $conversation)) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'presence-chat.{conversationId}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'presence_denied',
        ]);
        return false;
    }

    $device = $chatPresenceService->markUserPresenceSeen($user, request()->input('device_key'));
    $payload = $chatPresenceService->buildPresencePayload($user, $conversation, $device);

    event(new ChatUserJoinedConversation(
        conversationId: $conversation->id,
        payload: [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'joined_at' => now()->toISOString(),
        ]
    ));

    return $payload;
});

// Backward compatibility alias for older frontend builds that still subscribe to chat.{conversationId}
Broadcast::channel('chat.{conversationId}', static function (User $user, int $conversationId): array|bool {
    $conversation = Conversation::query()->find($conversationId);
    if (! $conversation) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'chat.{conversationId}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'conversation_not_found',
        ]);
        return false;
    }

    /** @var ChatPresenceService $chatPresenceService */
    $chatPresenceService = app(ChatPresenceService::class);
    if (! $chatPresenceService->canJoinPresence($user, $conversation)) {
        app(RealtimeLogService::class)->logChannelDenied([
            'channel_name' => 'chat.{conversationId}',
            'channel_type' => 'presence',
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'reason' => 'presence_denied',
        ]);
        return false;
    }

    $device = $chatPresenceService->markUserPresenceSeen($user, request()->input('device_key'));
    $payload = $chatPresenceService->buildPresencePayload($user, $conversation, $device);

    event(new ChatUserJoinedConversation(
        conversationId: $conversation->id,
        payload: [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'joined_at' => now()->toISOString(),
        ]
    ));

    return $payload;
});
