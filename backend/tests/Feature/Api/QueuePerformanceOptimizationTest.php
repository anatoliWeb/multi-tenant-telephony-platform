<?php

namespace Tests\Feature\Api;

use App\Jobs\Chat\DeliverChatWebhookJob;
use Tests\TestCase;

class QueuePerformanceOptimizationTest extends TestCase
{
    public function test_env_example_documents_redis_queue_settings(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertNotFalse($env);

        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $env);
        $this->assertStringContainsString('REDIS_QUEUE_RETRY_AFTER=', $env);
        $this->assertStringContainsString('QUEUE_WORKER_QUEUES=', $env);
        $this->assertStringContainsString('QUEUE_FAILED_DRIVER=database-uuids', $env);
    }

    public function test_queue_config_uses_redis_and_failed_jobs_storage(): void
    {
        $this->assertSame('redis', config('queue.default'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertSame('failed_jobs', config('queue.failed.table'));
    }

    public function test_critical_job_queue_and_retry_policy_are_configured(): void
    {
        $job = new DeliverChatWebhookJob(1);

        $this->assertSame('webhooks', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(15, $job->timeout);
        $this->assertSame([5, 15, 30], $job->backoff());
    }

    public function test_supervisor_worker_uses_priority_queues_and_sane_runtime_flags(): void
    {
        $supervisorPath = dirname(base_path()).'/docker/supervisor/supervisord.conf';
        if (! is_file($supervisorPath)) {
            $this->markTestSkipped('Supervisor config is not mounted inside backend test container.');
        }

        $conf = file_get_contents($supervisorPath);
        $this->assertNotFalse($conf);
        $this->assertStringContainsString('queue:work redis', $conf);
        $this->assertStringContainsString('--queue=webhooks,realtime,notifications,activity,emails,default,low', $conf);
        $this->assertStringContainsString('--tries=3', $conf);
        $this->assertStringContainsString('--timeout=90', $conf);
        $this->assertStringContainsString('--sleep=1', $conf);
        $this->assertStringContainsString('--backoff=10', $conf);
    }

    public function test_queue_docs_are_present_and_do_not_contain_sensitive_examples(): void
    {
        $docs = file_get_contents(base_path('docs/performance.md'));
        $this->assertNotFalse($docs);

        $this->assertStringContainsString('## Queue Performance Optimization', $docs);
        $this->assertStringContainsString('php artisan queue:restart', $docs);
        $this->assertStringContainsString('php artisan queue:failed', $docs);

        $lower = strtolower($docs);
        $this->assertStringNotContainsString('authorization:', $lower);
        $this->assertStringNotContainsString('webhook_secret=', $lower);
    }
}

