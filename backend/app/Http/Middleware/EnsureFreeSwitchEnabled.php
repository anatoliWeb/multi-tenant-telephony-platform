<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreeSwitchEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep the directory scaffold local-only until the Laravel-backed
        // provisioning path is intentionally wired to FreeSWITCH.
        if (! config('freeswitch.enabled', false) || config('app.env') !== 'local') {
            abort(404);
        }

        return $next($request);
    }
}
