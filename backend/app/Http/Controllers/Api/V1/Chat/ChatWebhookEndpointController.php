<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\StoreChatWebhookEndpointRequest;
use App\Http\Requests\Api\UpdateChatWebhookEndpointRequest;
use App\Http\Resources\Chat\ChatWebhookEndpointResource;
use App\Models\ChatWebhookEndpoint;
use App\Models\User;
use App\Services\Chat\ChatWebhookSecretRotationService;
use App\Services\Chat\ExternalChatTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ChatWebhookEndpointController extends BaseController
{
    public function __construct(
        protected ExternalChatTokenService $tokenService,
        protected ChatWebhookSecretRotationService $secretRotationService,
    ) {
    }

    public function index(): JsonResponse
    {
        $items = ChatWebhookEndpoint::query()
            ->latest('id')
            ->get()
            ->map(fn (ChatWebhookEndpoint $endpoint) => (new ChatWebhookEndpointResource($endpoint))->resolve())
            ->values()
            ->all();

        return $this->successResponse($items);
    }

    public function store(StoreChatWebhookEndpointRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $scopesInput = $validated['scopes'] ?? config('chat.external_api.scopes.default', []);
        $tokenMetadata = $this->tokenService->issueTokenMetadata(
            is_array($scopesInput) ? $scopesInput : [],
            $validated['name'] ?? null
        );

        $plainToken = $this->tokenService->generatePlainToken();
        $tokenHash = $this->tokenService->hashToken($plainToken);
        $secret = Str::random(64);

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'status' => ((bool) ($validated['is_active'] ?? true)) ? 'active' : 'disabled',
            'created_by' => $user->id,
            'metadata' => array_merge($tokenMetadata, [
                'token_hash' => $tokenHash,
                'token_hash_algo' => (string) config('chat.external_api.token_hash_algo', 'sha256'),
            ]),
        ]);

        $payload = (new ChatWebhookEndpointResource($endpoint))->resolve();
        $payload['plain_token'] = $plainToken;

        return $this->successResponse($payload, 'Webhook endpoint created', 201);
    }

    public function update(UpdateChatWebhookEndpointRequest $request, ChatWebhookEndpoint $endpoint): JsonResponse
    {
        $validated = $request->validated();
        $scopes = null;
        if (array_key_exists('scopes', $validated)) {
            $scopes = $this->tokenService->normalizeScopes((array) $validated['scopes']);
            unset($validated['scopes']);
        }

        if (array_key_exists('is_active', $validated) && ! array_key_exists('status', $validated)) {
            $validated['status'] = (bool) $validated['is_active'] ? 'active' : 'disabled';
        }

        $endpoint->fill($validated);
        if ($scopes !== null) {
            $metadata = is_array($endpoint->metadata) ? $endpoint->metadata : [];
            $metadata['token_scopes'] = $scopes;
            $endpoint->metadata = $metadata;
        }
        $endpoint->save();

        return $this->successResponse((new ChatWebhookEndpointResource($endpoint->fresh()))->resolve(), 'Webhook endpoint updated');
    }

    public function destroy(ChatWebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->delete();

        return $this->successResponse([
            'id' => $endpoint->id,
            'deleted' => true,
        ], 'Webhook endpoint deleted');
    }

    public function rotateSecret(ChatWebhookEndpoint $endpoint): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        $result = $this->secretRotationService->rotateSecret($endpoint, $user);

        return $this->successResponse([
            'id' => $endpoint->id,
            'rotated_at' => $result['rotated_at'],
            'previous_secret_expires_at' => $result['previous_secret_expires_at'],
            'plain_secret' => $result['plain_secret'],
        ], 'Webhook secret rotated');
    }
}
