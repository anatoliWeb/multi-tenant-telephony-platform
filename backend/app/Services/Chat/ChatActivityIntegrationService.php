<?php

namespace App\Services\Chat;

use App\Models\ChatModerationLog;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatActivityIntegrationService
{
    public function __construct(
        private readonly ActivityService $activityService,
    ) {
    }

    public function shouldRecord(ChatModerationLog $log): bool
    {
        if (! (bool) config('chat.activity_integration.enabled', true)) {
            return false;
        }

        $actions = (array) config('chat.activity_integration.actions', []);

        return in_array($log->action, $actions, true);
    }

    public function recordFromModerationLog(ChatModerationLog $log): void
    {
        if (! $this->shouldRecord($log)) {
            return;
        }

        try {
            $this->activityService->log(
                userId: $log->actor_id,
                action: $log->action,
                description: $this->buildDescription($log),
                meta: $this->buildActivityPayload($log),
            );
        } catch (Throwable $exception) {
            Log::warning('Chat activity integration failed', [
                'chat_moderation_log_id' => $log->id,
                'action' => $log->action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildActivityPayload(ChatModerationLog $log): array
    {
        $payload = [
            'source' => 'chat',
            'module' => 'chat',
            'category' => 'chat',
            'chat_moderation_log_id' => $log->id,
            'action' => $log->action,
            'conversation_id' => $log->conversation_id,
            'message_id' => $log->message_id,
            'participant_id' => data_get($log->metadata, 'participant_id'),
            'target_user_id' => $log->target_user_id,
        ];

        $safeMetadata = $this->safeMetadataSubset(is_array($log->metadata) ? $log->metadata : []);
        if ($safeMetadata !== []) {
            $payload['chat'] = $safeMetadata;
        }

        return $payload;
    }

    private function buildDescription(ChatModerationLog $log): string
    {
        return sprintf('Chat moderation action: %s', $log->action);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function safeMetadataSubset(array $metadata): array
    {
        $allowedKeys = [
            'source',
            'conversation_source',
            'conversation_type',
            'conversation_status',
            'message_type',
            'status',
            'event_type',
            'attempts',
            'max_attempts',
            'response_status',
            'signal_count',
            'signals',
            'import_mode',
            'imported_messages_count',
            'imported_attachments_count',
            'reason',
            'old_access_state',
            'new_access_state',
            'old_status',
            'new_status',
            'old_visibility',
            'new_visibility',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $metadata)) {
                $safe[$key] = $metadata[$key];
            }
        }

        return $safe;
    }
}

