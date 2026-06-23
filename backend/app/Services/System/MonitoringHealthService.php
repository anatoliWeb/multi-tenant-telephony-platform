<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class MonitoringHealthService
{
    /**
     * @return array{status: string}
     */
    public function liveness(): array
    {
        return ['status' => 'ok'];
    }

    /**
     * Build readiness status from dependency checks enabled in monitoring config.
     *
     * Readiness returns degraded when at least one enabled dependency fails, while
     * liveness remains a lightweight process heartbeat.
     *
     * @return array{status: string, checks: array<string, string>}
     */
    public function readiness(): array
    {
        $checks = [];
        $config = (array) config('monitoring.health.checks', []);

        if (($config['database'] ?? true) === true) {
            $checks['database'] = $this->checkDatabase()['status'];
        }

        if (($config['redis'] ?? true) === true) {
            $checks['redis'] = $this->checkRedis()['status'];
        }

        if (($config['cache'] ?? true) === true) {
            $checks['cache'] = $this->checkCache()['status'];
        }

        if (($config['queue'] ?? true) === true) {
            $checks['queue'] = $this->checkQueue()['status'];
        }

        $status = in_array('failed', $checks, true) ? 'degraded' : 'ok';

        return [
            'status' => $status,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, reason?: string}
     */
    public function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'reason' => $this->sanitizeError($exception)];
        }
    }

    /**
     * @return array{status: string, reason?: string}
     */
    public function checkRedis(): array
    {
        try {
            $connection = (string) config('database.redis.default.connection', 'default');
            Redis::connection($connection)->ping();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'reason' => $this->sanitizeError($exception)];
        }
    }

    /**
     * @return array{status: string, reason?: string}
     */
    public function checkCache(): array
    {
        try {
            $key = sprintf('monitoring.health.cache.%s', uniqid('', true));
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);

            if ($value !== 'ok') {
                return ['status' => 'failed', 'reason' => 'cache_miss'];
            }

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'reason' => $this->sanitizeError($exception)];
        }
    }

    /**
     * Validate queue connection configuration only.
     *
     * This check intentionally avoids dispatching jobs to keep readiness side-effect free.
     *
     * @return array{status: string, reason?: string}
     */
    public function checkQueue(): array
    {
        try {
            $default = (string) config('queue.default', '');
            $connectionConfig = config("queue.connections.{$default}");

            if ($default === '' || ! is_array($connectionConfig)) {
                return ['status' => 'failed', 'reason' => 'queue_config_missing'];
            }

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'reason' => $this->sanitizeError($exception)];
        }
    }

    /**
     * Return safe health error summary without leaking environment details.
     */
    public function sanitizeError(Throwable $exception): string
    {
        return class_basename($exception);
    }
}
