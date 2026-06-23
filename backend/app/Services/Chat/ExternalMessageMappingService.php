<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\ExternalMessageMapping;
use App\Models\Message;

class ExternalMessageMappingService
{
    public function mapExternalMessage(
        Conversation $conversation,
        Message $message,
        string $provider,
        string $externalMessageId,
        array $payload = []
    ): ExternalMessageMapping {
        $safeMetadata = [
            'source' => data_get($payload, 'source'),
            'module' => data_get($payload, 'module'),
            'direction' => data_get($payload, 'direction'),
        ];

        return ExternalMessageMapping::query()->updateOrCreate(
            [
                'provider' => $provider,
                'external_id' => $externalMessageId,
            ],
            [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'external_conversation_id' => data_get($payload, 'external_conversation_id'),
                'direction' => (string) (data_get($payload, 'direction') ?? 'outbound'),
                'idempotency_key' => data_get($payload, 'idempotency_key'),
                'payload_hash' => data_get($payload, 'payload_hash'),
                'metadata' => array_filter($safeMetadata, static fn ($v) => $v !== null),
            ]
        );
    }

    public function findByExternalId(string $provider, string $externalMessageId): ?ExternalMessageMapping
    {
        return ExternalMessageMapping::query()
            ->where('provider', $provider)
            ->where('external_id', $externalMessageId)
            ->first();
    }
}

