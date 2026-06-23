<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class RealtimeDocumentationTest extends TestCase
{
    public function test_realtime_documentation_contains_reverb_channels_diagnostics_and_safety_guide(): void
    {
        $path = base_path('docs/realtime.md');
        $this->assertFileExists($path);

        $doc = (string) file_get_contents($path);
        $lower = strtolower($doc);

        $this->assertStringContainsString('# Realtime', $doc);
        $this->assertStringContainsString('## Purpose', $doc);
        $this->assertStringContainsString('## Current Realtime Stack', $doc);
        $this->assertStringContainsString('## Environment Variables', $doc);
        $this->assertStringContainsString('## Realtime Channels', $doc);
        $this->assertStringContainsString('## Chat Events', $doc);
        $this->assertStringContainsString('## Presence', $doc);
        $this->assertStringContainsString('## Vue Admin Diagnostics', $doc);
        $this->assertStringContainsString('## Security Rules', $doc);
        $this->assertStringContainsString('## Logging and Monitoring', $doc);
        $this->assertStringContainsString('## Troubleshooting', $doc);
        $this->assertStringContainsString('## Testing', $doc);

        $this->assertStringContainsString('WS', $doc);
        $this->assertStringContainsString('EV', $doc);
        $this->assertStringContainsString('ON', $doc);
        $this->assertStringContainsString('PG', $doc);

        $this->assertStringContainsString('private-chat.conversation.{id}', $doc);
        $this->assertStringContainsString('presence-chat.{id}', $doc);
        $this->assertStringContainsString('chat.{id}', $doc);
        $this->assertStringContainsString('private-notifications.user.{userId}', $doc);
        $this->assertStringContainsString('private-system.notifications', $doc);
        $this->assertStringContainsString('private-activity.stream', $doc);
        $this->assertStringContainsString('presence-online', $doc);
        $this->assertStringContainsString('presence-dashboard', $doc);

        $this->assertStringContainsString('chat.message.created', $doc);
        $this->assertStringContainsString('chat.typing.started', $doc);
        $this->assertStringContainsString('chat.attachment.created', $doc);

        $this->assertStringContainsString('backend/docs/architecture.md', $doc);
        $this->assertStringContainsString('backend/docs/security.md', $doc);
        $this->assertStringContainsString('backend/docs/monitoring.md', $doc);

        $this->assertStringNotContainsString('app_key=base64:', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('sk_live_', $lower);
        $this->assertStringNotContainsString('ghp_', $lower);

        $this->assertStringContainsString('no standalone realtime service is extracted now', $lower);
        $this->assertStringNotContainsString('realtime service is implemented as a microservice', $lower);
    }
}

