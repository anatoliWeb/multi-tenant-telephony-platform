<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API locale propagation middleware.
 *
 * WHY:
 * Frontend sends active locale via Accept-Language. API resources/services
 * should resolve translations using the same locale automatically, without
 * endpoint-specific query parameters.
 */
class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = $this->supportedLocales();
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        $resolvedLocale = $this->resolveLocaleFromHeader(
            header: (string) $request->header('Accept-Language', ''),
            supportedLocales: $supportedLocales
        );

        app()->setLocale($resolvedLocale ?? $fallbackLocale);

        return $next($request);
    }

    /**
     * @param array<int, string> $supportedLocales
     */
    protected function resolveLocaleFromHeader(string $header, array $supportedLocales): ?string
    {
        if ($header === '') {
            return null;
        }

        $tokens = preg_split('/\s*,\s*/', $header) ?: [];

        foreach ($tokens as $token) {
            $locale = strtolower(trim(explode(';', $token)[0] ?? ''));
            if ($locale === '') {
                continue;
            }

            if (in_array($locale, $supportedLocales, true)) {
                return $locale;
            }

            // Support regional tags like "uk-UA" by matching base locale "uk".
            $baseLocale = explode('-', $locale)[0] ?? '';
            if ($baseLocale !== '' && in_array($baseLocale, $supportedLocales, true)) {
                return $baseLocale;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function supportedLocales(): array
    {
        /** @var mixed $configured */
        $configured = config('app.supported_locales', ['en', 'uk', 'de']);
        if (!is_array($configured) || $configured === []) {
            return ['en'];
        }

        $normalized = array_values(array_filter($configured, fn ($locale) => is_string($locale) && $locale !== ''));
        return $normalized === [] ? ['en'] : $normalized;
    }
}

