<?php

namespace Tests\Feature\Api;

use App\Jobs\Chat\DeliverChatWebhookJob;
use Tests\TestCase;

class QueueContractTest extends TestCase
{
    public function test_queue_connection_contract_is_env_driven_and_redis_capable(): void
    {
        $queueConfig = file_get_contents(config_path('queue.php'));
        $this->assertNotFalse($queueConfig);
        $this->assertStringContainsString("'default' => env('QUEUE_CONNECTION', 'redis')", $queueConfig);

        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertArrayHasKey('redis', config('queue.connections'));
        $this->assertArrayHasKey('block_for', config('queue.connections.redis'));
    }

    public function test_failed_jobs_tracking_is_enabled_and_failed_jobs_table_exists_in_migrations(): void
    {
        $this->assertNotSame('null', config('queue.failed.driver'));
        $this->assertSame('failed_jobs', config('queue.failed.table'));

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        $hasFailedJobsMigration = collect($migrationFiles)->contains(function (string $path): bool {
            $contents = file_get_contents($path);

            return is_string($contents) && str_contains($contents, "Schema::create('failed_jobs'");
        });

        $this->assertTrue($hasFailedJobsMigration, 'Failed jobs migration contract is missing.');
    }

    public function test_horizon_priority_queues_include_known_ordered_queue_list(): void
    {
        if (! is_file(config_path('horizon.php'))) {
            $this->markTestSkipped('Horizon config is not available in this environment.');
        }

        $expected = ['webhooks', 'realtime', 'notifications', 'activity', 'emails', 'default', 'low'];
        $queues = config('horizon.defaults.supervisor-1.queue');

        $this->assertIsArray($queues);
        $this->assertSame($expected, $queues);
    }

    public function test_queue_priority_list_is_documented_in_performance_docs(): void
    {
        $docs = file_get_contents(base_path('docs/performance.md'));
        $this->assertNotFalse($docs);

        foreach (['webhooks', 'realtime', 'notifications', 'activity', 'emails', 'default', 'low'] as $queueName) {
            $this->assertStringContainsString($queueName, $docs);
        }
    }

    public function test_deliver_chat_webhook_job_contract_uses_webhooks_queue_and_explicit_retry_policy(): void
    {
        $job = new DeliverChatWebhookJob(123);

        $this->assertSame('webhooks', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(15, $job->timeout);
        $this->assertSame([5, 15, 30], $job->backoff());
    }

    public function test_deliver_chat_webhook_job_test_visible_state_has_no_raw_payload_or_secrets(): void
    {
        $job = new DeliverChatWebhookJob(321);
        $serialized = serialize($job);
        $stateKeys = array_keys(get_object_vars($job));

        foreach (['token', 'secret', 'raw_payload', 'raw_response', 'payload', 'authorization', 'webhook_secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($serialized));
            $this->assertNotContains($forbidden, $stateKeys);
        }
    }

    public function test_queue_logging_safety_contract_includes_sensitive_key_sanitization(): void
    {
        $queueSensitiveKeys = (array) config('logging.queue.sensitive_keys', []);
        $structuredForbiddenKeys = (array) config('logging.structured.forbidden_keys', []);

        foreach (['token', 'secret', 'signature', 'authorization', 'raw_payload', 'raw_response', 'response_body'] as $forbidden) {
            $this->assertContains($forbidden, $queueSensitiveKeys);
        }

        foreach (['token', 'secret', 'signature', 'authorization', 'raw_payload', 'raw_response', 'response_body'] as $forbidden) {
            $this->assertContains($forbidden, $structuredForbiddenKeys);
        }
    }

    public function test_worker_supervisor_contract_uses_priority_queue_order_and_runtime_limits(): void
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
        $this->assertStringContainsString('--backoff=10', $conf);
        $this->assertStringContainsString('--max-time=3600', $conf);
        $this->assertStringContainsString('--max-jobs=1000', $conf);
    }

    public function test_failed_queue_operational_commands_are_documented(): void
    {
        $performanceDocs = file_get_contents(base_path('docs/performance.md'));
        $commandsDocs = file_get_contents(base_path('docs/commands.md'));

        $this->assertNotFalse($performanceDocs);
        $this->assertNotFalse($commandsDocs);

        foreach (['php artisan queue:failed', 'php artisan queue:retry all'] as $command) {
            $this->assertStringContainsString($command, $performanceDocs);
            $this->assertStringContainsString($command, $commandsDocs);
        }
    }
}

