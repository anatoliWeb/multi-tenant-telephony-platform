<?php

namespace App\Services\Chat;

class ChatWebhookSigningService
{
    /**
     * @return array{signature:string,timestamp:int}
     */
    public function signPayload(string $payload, string $secret, ?int $timestamp = null): array
    {
        $ts = $timestamp ?? now()->timestamp;
        $algo = (string) config('chat.webhooks.signing_algo', 'sha256');
        $signaturePayload = $this->buildSignaturePayload($payload, $ts);
        $hash = hash_hmac($algo, $signaturePayload, $secret);

        return [
            'signature' => 'v1='.$hash,
            'timestamp' => $ts,
        ];
    }

    public function verifySignature(string $payload, string $secret, string $signature, int $timestamp): bool
    {
        $tolerance = max((int) config('chat.webhooks.tolerance_seconds', 300), 1);
        if (abs(now()->timestamp - $timestamp) > $tolerance) {
            return false;
        }

        $expected = $this->signPayload($payload, $secret, $timestamp)['signature'];

        return hash_equals($expected, $signature);
    }

    public function buildSignaturePayload(string $payload, int $timestamp): string
    {
        return $timestamp.'.'.$payload;
    }
}

