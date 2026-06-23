<?php

namespace App\Services\Translation;

use App\Models\SystemTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Translation cache layer.
 *
 * WHY:
 * Database-driven localization may trigger hundreds
 * of translation lookups per request.
 *
 * This service centralizes:
 * - translation caching
 * - cache invalidation
 * - preload support
 * - bulk translation loading
 * - scalable localization access
 */
class TranslationCacheService
{
    /**
     * Cache TTL in seconds.
     */
    protected int $ttl = 3600;

    /**
     * Resolve single translation.
     */
    public function get(
        string $locale,
        string $group,
        string $key
    ): ?string {

        return Cache::remember(
            $this->makeCacheKey(
                locale: $locale,
                group: $group,
                key: $key
            ),
            now()->addSeconds($this->ttl),
            function () use (
                $locale,
                $group,
                $key
            ) {

                return SystemTranslation::query()
                    ->active()
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->where('key', $key)
                    ->value('value');
            }
        );
    }

    /**
     * Preload all translations for locale/group.
     *
     * IMPORTANT:
     * This prepares architecture for:
     * - frontend preload
     * - SPA hydration
     * - translation synchronization
     * - runtime translation bundles
     */
    public function preload(
        string $locale,
        ?string $group = null
    ): array {

        $cacheKey = $this->makePreloadKey(
            locale: $locale,
            group: $group
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($this->ttl),
            function () use (
                $locale,
                $group
            ) {

                $query = SystemTranslation::query()
                    ->active()
                    ->where('locale', $locale);

                if ($group) {
                    $query->where('group', $group);
                }

                return $query
                    ->get([
                        'group',
                        'key',
                        'value',
                        'is_frontend',
                        'is_backend',
                    ])
                    ->toArray();
            }
        );
    }

    /**
     * Forget single translation cache.
     */
    public function forget(
        string $locale,
        string $group,
        string $key
    ): void {

        Cache::forget(
            $this->makeCacheKey(
                locale: $locale,
                group: $group,
                key: $key
            )
        );
    }

    /**
     * Forget preload cache.
     */
    public function forgetPreload(
        string $locale,
        ?string $group = null
    ): void {

        Cache::forget(
            $this->makePreloadKey(
                locale: $locale,
                group: $group
            )
        );
    }

    /**
     * Flush translation cache.
     *
     * IMPORTANT:
     * Can be improved later with tagged cache.
     */
    public function flush(): void
    {
        Cache::flush();
    }

    /**
     * Lightweight snapshot token for stale-tracking foundation.
     *
     * WHY:
     * Future clients (Angular/mobile/external tooling) can use this token to
     * detect stale translation bundles without implementing full versioning yet.
     */
    public function snapshotToken(?string $locale = null): string
    {
        $query = SystemTranslation::query()->active();

        if ($locale !== null && $locale !== '') {
            $query->where('locale', $locale);
        }

        $lastUpdated = (string) ((clone $query)->max('updated_at') ?? '');
        $count = (string) ((clone $query)->count());

        return sha1(($locale ?? 'all') . '|' . $lastUpdated . '|' . $count);
    }

    /**
     * Build single translation cache key.
     */
    protected function makeCacheKey(
        string $locale,
        string $group,
        string $key
    ): string {

        return sprintf(
            'translations.%s.%s.%s',
            $locale,
            $group,
            $key
        );
    }

    /**
     * Build preload cache key.
     */
    protected function makePreloadKey(
        string $locale,
        ?string $group = null
    ): string {

        return sprintf(
            'translations.preload.%s.%s',
            $locale,
            $group ?? 'all'
        );
    }
}
