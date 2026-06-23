<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized runtime settings cache layer.
 *
 * WHY THIS SERVICE EXISTS:
 * Settings may be resolved from:
 * - middleware
 * - policies
 * - guards
 * - SPA preload
 * - feature flags
 * - jobs
 * - controllers
 * - realtime bootstrappers
 *
 * Centralizing cache logic guarantees:
 * - deterministic cache keys
 * - predictable invalidation
 * - stable preload contracts
 * - future inheritance cache extensions
 *
 * IMPORTANT:
 * Application layers should NEVER build settings cache keys manually.
 */
class SettingsCacheService
{
    /**
     * Global cache namespace.
     */
    protected const PREFIX = 'settings';

    /**
     * Runtime cache lifetime.
     *
     * IMPORTANT:
     * Settings are invalidated aggressively on mutation,
     * so TTL acts mostly as safety fallback.
     */
    protected const TTL_SECONDS = 600;

    /**
     * Cache resolved effective setting.
     *
     * WHY:
     * Effective setting resolution may involve:
     * - user overrides
     * - permission overrides
     * - role overrides
     * - global fallbacks
     *
     * Resolution can become expensive during SPA bootstrap.
     *
     * @return array<string, mixed>
     */
    public function rememberResolved(
        int      $userId,
        string   $key,
        ?string  $channel,
        callable $resolver
    ): array
    {

        return Cache::remember(
            $this->resolvedKey(
                userId: $userId,
                key: $key,
                channel: $channel
            ),

            now()->addSeconds(self::TTL_SECONDS),

            $resolver
        );
    }

    /**
     * Cache frontend/backend preload payload.
     *
     * WHY:
     * SPA applications preload runtime configuration
     * during bootstrap.
     *
     * Prevents:
     * - repeated resolver execution
     * - duplicated DB queries
     * - bootstrap latency spikes
     *
     * @return array<string, mixed>
     */
    public function rememberPreload(
        int      $userId,
        ?string  $channel,
        callable $resolver
    ): array
    {

        return Cache::remember(
            $this->preloadKey(
                userId: $userId,
                channel: $channel
            ),

            now()->addSeconds(self::TTL_SECONDS),

            $resolver
        );
    }

    /**
     * Forget resolved runtime cache entry.
     *
     * IMPORTANT:
     * Current implementation uses deterministic cache keys.
     *
     * Wildcard invalidation is intentionally handled
     * by higher-level cache flushing logic.
     */
    public function forgetResolved(
        int     $userId,
        string  $key = '*',
        ?string $channel = null
    ): void
    {

        Cache::forget(
            $this->resolvedKey(
                userId: $userId,
                key: $key,
                channel: $channel
            )
        );
    }

    /**
     * Forget cached preload payload.
     */
    public function forgetPreload(
        int     $userId,
        ?string $channel = null
    ): void
    {

        Cache::forget(
            $this->preloadKey(
                userId: $userId,
                channel: $channel
            )
        );
    }

    /**
     * Flush entire settings cache namespace.
     *
     * IMPORTANT:
     * Current implementation uses Cache::flush()
     * for simplicity.
     *
     * Future improvements may include:
     * - cache tags
     * - namespace versioning
     * - scope-based invalidation
     * - inheritance-aware invalidation
     */
    public function flushAll(): void
    {
        Cache::flush();
    }

    /**
     * Build effective setting cache key.
     *
     * IMPORTANT:
     * Future inheritance evolution may require:
     * - tenant versioning
     * - inheritance hash signatures
     * - scope-aware cache segments
     */
    protected function resolvedKey(
        int     $userId,
        string  $key,
        ?string $channel
    ): string
    {

        $channelName = $channel ?? 'all';

        return sprintf(
            '%s:resolved:user:%d:key:%s:channel:%s',
            self::PREFIX,
            $userId,
            $key,
            $channelName
        );
    }

    /**
     * Build preload payload cache key.
     */
    protected function preloadKey(
        int     $userId,
        ?string $channel
    ): string
    {

        $channelName = $channel ?? 'all';

        return sprintf(
            '%s:preload:user:%d:channel:%s',
            self::PREFIX,
            $userId,
            $channelName
        );
    }
}
