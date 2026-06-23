<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiGatewayStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_api_gateway_strategy_foundation(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## API Gateway Strategy', $doc);
        $this->assertStringContainsString('### Current State', $doc);
        $this->assertStringContainsString('modular monolith', $docLower);
        $this->assertStringContainsString('/api/v1', $doc);

        $this->assertStringContainsString('### Future Gateway Responsibilities', $doc);
        $this->assertStringContainsString('### What Gateway Should NOT Own', $doc);
        $this->assertStringContainsString('### Gateway Routing Model (Future)', $doc);

        $this->assertStringContainsString('### Auth Forwarding Strategy', $doc);
        $this->assertStringContainsString('### Rate Limiting Strategy', $doc);
        $this->assertStringContainsString('### OpenAPI Strategy', $doc);
        $this->assertStringContainsString('### WebSocket / Reverb Strategy', $doc);
        $this->assertStringContainsString('### Gateway Anti-Patterns', $doc);

        $this->assertStringContainsString('no business logic in gateway', $docLower);
        $this->assertStringContainsString('no real gateway is added in this phase', $docLower);
    }
}

