<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\IncomingChatWebhookRequest;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatWebhookEndpoint;
use App\Services\Chat\ChatWebhookReplayProtectionService;
use App\Services\Chat\ChatWebhookSecretRotationService;
use App\Services\Chat\ExternalChatMessageService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ChatIncomingWebhookController extends BaseController
{
    public function __construct(
        protected ChatWebhookSecretRotationService $secretRotationService,
        protected ChatWebhookReplayProtectionService $replayProtectionService,
        protected ExternalChatMessageService $externalChatMessageService,
        protected TenantContext $tenantContext,
    ) {
    }

    public function handle(ChatWebhookEndpoint $endpoint, IncomingChatWebhookRequest $request): JsonResponse
    {
        if (! $endpoint->is_active || $endpoint->status !== 'active') {
            abort(403, 'Webhook endpoint is inactive.');
        }

        $signatureHeader = (string) config('chat.webhooks.signature_header', 'X-Chat-Signature');
        $timestampHeader = (string) config('chat.webhooks.timestamp_header', 'X-Chat-Timestamp');
        $signature = (string) $request->header($signatureHeader, '');
        $timestampRaw = (string) $request->header($timestampHeader, '');

        if ($signature === '' || $timestampRaw === '' || ! ctype_digit($timestampRaw)) {
            abort(403, 'Missing or invalid webhook signature headers.');
        }

        $timestamp = (int) $timestampRaw;
        $rawPayload = (string) $request->getContent();
        if (! $this->secretRotationService->verifyWithRotation($endpoint, $rawPayload, $signature, $timestamp)) {
            abort(403, 'Invalid webhook signature.');
        }
        if (! $this->replayProtectionService->checkAndRemember($endpoint, $timestamp, $signature, $rawPayload)) {
            abort(409, 'Webhook replay detected.');
        }

        $validated = $request->validated();
        if (! in_array((string) $validated['event'], (array) ($endpoint->events ?? []), true)) {
            throw ValidationException::withMessages([
                'event' => ['Webhook endpoint is not subscribed to this event.'],
            ]);
        }

        $this->tenantContext->setTenant($endpoint->tenant);

        $result = $this->externalChatMessageService->sendExternalWebhookMessage($endpoint, $validated);
        $message = $result['message'];

        $payload = (new ChatMessageResource($message))->resolve();

        return response()->json([
            'success' => true,
            'message' => ($result['idempotent'] ?? false) ? 'Webhook message already exists' : 'Webhook message created',
            'data' => $payload,
            'meta' => [
                'idempotent' => (bool) ($result['idempotent'] ?? false),
            ],
        ], ($result['idempotent'] ?? false) ? 200 : 201);
    }
}
