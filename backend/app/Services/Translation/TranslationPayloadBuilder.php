<?php

namespace App\Services\Translation;

/**
 * Unified translation payload builder.
 *
 * WHY:
 * Keeps runtime/export contracts identical across Vue, future Angular, and
 * external tooling while avoiding endpoint-specific response drift.
 */
class TranslationPayloadBuilder
{
    public function __construct(
        protected TranslationFormatterService $formatter,
        protected TranslationCacheService $cache
    ) {
    }

    /**
     * Build frontend-ready translation payload contract.
     *
     * Contract:
     * {
     *   locale,
     *   fallback_locale,
     *   translations,
     *   snapshot_token
     * }
     */
    public function build(
        string $locale,
        ?string $group = null,
        ?bool $frontendOnly = null,
        ?bool $backendOnly = null
    ): array {
        return [
            'locale' => $locale,
            'fallback_locale' => (string) config('app.fallback_locale', 'en'),
            'translations' => $this->formatter->preloadFormatted(
                locale: $locale,
                group: $group,
                frontendOnly: $frontendOnly,
                backendOnly: $backendOnly,
            ),
            // Foundation for future stale-bundle checks and sync orchestration.
            'snapshot_token' => $this->cache->snapshotToken($locale),
        ];
    }
}
