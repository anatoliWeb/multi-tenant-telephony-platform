<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DebugLogCleanupTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * @return list<string>
     */
    private function collectFiles(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

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

            $files[] = $item->getPathname();
        }

        return $files;
    }

    private function shouldSkipFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $skipSegments = [
            '/vendor/',
            '/node_modules/',
            '/storage/',
            '/bootstrap/cache/',
            '/public/build/',
            '/dist/',
            '/coverage/',
            '/.git/',
        ];

        foreach ($skipSegments as $segment) {
            if (str_contains($normalized, $segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $files
     * @param list<string> $patterns
     */
    private function assertFilesDoNotContainPatterns(array $files, array $patterns, string $failurePrefix): void
    {
        foreach ($files as $file) {
            if ($this->shouldSkipFile($file)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $this->fail($failurePrefix.' Found forbidden pattern '.$pattern.' in '.$file);
                }
            }
        }
    }

    public function test_backend_runtime_code_has_no_dump_debug_helpers(): void
    {
        $targets = [
            base_path('app'),
            base_path('routes'),
            base_path('config'),
        ];

        foreach ($targets as $target) {
            $this->assertDirectoryExists($target);
        }

        $files = [];
        foreach ($targets as $target) {
            $files = [...$files, ...$this->collectFiles($target)];
        }

        $this->assertFilesDoNotContainPatterns(
            $files,
            [
                '/\bdd\s*\(/',
                '/\bdump\s*\(/',
                '/\bray\s*\(/',
                '/\bvar_dump\s*\(/',
                '/\bprint_r\s*\(/',
            ],
            'Debug helper scan failed.'
        );
    }

    public function test_frontend_source_has_no_debugger_or_console_log_debug_in_production_source(): void
    {
        $vuePath = base_path('resources/js');
        $angularPath = $this->repoRootPath('frontend/src');

        $this->assertDirectoryExists($vuePath);

        $pathsToScan = [$vuePath];
        if (is_dir($angularPath)) {
            $pathsToScan[] = $angularPath;
        } else {
            $this->markTestSkipped('frontend/src is not mounted in this backend test container; run in full-repo context for Angular scan.');
        }

        $files = [];
        foreach ($pathsToScan as $path) {
            foreach ($this->collectFiles($path) as $file) {
                $normalized = str_replace('\\', '/', $file);
                if (str_contains($normalized, '.spec.') || str_contains($normalized, '/__tests__/')) {
                    continue;
                }

                $files[] = $file;
            }
        }

        $this->assertFilesDoNotContainPatterns(
            $files,
            [
                '/\bdebugger\b/',
                '/console\.log\s*\(/',
                '/console\.debug\s*\(/',
            ],
            'Frontend debug artifact scan failed.'
        );
    }

    public function test_no_obvious_secret_echo_in_docker_and_script_entrypoints(): void
    {
        $paths = [
            $this->repoRootPath('docker/php/entrypoint.sh'),
            $this->repoRootPath('docker/queue/entrypoint.sh'),
            base_path('docker/entrypoint.sh'),
            base_path('docker/queue/entrypoint.sh'),
        ];

        $existing = array_values(array_filter($paths, static fn (string $path): bool => is_file($path)));

        if ($existing === []) {
            $this->markTestSkipped('No docker/script entrypoint files are mounted for static secret echo scan.');
        }

        foreach ($existing as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('/echo\s+.*(\$[A-Z_]*(TOKEN|SECRET|PASSWORD|APP_KEY)|bearer\s+)/i', $contents, "Secret echo pattern found in {$path}");
        }
    }
}

