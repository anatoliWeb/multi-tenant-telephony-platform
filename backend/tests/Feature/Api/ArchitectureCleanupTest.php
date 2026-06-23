<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use Tests\TestCase;

class ArchitectureCleanupTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function collectFiles(string $path, string $extension = 'md'): array
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

            if ($extension !== '' && strtolower($item->getExtension()) !== strtolower($extension)) {
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

    /**
     * @return list<string>
     */
    private function markdownLinks(string $content): array
    {
        preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $content, $matches);

        return array_values(array_filter(
            array_map(static fn (string $link): string => trim($link), $matches[1] ?? []),
            static fn (string $link): bool => $link !== ''
        ));
    }

    private function hasBrokenLocalLinks(string $docPath, string $content): bool
    {
        $docDir = dirname($docPath);
        $links = $this->markdownLinks($content);

        foreach ($links as $link) {
            if (Str::startsWith($link, ['http://', 'https://', 'mailto:', '#'])) {
                continue;
            }

            $normalized = trim($link, '`');
            if ($normalized === '') {
                continue;
            }

            $candidate = str_starts_with($normalized, '/')
                ? $this->repoRootPath(ltrim($normalized, '/'))
                : $docDir.DIRECTORY_SEPARATOR.$normalized;

            if (! file_exists($candidate)) {
                return true;
            }
        }

        return false;
    }

    public function test_todo_has_single_phase_23_block(): void
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

    public function test_architecture_docs_keep_modular_monolith_positioning_and_no_false_microservices_claim(): void
    {
        $architecturePath = base_path('docs/architecture.md');
        $microservicesPath = base_path('docs/microservices.md');

        $this->assertFileExists($architecturePath);
        $this->assertFileExists($microservicesPath);

        $architecture = (string) file_get_contents($architecturePath);
        $microservices = (string) file_get_contents($microservicesPath);

        $this->assertStringContainsStringIgnoringCase('modular monolith', $architecture);
        $this->assertStringContainsStringIgnoringCase('modular monolith', $microservices);
        $this->assertStringContainsStringIgnoringCase('no microservice extraction', $architecture);
        $this->assertStringContainsStringIgnoringCase('No microservice extraction is performed in this phase.', $microservices);
        $this->assertStringNotContainsStringIgnoringCase('microservices are implemented now', $architecture);
    }

    public function test_key_docs_have_valid_internal_links_and_cross_references(): void
    {
        $docs = [
            base_path('docs/architecture.md'),
            base_path('docs/testing.md'),
            base_path('docs/commands.md'),
            base_path('docs/deployment.md'),
            base_path('docs/docker.md'),
            base_path('docs/realtime.md'),
            base_path('docs/monitoring.md'),
            base_path('docs/security.md'),
            base_path('docs/performance.md'),
            base_path('docs/microservices.md'),
            base_path('docs/api/openapi-preparation.md'),
            base_path('docs/api/openapi-generator.md'),
        ];

        foreach ($docs as $docPath) {
            $this->assertFileExists($docPath);
            $doc = (string) file_get_contents($docPath);

            $this->assertFalse(
                $this->hasBrokenLocalLinks($docPath, $doc),
                "Broken local markdown link detected in {$docPath}"
            );
        }

        $architecture = (string) file_get_contents(base_path('docs/architecture.md'));
        $this->assertStringContainsString('backend/docs/commands.md', $architecture);
        $this->assertStringContainsString('backend/docs/deployment.md', $architecture);
        $this->assertStringContainsString('backend/docs/docker.md', $architecture);
        $this->assertStringContainsString('backend/docs/realtime.md', $architecture);
    }

    public function test_no_controller_to_controller_instantiation_or_service_to_controller_dependency(): void
    {
        $controllerFiles = $this->collectFiles(base_path('app/Http/Controllers'), 'php');

        foreach ($controllerFiles as $file) {
            $code = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/new\s+[A-Za-z0-9_\\\\]+Controller\s*\(/', $code, "Controller-to-controller instantiation detected in {$file}");
            $this->assertDoesNotMatchRegularExpression('/App\\\\Http\\\\Controllers\\\\[A-Za-z0-9_\\\\]+Controller::class/', $code, "Controller class reference detected in {$file}");
        }

        $serviceFiles = $this->collectFiles(base_path('app/Services'), 'php');

        foreach ($serviceFiles as $file) {
            $code = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/use\s+App\\\\Http\\\\Controllers\\\\/', $code, "Service imports controller namespace in {$file}");
        }
    }

    public function test_docs_filenames_are_unique_and_testing_doc_has_architecture_cleanup_guard_section(): void
    {
        $docFiles = $this->collectFiles(base_path('docs'), 'md');
        $docFiles = array_values(array_filter(
            $docFiles,
            static fn (string $file): bool => realpath($file) !== realpath(base_path('docs/architecture/README.md'))
        ));

        $basenameGroups = [];
        foreach ($docFiles as $file) {
            $basename = strtolower(basename($file));
            $basenameGroups[$basename][] = $file;
        }

        foreach ($basenameGroups as $basename => $files) {
            $this->assertLessThanOrEqual(1, count($files), "Duplicate doc filename detected for {$basename}");
        }

        $testingDoc = (string) file_get_contents(base_path('docs/testing.md'));
        $this->assertStringContainsString('## Architecture Cleanup Guard', $testingDoc);
        $this->assertStringContainsString('## API Test Strategy', $testingDoc);
        $this->assertStringContainsString('## Auth Test Strategy', $testingDoc);
        $this->assertStringContainsString('## RBAC Test Strategy', $testingDoc);
        $this->assertStringContainsString('## Queue Test Strategy', $testingDoc);
        $this->assertStringContainsString('## Realtime Test Strategy', $testingDoc);
        $this->assertStringContainsString('## Frontend Integration Test Strategy', $testingDoc);
        $this->assertStringNotContainsString('Phase 21 TODO', $testingDoc);
    }

    public function test_readme_doc_map_links_are_valid_when_root_files_available(): void
    {
        $readmePath = $this->repoRootPath('README.md');
        $readmeUaPath = $this->repoRootPath('README_UA.md');

        if (! file_exists($readmePath) || ! file_exists($readmeUaPath)) {
            $this->markTestSkipped('Root README files are not mounted in this backend test container.');
        }

        foreach ([$readmePath, $readmeUaPath] as $path) {
            $content = (string) file_get_contents($path);
            $this->assertFalse(
                $this->hasBrokenLocalLinks($path, $content),
                "Broken local markdown link detected in {$path}"
            );
        }
    }
}
