<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class NamingConsistencyTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function markdownFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            if (strtolower($item->getExtension()) !== 'md') {
                continue;
            }

            $files[] = $item->getPathname();
        }

        return $files;
    }

    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_docs_use_canonical_naming_terms(): void
    {
        $docs = '';
        foreach ($this->markdownFiles(base_path('docs')) as $path) {
            $docs .= (string) file_get_contents($path)."\n";
        }

        $this->assertStringContainsString('OpenAPI', $docs);
        $this->assertStringContainsString('Swagger UI', $docs);
        $this->assertStringContainsStringIgnoringCase('API docs portal', $docs);
        $this->assertStringContainsStringIgnoringCase('filtered OpenAPI spec', $docs);
        $this->assertStringContainsStringIgnoringCase('permission-aware docs', $docs);
        $this->assertStringContainsString('Realtime', $docs);
        $this->assertStringContainsString('Reverb', $docs);
        $this->assertStringContainsString('WebSocket', $docs);
        $this->assertStringContainsString('RBAC', $docs);
        $this->assertStringContainsString('Vue Admin', $docs);
        $this->assertStringContainsString('Angular Dashboard', $docs);
    }

    public function test_docs_avoid_common_wrong_variants_and_false_microservices_claims(): void
    {
        $docs = '';
        foreach ($this->markdownFiles(base_path('docs')) as $path) {
            $docs .= (string) file_get_contents($path)."\n";
        }

        $this->assertDoesNotMatchRegularExpression('/\bOpen API\b/i', $docs);
        $this->assertDoesNotMatchRegularExpression('/\bSwagger docs route\b/i', $docs);
        $this->assertDoesNotMatchRegularExpression('/^#{1,6}\s+websocket\b/m', $docs);
        $this->assertStringNotContainsStringIgnoringCase('microservices are implemented now', $docs);
    }

    public function test_permission_and_queue_names_remain_stable_in_core_config_and_routes(): void
    {
        $apiRoutes = (string) file_get_contents(base_path('routes/api.php'));
        $horizonConfig = (string) file_get_contents(base_path('config/horizon.php'));
        $microservicesDoc = (string) file_get_contents(base_path('docs/microservices.md'));

        foreach (['api.docs.view', 'api.docs.view.full', 'system.monitoring'] as $permission) {
            $this->assertStringContainsString($permission, $apiRoutes.$microservicesDoc);
        }

        foreach (['webhooks', 'realtime', 'notifications', 'activity', 'emails', 'default', 'low'] as $queue) {
            $this->assertStringContainsString($queue, $horizonConfig.$microservicesDoc);
        }
    }

    public function test_todo_has_single_phase_23_block_when_root_is_available(): void
    {
        $todoPath = $this->repoRootPath('TODO.md');

        if (! file_exists($todoPath)) {
            $this->markTestSkipped('TODO.md is not mounted in this backend test container.');
        }

        $todo = (string) file_get_contents($todoPath);
        preg_match_all('/^# Phase 23 - Final Polish$/m', $todo, $matches);
        $count = count($matches[0] ?? []);

        $this->assertSame(1, $count, 'TODO.md must contain exactly one "Phase 23 - Final Polish" block.');
    }

    public function test_docs_do_not_reference_outdated_or_missing_primary_doc_names(): void
    {
        $docs = '';
        foreach ($this->markdownFiles(base_path('docs')) as $path) {
            $docs .= (string) file_get_contents($path)."\n";
        }

        $this->assertStringNotContainsString('backend/docs/api.md', $docs);
        $this->assertStringNotContainsString('backend/docs/readme.md', strtolower($docs));
        $this->assertStringNotContainsString('backend/docs/swagger.md', strtolower($docs));
    }

    public function test_readmes_keep_canonical_project_naming_when_root_files_are_available(): void
    {
        $readmePath = $this->repoRootPath('README.md');
        $readmeUaPath = $this->repoRootPath('README_UA.md');

        if (! file_exists($readmePath) || ! file_exists($readmeUaPath)) {
            $this->markTestSkipped('Root README files are not mounted in this backend test container.');
        }

        $readme = (string) file_get_contents($readmePath);
        $readmeUa = (string) file_get_contents($readmeUaPath);

        $this->assertStringContainsString('Vue Admin', $readme.$readmeUa);
        $this->assertStringContainsString('Angular Dashboard', $readme.$readmeUa);
    }
}
