<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class BrokerEvaluationStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_broker_evaluation_strategy(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Kafka / RabbitMQ Evaluation', $doc);
        $this->assertStringContainsString('do not add kafka/rabbitmq now', $docLower);

        $this->assertStringContainsString('Redis Queues', $doc);
        $this->assertStringContainsString('Redis Streams', $doc);
        $this->assertStringContainsString('RabbitMQ', $doc);
        $this->assertStringContainsString('Kafka', $doc);

        $this->assertStringContainsString('### Decision Criteria', $doc);
        $this->assertStringContainsString('### Domain-Specific Recommendation', $doc);
        $this->assertStringContainsString('### Migration Path (Future)', $doc);
        $this->assertStringContainsString('### Broker Evaluation Anti-Patterns', $doc);

        $this->assertStringContainsString('outbox/inbox pattern', $docLower);
        $this->assertStringContainsString('observability', $docLower);
        $this->assertStringContainsString('operational ownership', $docLower);
        $this->assertStringContainsString('no secrets/raw payloads in messages', $docLower);
    }
}

