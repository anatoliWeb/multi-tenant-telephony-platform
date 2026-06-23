<?php

namespace App\Services\Chat;

use App\Models\ChatWebhookEndpoint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ChatWebhookSecretRotationService
{
    public function __construct(
        protected ChatWebhookSigningService $signingService,
    ) {
    }

    /**
     * @return array{plain_secret:string,rotated_at:string,previous_secret_expires_at:?string}
     */
    public function rotateSecret(ChatWebhookEndpoint $endpoint, User $actor, ?int $graceSeconds = null): array
    {
        $newSecret = Str::random(64);
        $oldSecret = (string) $endpoint->secret;
        $now = now();
        $ttl = $graceSeconds ?? $this->getRotationGraceSeconds();
        $ttl = max(0, (int) $ttl);
        $expiresAt = $ttl > 0 ? $now->copy()->addSeconds($ttl) : null;

        $metadata = (array) ($endpoint->metadata ?? []);
        $metadata['webhook_secret_rotation'] = [
            'previous_secret_encrypted' => $oldSecret !== '' ? encrypt($oldSecret) : null,
            'rotated_at' => $now->toISOString(),
            'previous_secret_expires_at' => $expiresAt?->toISOString(),
            'rotated_by' => $actor->id,
        ];

        $endpoint->secret = $newSecret;
        $endpoint->metadata = $metadata;
        $endpoint->save();

        return [
            'plain_secret' => $newSecret,
            'rotated_at' => $now->toISOString(),
            'previous_secret_expires_at' => $expiresAt?->toISOString(),
        ];
    }

    public function verifyWithRotation(
        ChatWebhookEndpoint $endpoint,
        string $payload,
        string $signature,
        int $timestamp
    ): bool {
        $current = (string) $endpoint->secret;
        if ($current !== '' && $this->signingService->verifySignature($payload, $current, $signature, $timestamp)) {
            return true;
        }

        if (! $this->previousSecretStillValid($endpoint)) {
            return false;
        }

        $rotation = (array) data_get($endpoint->metadata, 'webhook_secret_rotation', []);
        $encrypted = (string) ($rotation['previous_secret_encrypted'] ?? '');
        if ($encrypted === '') {
            return false;
        }

        try {
            $previousSecret = (string) decrypt($encrypted);
        } catch (\Throwable) {
            return false;
        }

        return $previousSecret !== ''
            && $this->signingService->verifySignature($payload, $previousSecret, $signature, $timestamp);
    }

    public function getRotationGraceSeconds(): int
    {
        return max(0, (int) config('chat.webhooks.secret_rotation_grace_seconds', 86400));
    }

    public function previousSecretStillValid(ChatWebhookEndpoint $endpoint): bool
    {
        $rotation = (array) data_get($endpoint->metadata, 'webhook_secret_rotation', []);
        $expiresAt = $rotation['previous_secret_expires_at'] ?? null;
        if ($expiresAt === null) {
            return false;
        }

        try {
            $expiry = Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return false;
        }

        return now()->lte($expiry);
    }
}

