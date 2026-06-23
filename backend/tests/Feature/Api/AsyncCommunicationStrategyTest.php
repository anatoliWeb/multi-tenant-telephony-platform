<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AsyncCommunicationStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_async_communication_strategy_foundation(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Async Communication Strategy', $doc);
        $this->assertStringContainsString('Laravel domain events/listeners', $doc);
        $this->assertStringContainsString('Redis-backed queues', $doc);

        $this->assertStringContainsString('event_id', $doc);
        $this->assertStringContainsString('event_type', $doc);
        $this->assertStringContainsString('event_version', $doc);
        $this->assertStringContainsString('correlation_id', $doc);
        $this->assertStringContainsString('idempotency_key', $doc);

        $this->assertStringContainsString('at-least-once delivery', $docLower);
        $this->assertStringContainsString('idempotent', $docLower);
        $this->assertStringContainsString('retries', $docLower);
        $this->assertStringContainsString('backoff', $docLower);
        $this->assertStringContainsString('dead-letter', $docLower);
        $this->assertStringContainsString('failed jobs', $docLower);

        $this->assertStringContainsString('Outbox pattern', $doc);
        $this->assertStringContainsString('Inbox/dedup', $doc);
        $this->assertStringContainsString('Queue/Topic Model (Future)', $doc);

        $this->assertStringContainsString('no secrets/tokens', $docLower);
        $this->assertStringContainsString('no kafka/rabbitmq is added in this phase', $docLower);
    }
}

