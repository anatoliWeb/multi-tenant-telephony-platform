<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingsResolverService;

/**
 * Settings facade service for runtime consumers.
 *
 * WHY:
 * Application layers should use one API (`settings()->get()`, helper `settings`)
 * rather than coupling directly to resolver internals. This also centralizes
 * preload and future hierarchical-source metadata expansion.
 */
class SettingsService
{
    public function __construct(
        protected SettingsResolverService $resolver,
        protected SettingsCacheService $cache
    ) {
    }

    /**
     * Resolve effective setting value.
     *
     * WHY:
     * Most runtime consumers only need the final resolved value and should not
     * know anything about inheritance chains or resolution metadata.
     *
     * Resolution may include:
     * - user overrides
     * - permission overrides
     * - role overrides
     * - global defaults
     *
     * IMPORTANT:
     * This method intentionally hides inheritance complexity from application
     * layers and acts as the primary runtime API contract.
     */
    public function get(
        string $key,
        mixed $default = null,
        ?string $channel = null,
        ?User $user = null
    ): mixed {

        $resolved = $user
            ? $this->cache->rememberResolved(
                $user->id,
                $key,
                $channel,
                fn (): array => $this->resolver->getForUser(
                    user: $user,
                    key: $key,
                    channel: $channel
                )
            )
            : $this->resolver->get(
                key: $key,
                channel: $channel
            );

        return $resolved['value'] ?? $default;
    }

    /**
     * Resolve effective setting with inheritance metadata.
     *
     * WHY:
     * Admin interfaces and debugging tools must understand:
     * - where the value came from
     * - which scope resolved it
     * - which setting record won resolution
     *
     * Used by:
     * - effective settings preview UI
     * - inheritance debugging
     * - future tenant-aware inspection
     * - platform diagnostics
     *
     * IMPORTANT:
     * Runtime consumers should normally use:
     * settings()->get(...)
     *
     * instead of this method.
     *
     * @return array{
     *     value:mixed,
     *     source:?string,
     *     scope:?string,
     *     setting:?SystemSetting
     * }
     */
    public function getDetailed(
        string $key,
        mixed $default = null,
        ?string $channel = null,
        ?User $user = null
    ): array {

        $resolved = $user
            ? $this->cache->rememberResolved(
                $user->id,
                $key,
                $channel,
                fn (): array => $this->resolver->getForUser(
                    user: $user,
                    key: $key,
                    channel: $channel
                )
            )
            : $this->resolver->get(
                key: $key,
                channel: $channel
            );

        return [
            'value' => $resolved['value'] ?? $default,
            'source' => $resolved['source'] ?? null,
            'scope' => $resolved['scope'] ?? null,
            'setting' => $resolved['setting'] ?? null,
        ];
    }


    /**
     * Create or update runtime setting.
     *
     * WHY:
     * Centralizing persistence guarantees:
     * - typed serialization consistency
     * - cache invalidation consistency
     * - future audit/event hooks
     * - inheritance compatibility
     *
     * IMPORTANT:
     * Values are stored serialized because settings support:
     * - booleans
     * - arrays
     * - JSON
     * - numeric values
     * - feature flags
     * - future encrypted payloads
     */
    public function set(
        string $key,
        mixed $value,
        array $attributes = []
    ): SystemSetting {

        $type = (string) (
            $attributes['type']
            ?? SystemSetting::TYPE_STRING
        );

        $serialized = $this->serializeValue($value, $type);

        /*
        |--------------------------------------------------------------------------
        | Scope Identity
        |--------------------------------------------------------------------------
        |
        | Determines which inheritance layer owns this setting.
        */

        $scope = [
            'key' => $key,
            'scope_user_id' => $attributes['scope_user_id'] ?? null,
            'scope_role_id' => $attributes['scope_role_id'] ?? null,
            'scope_permission_id' => $attributes['scope_permission_id'] ?? null,
        ];

        /*
        |--------------------------------------------------------------------------
        | Runtime Payload
        |--------------------------------------------------------------------------
        */

        $payload = [
            'label' => (string) ($attributes['label'] ?? $key),
            'group' => (string) ($attributes['group'] ?? SystemSetting::DEFAULT_GROUP),
            'description' => $attributes['description'] ?? null,

            'type' => $type,

            'value' => $serialized,

            'default_value' => array_key_exists('default_value', $attributes)
                ? $this->serializeValue(
                    $attributes['default_value'],
                    $type
                )
                : null,

            'is_frontend' => (bool) ($attributes['is_frontend'] ?? true),
            'is_backend' => (bool) ($attributes['is_backend'] ?? true),

            'is_public' => (bool) ($attributes['is_public'] ?? false),
            'is_encrypted' => (bool) ($attributes['is_encrypted'] ?? false),

            /*
            |--------------------------------------------------------------------------
            | Resolution Metadata
            |--------------------------------------------------------------------------
            */

            'priority' => (int) ($attributes['priority'] ?? SystemSetting::DEFAULT_PRIORITY),

            'inheritance_source' => $attributes['inheritance_source']
                ?? null,

            'is_active' => (bool) ($attributes['is_active'] ?? true),

            'is_system' => (bool) ($attributes['is_system'] ?? false),

            'updated_by' => auth()->id(),
        ];

        $setting = SystemSetting::updateOrCreate(
            $scope,
            $payload
        );

        /*
        |--------------------------------------------------------------------------
        | Cache Invalidation
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Effective setting resolution is heavily cached.
        | Every mutation must invalidate runtime caches.
        */

        $this->invalidateCaches();

        return $setting;
    }

    /**
     * Preload frontend-safe resolved settings.
     *
     * WHY:
     * SPA bootstrap should avoid:
     * - dozens of runtime API calls
     * - duplicate resolver execution
     * - hydration flickering
     *
     * This endpoint prepares:
     * - Vue admin bootstrap
     * - future Angular dashboard bootstrap
     * - public-safe frontend runtime config
     *
     * IMPORTANT:
     * Only flattened effective values are exposed to frontend preload.
     * Internal inheritance metadata remains backend-only.
     *
     * @return array<string, mixed>
     */
    public function preloadFrontend(User $user): array
    {
        $resolved = $this->cache->rememberPreload(
            $user->id,
            'frontend',
            function () use ($user): array {
                return $this->resolver->resolveAllForUser(
                    user: $user,
                    channel: 'frontend'
                );
            }
        );

        return [
            'channel' => SystemSetting::CHANNEL_FRONTEND,

            'settings' => $this->flattenResolvedMap($resolved),
        ];
    }

    /**
     * Invalidate runtime settings caches.
     *
     * WHY:
     * Settings are aggressively cached because they may be resolved:
     * - during requests
     * - during SPA bootstrap
     * - in policies
     * - in jobs
     * - in middleware
     *
     * IMPORTANT:
     * Future optimization may migrate this to:
     * - tagged cache
     * - versioned cache namespaces
     * - partial scope invalidation
     */
    public function invalidateCaches(?int $userId = null): void
    {
        $this->resolver->invalidateCaches($userId);

        if ($userId !== null) {

            $this->cache->forgetResolved($userId, '*', null);
            $this->cache->forgetResolved($userId, '*', 'frontend');
            $this->cache->forgetResolved($userId, '*', 'backend');

            $this->cache->forgetPreload($userId, 'frontend');

            return;
        }

        $this->cache->flushAll();
    }


    /**
     * Serialize typed runtime setting value.
     *
     * WHY:
     * Settings are stored in a flexible schema-light table supporting
     * heterogeneous value types.
     */
    protected function serializeValue(
        mixed $value,
        string $type
    ): ?string {

        if ($value === null) {
            return null;
        }

        return match ($type) {

            SystemSetting::TYPE_ARRAY,
            SystemSetting::TYPE_JSON => is_string($value)
                ? $value
                : json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            SystemSetting::TYPE_BOOLEAN => filter_var(
                $value,
                FILTER_VALIDATE_BOOL
            )
                ? 'true'
                : 'false',

            SystemSetting::TYPE_INTEGER => (string) ((int) $value),

            SystemSetting::TYPE_FLOAT => (string) ((float) $value),

            default => (string) $value,
        };
    }

    /**
     * Flatten resolved inheritance map into frontend-safe payload.
     *
     * WHY:
     * Frontend runtime preload should receive only:
     * key => value
     *
     * without internal inheritance/debug metadata.
     *
     * @param array<string, array<string, mixed>> $resolved
     * @return array<string, mixed>
     */
    protected function flattenResolvedMap(array $resolved): array
    {
        $flattened = [];

        foreach ($resolved as $key => $item) {
            $flattened[$key] = $item['value'] ?? null;
        }

        return $flattened;
    }
}
