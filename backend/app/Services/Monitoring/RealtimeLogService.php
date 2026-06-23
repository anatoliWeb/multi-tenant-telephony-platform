<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Log;

class RealtimeLogService
{
    public function __construct(
        private readonly StructuredLogContextService $structuredLogs
    ) {
    }

    /**
     * Log denied channel authorization attempts with sanitized structured context.
     *
     * High-volume successful authorizations are intentionally not logged here to keep
     * realtime observability signal-focused on security-relevant denials.
     *
     * @param array<string, mixed> $context
     */
    public function logChannelDenied(array $context): void
    {
        if (! $this->isEnabled() || ! $this->logChannelAuthFailures()) {
            return;
        }

        Log::warning('realtime.channel.auth.denied', $this->sanitizeContext($context + [
            'category' => 'realtime',
            'module' => 'broadcast',
            'status' => 'denied',
        ]));
    }

    /**
     * Log realtime broadcast failures using sanitized context only.
     *
     * Failure events are kept at error level because they can affect delivery guarantees.
     *
     * @param array<string, mixed> $context
     */
    public function logBroadcastFailed(array $context): void
    {
        if (! $this->isEnabled() || ! $this->logBroadcastFailures()) {
            return;
        }

        Log::error('realtime.broadcast.failed', $this->sanitizeContext($context + [
            'category' => 'realtime',
            'module' => 'broadcast',
            'status' => 'failed',
        ]));
    }

    public function isEnabled(): bool
    {
        return (bool) config('logging.realtime.enabled', true);
    }

    public function logChannelAuthFailures(): bool
    {
        return (bool) config('logging.realtime.channel_auth_failures', true);
    }

    public function logBroadcastFailures(): bool
    {
        return (bool) config('logging.realtime.broadcast_failures', true);
    }

    /**
     * Apply shared structured log sanitization before writing realtime logs.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitizeContext(array $context): array
    {
        return $this->structuredLogs->sanitize($context);
    }
}
