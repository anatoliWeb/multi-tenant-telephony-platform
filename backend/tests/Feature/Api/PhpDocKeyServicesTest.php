<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PhpDocKeyServicesTest extends TestCase
{
    private function assertMethodHasDocblock(string $path, string $methodName): void
    {
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);

        $pattern = '/\/\*\*[\s\S]*?\*\/\s*(?:public|protected|private)\s+function\s+'.preg_quote($methodName, '/').'\s*\(/';
        $this->assertMatchesRegularExpression(
            $pattern,
            $contents,
            "Expected PHPDoc for method {$methodName} in {$path}"
        );
    }

    public function test_security_logging_and_monitoring_key_methods_have_contract_phpdoc(): void
    {
        $this->assertMethodHasDocblock(base_path('app/Services/Monitoring/StructuredLogContextService.php'), 'sanitize');
        $this->assertMethodHasDocblock(base_path('app/Services/Monitoring/StructuredLogContextService.php'), 'withRequestContext');
        $this->assertMethodHasDocblock(base_path('app/Services/Monitoring/RealtimeLogService.php'), 'logChannelDenied');
        $this->assertMethodHasDocblock(base_path('app/Services/Monitoring/RealtimeLogService.php'), 'logBroadcastFailed');
        $this->assertMethodHasDocblock(base_path('app/Http/Middleware/SecurityHeadersMiddleware.php'), 'handle');
        $this->assertMethodHasDocblock(base_path('app/Http/Middleware/ApiDocsAccessMiddleware.php'), 'handle');
        $this->assertMethodHasDocblock(base_path('app/Services/System/MonitoringHealthService.php'), 'readiness');
    }

    public function test_cache_openapi_and_query_key_methods_have_contract_phpdoc(): void
    {
        $this->assertMethodHasDocblock(base_path('app/Services/Rbac/PermissionCacheService.php'), 'getEffectivePermissionsForUser');
        $this->assertMethodHasDocblock(base_path('app/Services/MetaService.php'), 'getBootstrapMeta');
        $this->assertMethodHasDocblock(base_path('app/Services/StatsService.php'), 'getStats');
        $this->assertMethodHasDocblock(base_path('app/Services/ApiDocsPermissionService.php'), 'userCanSeePath');
        $this->assertMethodHasDocblock(base_path('app/Services/ApiDocsOpenApiFilterService.php'), 'shouldKeepPath');
        $this->assertMethodHasDocblock(base_path('app/Http/Controllers/ApiDocsFilteredSpecController.php'), '__invoke');
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatConversationQueryService.php'), 'visibleConversationsFor');
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatConversationQueryService.php'), 'unreadCountsForConversations');
    }

    public function test_queue_webhook_and_external_auth_key_methods_have_contract_phpdoc(): void
    {
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatMessageService.php'), 'sendMessage');
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatMessageService.php'), 'deleteMessage');
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatWebhookDeliveryService.php'), 'scheduleRetry');
        $this->assertMethodHasDocblock(base_path('app/Services/Chat/ChatWebhookDeliveryService.php'), 'queueEvent');
        $this->assertMethodHasDocblock(base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'), 'backoff');
        $this->assertMethodHasDocblock(base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'), 'handle');
        $this->assertMethodHasDocblock(base_path('app/Http/Middleware/ExternalChatScopeMiddleware.php'), 'handle');
    }
}
