<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MicroservicesObservabilityStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_observability_strategy(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Observability Strategy', $doc);
        $this->assertStringContainsString('structured laravel logs', $docLower);
        $this->assertStringContainsString('request_id', $docLower);
        $this->assertStringContainsString('/health', $doc);
        $this->assertStringContainsString('/api/v1/system/health', $doc);

        $this->assertStringContainsString('### Logs / Metrics / Traces Model', $doc);
        $this->assertStringContainsString('#### Logs', $doc);
        $this->assertStringContainsString('#### Metrics', $doc);
        $this->assertStringContainsString('#### Traces', $doc);

        $this->assertStringContainsString('http_request_duration_ms', $doc);
        $this->assertStringContainsString('http_requests_total', $doc);
        $this->assertStringContainsString('api_errors_total', $doc);
        $this->assertStringContainsString('queue_depth', $doc);
        $this->assertStringContainsString('webhook_delivery_success_total', $doc);
        $this->assertStringContainsString('realtime_connections', $doc);

        $this->assertStringContainsString('### SLI/SLO Strategy (Future Candidates)', $doc);
        $this->assertStringContainsString('### Alerting Strategy (Future)', $doc);
        $this->assertStringContainsString('### Dashboard Strategy (Future)', $doc);
        $this->assertStringContainsString('### Microservices Observability Rules', $doc);
        $this->assertStringContainsString('correlation_id', $docLower);
        $this->assertStringContainsString('observability readiness', $docLower);

        $this->assertStringContainsString('### Observability Anti-Patterns', $doc);
        $this->assertStringContainsString('no raw payload logging', $docLower);
        $this->assertStringContainsString('no secrets/tokens in log/trace/metric labels', $docLower);
        $this->assertStringContainsString('no high-cardinality labels', $docLower);

        $this->assertStringContainsString('no heavy observability stack is added in this phase', $docLower);
    }
}

