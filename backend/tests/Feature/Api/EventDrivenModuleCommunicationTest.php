<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class EventDrivenModuleCommunicationTest extends TestCase
{
    public function test_architecture_docs_define_event_driven_module_communication_foundation(): void
    {
        $architectureDocPath = base_path('docs/architecture.md');

        $this->assertFileExists($architectureDocPath);

        $doc = (string) file_get_contents($architectureDocPath);

        $this->assertStringContainsString('## Event-Driven Module Communication', $doc);
        $this->assertStringContainsString('Domain Events', $doc);
        $this->assertStringContainsString('Queue Jobs', $doc);
        $this->assertStringContainsString('Broadcast Events', $doc);
        $this->assertStringContainsString('Webhook Events', $doc);
        $this->assertStringContainsString('Activity/Audit Events', $doc);

        $this->assertStringContainsString('Allowed communication paths', $doc);
        $this->assertStringContainsString('Avoid / forbidden communication paths', $doc);
        $this->assertStringContainsString('Event naming rules', $doc);
        $this->assertStringContainsString('Payload safety rules', $doc);
        $this->assertStringContainsString('IDs over full models', $doc);
        $this->assertStringContainsString('no microservice split', strtolower($doc));

        $this->assertStringNotContainsString('microservice now', strtolower($doc));
    }

    public function test_existing_safe_payload_tests_exist_as_event_contract_guard(): void
    {
        $chatRealtimeSafePayloadTest = base_path('tests/Feature/Chat/ChatRealtimeSafePayloadTest.php');
        $chatWebhookMessageCallbacksTest = base_path('tests/Feature/Chat/ChatWebhookMessageCallbacksTest.php');
        $chatWebhookHmacTest = base_path('tests/Feature/Chat/ChatWebhookHmacSignatureTest.php');

        $this->assertFileExists($chatRealtimeSafePayloadTest);
        $this->assertFileExists($chatWebhookMessageCallbacksTest);
        $this->assertFileExists($chatWebhookHmacTest);
    }
}

