<?php

namespace App\Observers;

use App\Models\SystemTranslation;
use App\Services\Translation\TranslationCacheService;

/**
 * System translation observer.
 *
 * WHY:
 * Translation cache must stay synchronized with DB state.
 *
 * Responsibilities:
 * - invalidate translation cache
 * - invalidate preload cache
 * - keep runtime localization fresh
 *
 * IMPORTANT:
 * TranslationService uses aggressive caching,
 * therefore invalidation is mandatory.
 */
class SystemTranslationObserver
{
    public function __construct(
        protected TranslationCacheService $cache
    ) {
    }

    /**
     * Handle translation created event.
     */
    public function created(SystemTranslation $translation): void
    {
        $this->clearTranslationCache($translation);
    }

    /**
     * Handle translation updated event.
     */
    public function updated(SystemTranslation $translation): void
    {
        $this->clearTranslationCache($translation);
    }

    /**
     * Handle translation deleted event.
     */
    public function deleted(SystemTranslation $translation): void
    {
        $this->clearTranslationCache($translation);
    }

    /**
     * Clear translation-related cache.
     */
    protected function clearTranslationCache(
        SystemTranslation $translation
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Single translation cache
        |--------------------------------------------------------------------------
        */

        $this->cache->forget(
            locale: $translation->locale,
            group: $translation->group,
            key: $translation->key
        );

        /*
        |--------------------------------------------------------------------------
        | Preload translation cache
        |--------------------------------------------------------------------------
        */

        $this->cache->forgetPreload(
            locale: $translation->locale,
            group: $translation->group
        );

        /*
        |--------------------------------------------------------------------------
        | Global preload cache
        |--------------------------------------------------------------------------
        */

        $this->cache->forgetPreload(
            locale: $translation->locale
        );
    }
}
