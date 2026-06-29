<?php

namespace App\Services\CallLogs;

use App\Enums\CallLogs\CallEventType;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\CallEvent;
use App\Models\CallLog;
use App\Services\Monitoring\StructuredLogContextService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallEventService
{
    public function __construct(
        private readonly StructuredLogContextService $structuredLogContextService,
        private readonly CallLifecycleService $callLifecycleService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        CallLog $callLog,
        string $providerId,
        string $providerEventId,
        CallEventType $type,
        array $payload = [],
        ?CarbonImmutable $occurredAt = null,
        ?int $sequence = null
    ): CallEvent {
        $occurredAt ??= CarbonImmutable::now();
        $sanitizedPayload = $this->sanitizePayload($payload);

        return DB::transaction(function () use ($callLog, $providerId, $providerEventId, $type, $sanitizedPayload, $occurredAt, $sequence): CallEvent {
            /** @var CallEvent|null $existing */
            $existing = CallEvent::query()
                ->forTenant($callLog->tenant_id)
                ->where('provider_id', $providerId)
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CallEvent) {
                if ($this->eventSignature($existing) !== $this->eventSignatureData($type, $occurredAt, $sequence, $sanitizedPayload)) {
                    throw new TelephonyConflictException('Provider event identity already exists with conflicting payload.');
                }

                return $existing;
            }

            $event = CallEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $callLog->tenant_id,
                'call_log_id' => $callLog->getKey(),
                'provider_event_id' => $providerEventId,
                'provider_id' => $providerId,
                'type' => $type->value,
                'occurred_at' => $occurredAt,
                'sequence' => $sequence,
                'payload' => $sanitizedPayload,
                'created_at' => now(),
            ]);

            $this->callLifecycleService->applyEvent($callLog->refresh(), $type, $sanitizedPayload, $occurredAt);

            return $event;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = $this->structuredLogContextService->sanitize($payload);

        return $this->trimPayload($sanitized);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function trimPayload(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $result[(string) $key] = $this->trimPayload($value);
                continue;
            }

            if (is_string($value)) {
                $result[(string) $key] = mb_substr($value, 0, 255);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    private function eventSignature(CallEvent $event): string
    {
        return $this->eventSignatureData(
            $event->type instanceof CallEventType ? $event->type : CallEventType::from((string) $event->type),
            $event->occurred_at ? CarbonImmutable::parse($event->occurred_at) : null,
            $event->sequence,
            is_array($event->payload) ? $event->payload : []
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function eventSignatureData(CallEventType $type, ?CarbonImmutable $occurredAt, ?int $sequence, array $payload): string
    {
        return hash('sha256', json_encode([
            'type' => $type->value,
            'occurred_at' => $occurredAt?->toISOString(),
            'sequence' => $sequence,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }
}
