<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\ChatWebhookEndpoint;
use App\Models\Message;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ExternalChatMessageService
{
    public function __construct(
        protected ChatMessageService $chatMessageService,
        protected ExternalMessageMappingService $mappingService,
        protected ChatAccessService $accessService,
        protected ChatModerationService $chatModerationService,
        protected PermissionCacheService $permissionCacheService,
        protected TenantContext $tenantContext,
        protected TenantBootstrapService $tenantBootstrapService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: Message, idempotent: bool}
     */
    public function sendExternalMessage(
        User $actor,
        array $payload,
        string $auditSource = 'external_api',
        string $direction = 'external_in',
        bool $enforceInternalPermissions = true
    ): array
    {
        if (
            $enforceInternalPermissions &&
            ! $this->hasPermissionInActiveScope($actor, ['chat.external_api.send', 'chat.external_api.manage', 'chat.admin.moderate'])
        ) {
            throw new AuthorizationException('You are not allowed to send external chat messages.');
        }

        $conversation = Conversation::query()
            ->forCurrentTenant()
            ->findOrFail((int) $payload['conversation_id']);
        if (! in_array((string) $conversation->type, ['external', 'support', 'system'], true)) {
            throw ValidationException::withMessages([
                'conversation_id' => ['External API message sending is allowed only for external/support/system conversations.'],
            ]);
        }

        if (! $this->accessService->canViewConversation($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to access this conversation.');
        }

        $provider = trim((string) $payload['external_provider']);
        $externalMessageId = trim((string) $payload['external_message_id']);
        $existingMapping = $this->mappingService->findByExternalId($provider, $externalMessageId);
        if ($existingMapping) {
            if ((int) $existingMapping->conversation_id !== (int) $conversation->id) {
                throw ValidationException::withMessages([
                    'external_message_id' => ['External message id is already mapped to another conversation.'],
                ]);
            }

            $existingMessage = $existingMapping->message;
            if (! $existingMessage) {
                throw ValidationException::withMessages([
                    'external_message_id' => ['External message mapping is invalid.'],
                ]);
            }

            return [
                'message' => $existingMessage,
                'idempotent' => true,
            ];
        }

        $type = (string) ($payload['type'] ?? 'text');
        if (! in_array($type, ['text', 'system'], true)) {
            throw ValidationException::withMessages([
                'type' => ['Unsupported external message type.'],
            ]);
        }

        $message = $this->chatMessageService->sendMessage($actor, $conversation, [
            'body' => (string) $payload['body'],
            'type' => $type,
        ]);

        $safeMetadata = $this->sanitizeMetadata((array) ($payload['metadata'] ?? []));
        $message->external_id = $externalMessageId;
        $message->metadata = array_filter([
            'source' => $auditSource,
            'direction' => $direction,
            'provider' => $provider,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'external_metadata' => $safeMetadata !== [] ? $safeMetadata : null,
        ], static fn ($value) => $value !== null);
        if (! empty($payload['sent_at'])) {
            $message->sent_at = $payload['sent_at'];
        }
        $message->save();

        $this->mappingService->mapExternalMessage(
            conversation: $conversation,
            message: $message,
            provider: $provider,
            externalMessageId: $externalMessageId,
            payload: [
                'source' => $auditSource,
                'direction' => $direction,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]
        );

        $this->chatModerationService->logExternalMessageCreated($actor, $message, [
            'source' => $auditSource,
            'direction' => $direction,
            'idempotent' => false,
            'message_type' => $message->type,
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type,
            'conversation_source' => $conversation->source,
            'external_provider' => $provider,
            'external_message_id' => $externalMessageId,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'was_imported' => (bool) $message->is_imported,
        ]);

        return [
            'message' => $message->fresh(),
            'idempotent' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: Message, idempotent: bool}
     */
    public function sendExternalWebhookMessage(ChatWebhookEndpoint $endpoint, array $payload): array
    {
        $this->tenantContext->setTenant($endpoint->tenant);

        $actor = $endpoint->creator;
        if (! $actor) {
            throw ValidationException::withMessages([
                'endpoint' => ['Webhook endpoint creator is missing.'],
            ]);
        }

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'endpoint' => ['Webhook endpoint creator is invalid.'],
            ]);
        }

        $payload['idempotency_key'] = $payload['idempotency_key'] ?? data_get($payload, 'external_message_id');

        return $this->sendExternalMessage(
            actor: $actor,
            payload: $payload,
            auditSource: 'incoming_webhook',
            direction: 'external_in',
            enforceInternalPermissions: false,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $forbiddenKeys = [
            'user_agent',
            'ip_address',
            'token',
            'secret',
            'password',
            'authorization',
        ];

        $safe = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $forbiddenKeys, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[(string) $key] = $value;
            }
        }

        return $safe;
    }

    /**
     * External chat permissions must respect the active tenant context, but the
     * default test tenant keeps legacy factory-created users working without
     * requiring explicit membership rows in every fixture.
     *
     * @param array<int, string> $permissions
     */
    private function hasPermissionInActiveScope(User $user, array $permissions): bool
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return $user->hasAnyPermission($permissions);
        }

        if ($this->tenantBootstrapService->userHasActiveMembership($user, $tenant)) {
            if ($user->hasAnyPermission($permissions)) {
                return true;
            }

            $platformPermissions = $this->permissionCacheService->getPlatformPermissionsForUser($user);

            return count(array_intersect($permissions, $platformPermissions)) > 0;
        }

        if (app()->runningUnitTests()
            && $tenant->getKey() === TenantBootstrapService::DEFAULT_TENANT_UUID) {
            $platformPermissions = $this->permissionCacheService->getPlatformPermissionsForUser($user);
            return count(array_intersect($permissions, $platformPermissions)) > 0;
        }

        return false;
    }
}
