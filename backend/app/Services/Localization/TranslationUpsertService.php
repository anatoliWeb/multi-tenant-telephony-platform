<?php

namespace App\Services\Localization;

use App\Models\SystemTranslation;

/**
 * Centralized dynamic translation writer.
 *
 * WHY:
 * RBAC, settings and future localized entities should reuse one upsert flow
 * instead of duplicating low-level SystemTranslation persistence logic.
 */
class TranslationUpsertService
{
    /**
     * @param array<string, string|null> $translations
     */
    public function saveTranslations(
        string $group,
        string $key,
        array $translations,
        bool $isFrontend = true,
        bool $isBackend = true
    ): void {
        foreach ($translations as $locale => $value) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }

            if (!is_string($value) && $value !== null) {
                continue;
            }

            SystemTranslation::query()->updateOrCreate(
                [
                    'locale' => $locale,
                    'group' => $group,
                    'key' => $key,
                ],
                [
                    'value' => (string) ($value ?? ''),
                    'source' => 'runtime',
                    'is_frontend' => $isFrontend,
                    'is_backend' => $isBackend,
                    'is_system' => true,
                    'is_active' => true,
                    'is_auto_generated' => false,
                    'is_translated' => true,
                    'updated_by' => auth()->id(),
                ]
            );
        }
    }
}

