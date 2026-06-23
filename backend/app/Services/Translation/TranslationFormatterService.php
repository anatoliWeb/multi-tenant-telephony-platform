<?php

namespace App\Services\Translation;

use Illuminate\Support\Collection;

/**
 * Translation formatter service layer.
 *
 * WHY:
 * Frontend localization systems (Vue i18n, SPA hydration, etc.)
 * require grouped translation maps instead of flat DB collections.
 *
 * Responsibilities:
 * - preload translations
 * - group translations
 * - prepare frontend-ready structure
 * - support SPA localization hydration
 */
class TranslationFormatterService
{
    public function __construct(
        protected TranslationCacheService $cache
    ) {
    }

    /**
     * Load grouped translations.
     *
     * Output example:
     *
     * [
     *     'roles' => [
     *         'admin' => 'Administrator'
     *     ]
     * ]
     */
    public function preloadFormatted(
        string $locale,
        ?string $group = null,
        ?bool $frontendOnly = null,
        ?bool $backendOnly = null
    ): array {

        $translations = $this->cache
            ->preload(
                locale: $locale,
                group: $group
            );
        $translations = collect($translations);

        /*
        |--------------------------------------------------------------------------
        | Visibility filtering
        |--------------------------------------------------------------------------
        */

        if ($frontendOnly) {

            $translations = $translations
                ->where('is_frontend', true);
        }

        if ($backendOnly) {

            $translations = $translations
                ->where('is_backend', true);
        }

        /*
        |--------------------------------------------------------------------------
        | Group translations
        |--------------------------------------------------------------------------
        */

        return $translations
            ->groupBy('group')
            ->map(function (Collection $items) {

                return $items
                    ->mapWithKeys(function ($item) {

                        return [
                            $item['key'] => $item['value'],
                        ];
                    })
                    ->toArray();
            })
            ->toArray();
    }
}
