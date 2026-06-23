<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AuthServiceStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_auth_service_strategy_foundation(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Auth Service Strategy', $doc);
        $this->assertStringContainsString('Sanctum/session', $doc);
        $this->assertStringContainsString('Bearer token flow', $doc);

        $this->assertStringContainsString('### Future Auth Service Responsibilities', $doc);
        $this->assertStringContainsString('### What Auth Service Should NOT Own', $doc);
        $this->assertStringContainsString('### Identity and RBAC Boundary Options', $doc);
        $this->assertStringContainsString('Option A: Auth + RBAC together', $doc);
        $this->assertStringContainsString('Option B: Auth identity only, RBAC remains platform/domain service', $doc);

        $this->assertStringContainsString('### Token/Session Strategy (Future)', $doc);
        $this->assertStringContainsString('### Phased Migration Strategy', $doc);
        $this->assertStringContainsString('### Auth Strategy Anti-Patterns', $doc);

        $this->assertStringContainsString('not extracting now', $docLower);
        $this->assertStringContainsString('no plain token storage', $docLower);
        $this->assertStringContainsString('no token logging', $docLower);
    }
}

