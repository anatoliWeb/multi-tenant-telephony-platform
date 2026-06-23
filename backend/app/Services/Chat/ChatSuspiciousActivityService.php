<?php

namespace App\Services\Chat;

use App\Models\ChatModerationLog;
use App\Models\Message;
use App\Models\User;

class ChatSuspiciousActivityService
{
    public function __construct(
        protected ChatModerationService $chatModerationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectMessageCreated(Message $message, ?User $actor = null): array
    {
        if (! (bool) config('chat.suspicious_activity.enabled', true)) {
            return [
                'enabled' => false,
                'logged' => false,
                'signals' => [],
            ];
        }

        $signals = $this->detectMessageSignals($message);
        if ($signals === []) {
            return [
                'enabled' => true,
                'logged' => false,
                'signals' => [],
            ];
        }

        $this->logSignalsIfNeeded($message, $actor, $signals);

        return [
            'enabled' => true,
            'logged' => true,
            'signals' => $signals,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function detectMessageSignals(Message $message): array
    {
        $signals = [];
        $body = (string) ($message->body ?? '');
        $length = mb_strlen($body);
        $maxLength = max(1, (int) config('chat.suspicious_activity.max_message_length', 5000));

        if ($length > $maxLength) {
            $signals[] = 'too_long_message';
        }

        if ($this->hasRepeatedPattern($body)) {
            $signals[] = 'repeated_message_pattern';
        }

        if ($this->hasSuspiciousLinkPlaceholder($body)) {
            $signals[] = 'suspicious_link_placeholder';
        }

        return array_values(array_unique($signals));
    }

    /**
     * @param array<int, string> $signals
     */
    public function logSignalsIfNeeded(Message $message, ?User $actor, array $signals): void
    {
        $existing = ChatModerationLog::query()
            ->where('action', 'suspicious.message_activity')
            ->where('message_id', $message->id)
            ->exists();

        if ($existing) {
            return;
        }

        $attachmentCount = (int) ($message->attachments_count ?? 0);
        $metadata = [
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'sender_id' => $message->sender_id,
            'message_type' => $message->type,
            'sender_type' => $message->sender_type,
            'source' => 'suspicious_activity_placeholder',
            'signals' => $signals,
            'signal_count' => count($signals),
            'length_bucket' => $this->lengthBucket(mb_strlen((string) ($message->body ?? ''))),
            'attachment_count' => $attachmentCount,
            'log_only' => (bool) config('chat.suspicious_activity.log_only', true),
        ];

        $this->chatModerationService->logSuspiciousMessageActivity($actor, $message, $metadata);
    }

    private function hasRepeatedPattern(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        return preg_match('/(.)\1{15,}/u', $body) === 1;
    }

    private function hasSuspiciousLinkPlaceholder(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        return str_contains($body, 'http://')
            || str_contains($body, 'https://')
            || str_contains($body, 'www.');
    }

    private function lengthBucket(int $length): string
    {
        return match (true) {
            $length < 256 => '0-255',
            $length < 1024 => '256-1023',
            $length < 5000 => '1024-4999',
            default => '5000+',
        };
    }
}

