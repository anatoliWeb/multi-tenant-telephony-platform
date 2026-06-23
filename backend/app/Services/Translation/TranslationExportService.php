<?php

namespace App\Services\Translation;

use App\Models\SystemTranslation;
use Illuminate\Support\Facades\File;

/**
 * Translation export service.
 *
 * WHY:
 * Keeps export business logic outside Artisan route bootstrap and gives us a
 * reusable service entrypoint for future sync/import pipelines.
 */
class TranslationExportService
{
    public function __construct(
        protected TranslationPayloadBuilder $payloadBuilder
    ) {
    }

    /**
     * Export translation payloads by locale.
     *
     * @return array<int, array{locale:string,path:string}>
     */
    public function export(array $locales = [], string $outputDir = 'storage/app/translations'): array
    {
        $resolvedLocales = $this->resolveLocales($locales);
        $absoluteOutputDir = base_path($outputDir);

        if (empty($resolvedLocales)) {
            return [];
        }

        File::ensureDirectoryExists($absoluteOutputDir);

        $exports = [];

        foreach ($resolvedLocales as $locale) {
            $payload = $this->payloadBuilder->build($locale, null, null, null);
            $path = $absoluteOutputDir . DIRECTORY_SEPARATOR . $locale . '.json';

            File::put(
                $path,
                json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            $exports[] = [
                'locale' => $locale,
                'path' => $path,
            ];
        }

        return $exports;
    }

    /**
     * @param array<int, string> $locales
     * @return array<int, string>
     */
    protected function resolveLocales(array $locales): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($locale) => trim((string) $locale),
            $locales
        ))));

        if (! empty($normalized)) {
            return $normalized;
        }

        return SystemTranslation::query()
            ->select('locale')
            ->distinct()
            ->orderBy('locale')
            ->pluck('locale')
            ->values()
            ->all();
    }
}
