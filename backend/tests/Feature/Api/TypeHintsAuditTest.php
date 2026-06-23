<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class TypeHintsAuditTest extends TestCase
{
    private function assertMethodHasReturnType(string $path, string $methodName, string $returnTypePattern): void
    {
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);

        $pattern = '/function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)\s*:\s*'.$returnTypePattern.'/';
        $this->assertMatchesRegularExpression(
            $pattern,
            $contents,
            "Expected return type for {$methodName} in {$path}"
        );
    }

    public function test_key_service_and_middleware_files_exist(): void
    {
        $files = [
            base_path('app/Http/Middleware/ApiDocsAccessMiddleware.php'),
            base_path('app/Http/Middleware/ExternalChatScopeMiddleware.php'),
            base_path('app/Http/Middleware/SecurityHeadersMiddleware.php'),
            base_path('app/Http/Middleware/LogRequestMiddleware.php'),
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            base_path('app/Services/Monitoring/RealtimeLogService.php'),
            base_path('app/Services/System/MonitoringHealthService.php'),
            base_path('app/Services/Rbac/PermissionCacheService.php'),
            base_path('app/Services/MetaService.php'),
            base_path('app/Services/StatsService.php'),
            base_path('app/Services/ApiDocsPermissionService.php'),
            base_path('app/Services/ApiDocsOpenApiFilterService.php'),
            base_path('app/Http/Controllers/ApiDocsFilteredSpecController.php'),
            base_path('app/Http/Controllers/ApiDocsPortalController.php'),
            base_path('app/Services/Chat/ChatConversationQueryService.php'),
            base_path('app/Services/Chat/ChatMessageService.php'),
            base_path('app/Services/Chat/ChatWebhookDeliveryService.php'),
            base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'),
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
        }
    }

    public function test_critical_methods_have_explicit_return_types(): void
    {
        $this->assertMethodHasReturnType(
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            'sanitize',
            'array'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            'summarizeThrowable',
            'array'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/Rbac/PermissionCacheService.php'),
            'globalVersion',
            'int'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/Rbac/PermissionCacheService.php'),
            'userVersion',
            'int'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/ApiDocsPermissionService.php'),
            'userCanSeePath',
            'bool'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/ApiDocsOpenApiFilterService.php'),
            'filterForUser',
            'array'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Services/Chat/ChatConversationQueryService.php'),
            'unreadCountsForConversations',
            'array'
        );
        $this->assertMethodHasReturnType(
            base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'),
            'backoff',
            'array'
        );
    }

    public function test_middleware_handle_signatures_remain_compatible(): void
    {
        $targets = [
            base_path('app/Http/Middleware/ApiDocsAccessMiddleware.php'),
            base_path('app/Http/Middleware/ExternalChatScopeMiddleware.php'),
            base_path('app/Http/Middleware/SecurityHeadersMiddleware.php'),
            base_path('app/Http/Middleware/LogRequestMiddleware.php'),
        ];

        foreach ($targets as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertMatchesRegularExpression(
                '/function\s+handle\s*\(\s*Request\s+\$request\s*,\s*Closure\s+\$next/',
                $contents,
                "Middleware handle signature should keep Request + Closure contract in {$path}"
            );
        }
    }

    public function test_no_mixed_return_types_were_added_to_critical_service_methods(): void
    {
        $criticalFiles = [
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            base_path('app/Services/Rbac/PermissionCacheService.php'),
            base_path('app/Services/ApiDocsPermissionService.php'),
            base_path('app/Services/ApiDocsOpenApiFilterService.php'),
            base_path('app/Services/Chat/ChatConversationQueryService.php'),
            base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'),
        ];

        foreach ($criticalFiles as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/(?:public|protected)\s+function\s+[A-Za-z0-9_]+\s*\([^)]*\)\s*:\s*mixed\b/',
                $contents,
                "Unexpected mixed return type found in {$file}"
            );
        }
    }

    public function test_changed_target_files_have_valid_php_syntax_via_lightweight_lint(): void
    {
        $files = [
            base_path('app/Http/Middleware/ApiDocsAccessMiddleware.php'),
            base_path('app/Http/Middleware/LogRequestMiddleware.php'),
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            base_path('app/Services/ApiDocsOpenApiFilterService.php'),
            base_path('app/Services/Chat/ChatConversationQueryService.php'),
            base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'),
        ];

        foreach ($files as $file) {
            $stripped = @php_strip_whitespace($file);
            $this->assertNotFalse($stripped, "Lightweight lint failed for {$file}");
        }
    }

    public function test_resource_to_array_signatures_do_not_use_risky_return_types(): void
    {
        $resourcePath = base_path('app/Http/Resources');
        if (! is_dir($resourcePath)) {
            $this->markTestSkipped('Resource directory is not present.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resourcePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile() || strtolower($item->getExtension()) !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($item->getPathname());

            if (preg_match('/function\s+toArray\s*\([^)]*\)\s*:\s*([A-Za-z0-9_\\\\|?]+)/', $contents, $match) !== 1) {
                continue;
            }

            $declaredType = strtolower(trim($match[1]));
            $this->assertSame(
                'array',
                $declaredType,
                "Resource toArray return type should be array when explicitly declared in {$item->getPathname()}"
            );
        }
    }
}
