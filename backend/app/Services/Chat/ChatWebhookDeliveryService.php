<?php

namespace App\Services\Chat;

use App\Jobs\Chat\DeliverChatWebhookJob;
use App\Models\ChatWebhookDelivery;
use App\Models\ChatWebhookEndpoint;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ChatWebhookDeliveryService
{
    public function __construct(
        protected ChatModerationService $moderationService,
    ) {
    }

    /**
     * Persist delivery row for an endpoint and event payload.
     *
     * Payload is stored as-is for signed delivery; sanitization for logs is handled in
     * queue logging paths to avoid leaking secrets in operational logs.
     *
     * @param array<string, mixed> $payload
     */
    public function createDelivery(ChatWebhookEndpoint $endpoint, string $eventType, array $payload): ChatWebhookDelivery
    {
        $delivery = ChatWebhookDelivery::query()->create([
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->id,
            'conversation_id' => data_get($payload, 'conversation_id'),
            'message_id' => data_get($payload, 'message_id'),
            'event' => $eventType,
            'delivery_uuid' => (string) Str::uuid(),
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'signature' => null,
            'metadata' => [
                'source' => 'chat_webhook_delivery_service',
            ],
        ]);

        $this->moderationService->logWebhookDeliveryCreated($delivery, [
            'source' => 'chat_webhook_delivery_service',
        ]);

        return $delivery;
    }

    public function markAttempted(ChatWebhookDelivery $delivery, ?int $statusCode = null, ?string $error = null): ChatWebhookDelivery
    {
        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->response_status = $statusCode;
        $delivery->error_message = $error !== null ? mb_substr($error, 0, 65535) : null;
        $delivery->status = $error === null ? 'sent' : 'failed';
        $delivery->sent_at = $error === null ? now() : $delivery->sent_at;
        $delivery->failed_at = $error !== null ? now() : null;
        $delivery->save();

        return $delivery->fresh();
    }

    /**
     * Transition delivery to retrying/failed state according to max attempts policy.
     */
    public function scheduleRetry(ChatWebhookDelivery $delivery): ChatWebhookDelivery
    {
        $maxAttempts = max((int) config('chat.webhooks.max_attempts', 5), 1);
        $currentAttempts = (int) $delivery->attempts;

        if ($currentAttempts >= $maxAttempts) {
            $delivery->status = 'failed';
            $delivery->next_retry_at = null;
            $delivery->failed_at = now();
            $delivery->save();
            $fresh = $delivery->fresh();
            $this->moderationService->logWebhookDeliveryFailed($fresh, [
                'max_attempts' => $maxAttempts,
                'error_summary' => $this->sanitizeErrorSummary($fresh->error_message),
            ]);

            return $fresh;
        }

        $delivery->status = 'retrying';
        $delivery->next_retry_at = $this->calculateNextAttemptAt($currentAttempts + 1);
        $delivery->save();
        $fresh = $delivery->fresh();
        $this->moderationService->logWebhookDeliveryRetrying($fresh, [
            'max_attempts' => $maxAttempts,
            'error_summary' => $this->sanitizeErrorSummary($fresh->error_message),
        ]);

        return $fresh;
    }

    /**
     * Calculate next retry timestamp using configured bounded backoff sequence.
     */
    public function calculateNextAttemptAt(int $attempts): ?Carbon
    {
        $backoff = (array) config('chat.webhooks.retry_backoff_seconds', [60, 300, 900, 3600]);
        $index = max($attempts - 1, 0);
        $seconds = $backoff[$index] ?? end($backoff);
        if (! is_int($seconds)) {
            $seconds = (int) $seconds;
        }
        if ($seconds <= 0) {
            return null;
        }

        return now()->addSeconds($seconds);
    }

    /**
     * Queue webhook job dispatch on the dedicated webhooks queue.
     */
    public function dispatchDelivery(ChatWebhookDelivery $delivery): void
    {
        DeliverChatWebhookJob::dispatch($delivery->id);
    }

    public function markSucceeded(
        ChatWebhookDelivery $delivery,
        int $statusCode,
        ?array $responseBody = null
    ): ChatWebhookDelivery {
        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->response_status = $statusCode;
        $delivery->response_body = $responseBody;
        $delivery->status = 'sent';
        $delivery->sent_at = now();
        $delivery->failed_at = null;
        $delivery->next_retry_at = null;
        $delivery->error_message = null;
        $delivery->save();
        $fresh = $delivery->fresh();
        $this->moderationService->logWebhookDeliverySent($fresh, [
            'response_status' => $statusCode,
            'max_attempts' => max((int) config('chat.webhooks.max_attempts', 5), 1),
        ]);

        return $fresh;
    }

    public function markFailed(ChatWebhookDelivery $delivery, ?string $error = null): ChatWebhookDelivery
    {
        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->status = 'failed';
        $delivery->failed_at = now();
        $delivery->error_message = $error !== null ? mb_substr($error, 0, 65535) : null;
        $delivery->save();
        $fresh = $delivery->fresh();

        $maxAttempts = max((int) config('chat.webhooks.max_attempts', 5), 1);
        if ((int) $fresh->attempts >= $maxAttempts) {
            $this->moderationService->logWebhookDeliveryFailed($fresh, [
                'max_attempts' => $maxAttempts,
                'error_summary' => $this->sanitizeErrorSummary($fresh->error_message),
            ]);
        }

        return $fresh;
    }

    public function markCancelled(ChatWebhookDelivery $delivery, ?string $reason = null): ChatWebhookDelivery
    {
        $delivery->status = 'cancelled';
        $delivery->next_retry_at = null;
        $delivery->error_message = $reason !== null ? mb_substr($reason, 0, 65535) : null;
        $delivery->save();
        $fresh = $delivery->fresh();
        $this->moderationService->logWebhookDeliveryCancelled($fresh, [
            'cancelled_reason' => $this->sanitizeErrorSummary($fresh->error_message),
            'max_attempts' => max((int) config('chat.webhooks.max_attempts', 5), 1),
        ]);

        return $fresh;
    }

    /**
     * Queue one webhook delivery per active endpoint subscribed to event type.
     *
     * @param array<string, mixed> $payload
     */
    public function queueEvent(string $eventType, array $payload): int
    {
        $endpoints = ChatWebhookEndpoint::query()
            ->forCurrentTenant()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereJsonContains('events', $eventType)
            ->get();

        $count = 0;
        foreach ($endpoints as $endpoint) {
            $delivery = $this->createDelivery($endpoint, $eventType, $payload);
            $this->dispatchDelivery($delivery);
            $count++;
        }

        return $count;
    }

    private function sanitizeErrorSummary(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        return mb_substr(trim($error), 0, 255);
    }
}
