<?php

namespace App\Services\Chat;

use App\Models\ChatWebhookEndpoint;
use Illuminate\Support\Facades\Cache;

class ChatWebhookReplayProtectionService
{
    public function isEnabled(): bool
    {
        return (bool) config('chat.webhooks.replay_protection.enabled', true);
    }

    public function ttlSeconds(): int
    {
        $configured = (int) config('chat.webhooks.replay_protection.ttl_seconds', 300);
        $fallback = max((int) config('chat.webhooks.tolerance_seconds', 300), 1);

        return max($configured, 1) ?: $fallback;
    }

    public function fingerprint(ChatWebhookEndpoint $endpoint, int $timestamp, string $signature, string $payload): string
    {
        $normalized = implode('|', [
            'endpoint:'.(string) $endpoint->id,
            'ts:'.$timestamp,
            'sig:'.hash('sha256', $signature),
            'payload:'.hash('sha256', $payload),
        ]);

        return hash('sha256', $normalized);
    }

    public function checkAndRemember(ChatWebhookEndpoint $endpoint, int $timestamp, string $signature, string $payload): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $fingerprint = $this->fingerprint($endpoint, $timestamp, $signature, $payload);
        $key = 'chat:webhooks:replay:'.$fingerprint;

        return Cache::add($key, 1, now()->addSeconds($this->ttlSeconds()));
    }
}

