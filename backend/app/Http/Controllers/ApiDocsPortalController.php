<?php

namespace App\Http\Controllers;

use App\Services\ApiDocsPermissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ApiDocsPortalController extends Controller
{
    public function __invoke(Request $request, ApiDocsPermissionService $permissionService): View
    {
        $this->applyPortalLocale($request);

        $user = $request->user();
        $allGroups = $permissionService->groups();
        $hasFullAccess = $permissionService->userHasFullDocsAccess($user);

        $visibleGroups = [];
        $hasPermissionScopedVisibility = false;
        foreach ($allGroups as $groupKey => $group) {
            if ($permissionService->userCanSeeGroup($user, $groupKey)) {
                $visibleGroups[$groupKey] = $group;

                if (
                    count((array) ($group['permissions_any'] ?? [])) > 0
                    || count((array) ($group['permissions_all'] ?? [])) > 0
                ) {
                    $hasPermissionScopedVisibility = true;
                }
            }
        }

        if (! $hasFullAccess && ! $hasPermissionScopedVisibility) {
            $visibleGroups = [];
        }

        $translatedVisibleGroups = [];
        foreach ($visibleGroups as $groupKey => $group) {
            $translatedVisibleGroups[$groupKey] = [
                'label' => $this->translateWithFallback(
                    key: (string) ($group['label_key'] ?? ''),
                    fallback: (string) ($group['label'] ?? $groupKey)
                ),
                'description' => $this->translateWithFallback(
                    key: (string) ($group['description_key'] ?? ''),
                    fallback: (string) ($group['description'] ?? '')
                ),
                'paths' => $group['paths'] ?? [],
            ];
        }

        $accessState = $hasFullAccess
            ? 'full'
            : (count($translatedVisibleGroups) > 0 ? 'limited' : 'none');
        $currentLocale = app()->getLocale();
        $langQuery = '?lang='.$currentLocale;

        return view('docs.api-portal', [
            'visibleGroups' => $translatedVisibleGroups,
            'hasFullAccess' => $hasFullAccess,
            'accessState' => $accessState,
            'docsUiUrl' => '/docs/api'.$langQuery,
            'docsJsonUrl' => '/docs/api.json'.$langQuery,
            'filteredDocsJsonUrl' => '/docs/api.filtered.json'.$langQuery,
        ]);
    }

    private function applyPortalLocale(Request $request): void
    {
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $supportedLocales = array_values(array_filter(
            (array) config('app.supported_locales', ['en', 'uk', 'de']),
            fn (mixed $locale): bool => is_string($locale) && $locale !== ''
        ));
        if ($supportedLocales === []) {
            $supportedLocales = [$fallbackLocale];
        }

        $queryLocale = strtolower((string) $request->query('lang', ''));
        if ($queryLocale !== '') {
            $queryBase = explode('-', $queryLocale)[0] ?? $queryLocale;
            if (in_array($queryBase, $supportedLocales, true)) {
                app()->setLocale($queryBase);
                return;
            }

            app()->setLocale($fallbackLocale);
            return;
        }

        $header = strtolower((string) $request->header('Accept-Language', ''));
        $resolvedLocale = $fallbackLocale;

        if ($header !== '') {
            foreach (preg_split('/\s*,\s*/', $header) ?: [] as $token) {
                $locale = strtolower(trim(explode(';', $token)[0] ?? ''));
                if ($locale === '') {
                    continue;
                }

                $base = explode('-', $locale)[0] ?? $locale;
                if (in_array($base, $supportedLocales, true)) {
                    $resolvedLocale = $base;
                    break;
                }
            }
        }

        app()->setLocale($resolvedLocale);
    }

    private function translateWithFallback(string $key, string $fallback): string
    {
        if ($key === '') {
            return $fallback;
        }

        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $fallbackTranslated = trans($key, [], locale: $fallbackLocale);
        if ($fallbackTranslated !== $key) {
            return $fallbackTranslated;
        }

        return $fallback;
    }
}
