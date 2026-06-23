<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ApiDocsAccessMiddleware
{
    /**
     * Enforce permission-aware API docs access rules.
     *
     * Raw specs (/docs/api, /docs/api.json) require full docs permission.
     * Filtered docs/portal require regular docs permission unless local bypass is enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypassInLocal()) {
            return $next($request);
        }

        if ($this->isInternalRawDocsDispatch($request)) {
            return $next($request);
        }

        $gate = $this->isRawDocsRoute($request) ? 'viewFullApiDocs' : 'viewApiDocs';

        if (Gate::allows($gate)) {
            return $next($request);
        }

        abort(403);
    }

    private function shouldBypassInLocal(): bool
    {
        $localBypassEnabled = (bool) config('api-docs.local_bypass', false);

        return app()->environment(['local', 'testing']) && $localBypassEnabled;
    }

    private function isRawDocsRoute(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        return in_array($path, ['/docs/api', '/docs/api.json'], true);
    }

    private function isInternalRawDocsDispatch(Request $request): bool
    {
        // Internal dispatch is used by filtered spec generation and must not be exposed as a public bypass.
        return $this->isRawDocsRoute($request)
            && (bool) $request->attributes->get('api_docs_internal_raw_access', false);
    }
}
