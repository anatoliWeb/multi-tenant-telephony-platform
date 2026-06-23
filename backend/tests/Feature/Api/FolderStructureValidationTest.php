<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use Tests\TestCase;

class FolderStructureValidationTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function collectFiles(string $path, array $extensions): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $normalizedExtensions = array_map(static fn (string $ext): string => ltrim(strtolower($ext), '.'), $extensions);
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if (! in_array($extension, $normalizedExtensions, true)) {
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

    public function test_root_expected_files_exist_when_repository_root_is_mounted(): void
    {
        $rootChecks = [
            'README.md',
            'README_UA.md',
            'TODO.md',
            'docker-compose.yml',
            'docker-compose.prod.example.yml',
            '.dockerignore',
            '.github/workflows/ci.yml',
        ];

        $probe = $this->repoRootPath('README.md');
        if (! file_exists($probe)) {
            $this->markTestSkipped('Repository root is not mounted in this backend test container.');
        }

        foreach ($rootChecks as $relativePath) {
            $this->assertFileExists($this->repoRootPath($relativePath), "Missing expected root file: {$relativePath}");
        }
    }

    public function test_backend_expected_directories_exist(): void
    {
        $expectedDirectories = [
            'app',
            'app/Http/Controllers',
            'app/Http/Middleware',
            'app/Http/Requests',
            'app/Services',
            'app/Jobs',
            'app/Events',
            'app/Models',
            'config',
            'database',
            'resources/js',
            'docs',
            'tests/Feature/Api',
            'tests/Feature/Chat',
            'tests/Feature/Auth',
            'tests/Feature/Events',
        ];

        foreach ($expectedDirectories as $relativePath) {
            $this->assertDirectoryExists(base_path($relativePath), "Missing expected backend directory: {$relativePath}");
        }
    }

    public function test_frontend_expected_directories_exist_when_frontend_is_mounted(): void
    {
        $frontendRoot = $this->repoRootPath('frontend/src/app');
        if (! is_dir($frontendRoot)) {
            $this->markTestSkipped('frontend/src/app is not mounted in this backend test container.');
        }

        $expectedDirectories = [
            'frontend/src/app',
            'frontend/src/app/features',
            'frontend/src/app/core',
            'frontend/src/app/api',
            'frontend/src/app/auth',
            'frontend/src/app/i18n',
        ];

        foreach ($expectedDirectories as $relativePath) {
            $this->assertDirectoryExists($this->repoRootPath($relativePath), "Missing expected frontend directory: {$relativePath}");
        }
    }

    public function test_backend_docs_structure_contains_expected_core_documents(): void
    {
        $expectedDocs = [
            'docs/architecture.md',
            'docs/microservices.md',
            'docs/security.md',
            'docs/performance.md',
            'docs/monitoring.md',
            'docs/testing.md',
            'docs/commands.md',
            'docs/deployment.md',
            'docs/docker.md',
            'docs/realtime.md',
            'docs/ci-cd.md',
            'docs/release.md',
            'docs/api/openapi-preparation.md',
            'docs/api/openapi-generator.md',
        ];

        foreach ($expectedDocs as $relativePath) {
            $this->assertFileExists(base_path($relativePath), "Missing expected backend doc: {$relativePath}");
        }
    }

    public function test_no_obvious_misplaced_source_files_in_backend_and_frontend_source_trees(): void
    {
        $phpInsideVueSource = $this->collectFiles(base_path('resources/js'), ['php']);
        $this->assertCount(0, $phpInsideVueSource, 'No .php files are expected under backend/resources/js.');

        $frontendLikeFilesInsideBackendApp = $this->collectFiles(base_path('app'), ['vue', 'ts', 'tsx', 'js']);
        $this->assertCount(0, $frontendLikeFilesInsideBackendApp, 'No frontend source files are expected under backend/app.');

        $markdownInsideBackendApp = $this->collectFiles(base_path('app'), ['md']);
        $allowedMarkdown = [
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path('app/Domain/README.md')),
        ];
        $disallowedMarkdown = array_values(array_filter(
            $markdownInsideBackendApp,
            static fn (string $file): bool => ! in_array($file, $allowedMarkdown, true)
        ));
        $this->assertCount(0, $disallowedMarkdown, 'No docs markdown files are expected under backend/app (except app/Domain/README.md).');

        $frontendPhpFiles = $this->collectFiles($this->repoRootPath('frontend/src'), ['php']);
        $this->assertCount(0, $frontendPhpFiles, 'No backend PHP files are expected under frontend/src.');
    }

    public function test_documentation_maps_and_core_docs_links_are_valid_when_root_docs_are_available(): void
    {
        $docsToVerify = [
            base_path('docs/architecture.md'),
            base_path('docs/testing.md'),
            base_path('docs/commands.md'),
            base_path('docs/deployment.md'),
            base_path('docs/docker.md'),
            base_path('docs/realtime.md'),
            base_path('docs/security.md'),
            base_path('docs/monitoring.md'),
            base_path('docs/performance.md'),
        ];

        foreach ($docsToVerify as $docPath) {
            $this->assertFileExists($docPath);
            $content = (string) file_get_contents($docPath);
            $this->assertFalse($this->hasBrokenLocalLinks($docPath, $content), "Broken local markdown link detected in {$docPath}");
        }

        $readmePath = $this->repoRootPath('README.md');
        $readmeUaPath = $this->repoRootPath('README_UA.md');
        if (! file_exists($readmePath) || ! file_exists($readmeUaPath)) {
            $this->markTestSkipped('Root README files are not mounted in this backend test container.');
        }

        foreach ([$readmePath, $readmeUaPath] as $path) {
            $content = (string) file_get_contents($path);
            $this->assertFalse($this->hasBrokenLocalLinks($path, $content), "Broken local markdown link detected in {$path}");
        }
    }

    public function test_todo_has_single_phase_23_block_when_root_todo_is_available(): void
    {
        $todoPath = $this->repoRootPath('TODO.md');
        if (! file_exists($todoPath)) {
            $this->markTestSkipped('TODO.md is not mounted in this backend test container.');
        }

        $todo = (string) file_get_contents($todoPath);
        preg_match_all('/^# Phase 23 - Final Polish$/m', $todo, $matches);
        $this->assertSame(1, count($matches[0] ?? []), 'TODO.md must contain exactly one "Phase 23 - Final Polish" block.');
    }

    public function test_no_build_dependency_folders_inside_source_or_docs_directories(): void
    {
        $rootsToScan = [
            base_path('app'),
            base_path('docs'),
            base_path('resources/js'),
            $this->repoRootPath('frontend/src'),
        ];

        $forbiddenNames = ['node_modules', 'vendor', 'dist', 'build'];

        foreach ($rootsToScan as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (! $item->isDir()) {
                    continue;
                }

                $basename = strtolower($item->getBasename());
                $this->assertNotContains($basename, $forbiddenNames, "Unexpected build/dependency directory detected in source/docs tree: {$item->getPathname()}");
            }
        }
    }
}

