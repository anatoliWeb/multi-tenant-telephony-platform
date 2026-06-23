<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthService
{
    /**
     * @return array<string, string>
     */
    public function health(): array
    {
        $db = 'ok';
        $cache = 'ok';

        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = 'failed';
        }

        try {
            $key = 'system.health.ping';
            Cache::put($key, 'pong', 10);
            $cache = Cache::get($key) === 'pong' ? 'ok' : 'failed';
        } catch (\Throwable) {
            $cache = 'failed';
        }

        return [
            'database' => $db,
            'cache' => $cache,
            'app_env' => (string) config('app.env'),
            'app_debug' => config('app.debug') ? 'true' : 'false',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function debugInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => (string) config('app.env'),
            'app_locale' => (string) config('app.locale'),
            'fallback_locale' => (string) config('app.fallback_locale'),
            'cache_driver' => (string) config('cache.default'),
            'queue_driver' => (string) config('queue.default'),
            'timezone' => (string) config('app.timezone'),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    public function queueDiagnostics(): array
    {
        $queueConnection = (string) config('queue.default');
        $failedJobsCount = 0;
        $redisStatus = 'n/a';

        try {
            $failedJobsCount = (int) DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable) {
            $failedJobsCount = -1;
        }

        if ($queueConnection === 'redis') {
            try {
                $redisConnection = (string) config('queue.connections.redis.connection', 'default');
                $ping = Redis::connection($redisConnection)->ping();
                $redisStatus = is_string($ping) ? strtolower($ping) : 'ok';
            } catch (\Throwable) {
                $redisStatus = 'failed';
            }
        }

        return [
            'queue_connection' => $queueConnection,
            'failed_jobs_count' => $failedJobsCount,
            'redis_status' => $redisStatus,
            'worker_hint' => 'docker compose logs -f queue-worker',
        ];
    }
}
