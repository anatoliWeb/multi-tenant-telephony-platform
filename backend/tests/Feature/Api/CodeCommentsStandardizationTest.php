<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CodeCommentsStandardizationTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function collectPhpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (! $item->isFile() || strtolower($item->getExtension()) !== 'php') {
                    continue;
                }

                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function extractComments(string $php): array
    {
        $comments = [];
        $tokens = token_get_all($php);

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $comments[] = $token[1];
            }
        }

        return $comments;
    }

    public function test_backend_runtime_comments_do_not_use_cyrillic_technical_text(): void
    {
        $files = $this->collectPhpFiles([
            base_path('app'),
            base_path('config'),
            base_path('routes'),
        ]);

        foreach ($files as $file) {
            $php = (string) file_get_contents($file);
            $comments = $this->extractComments($php);

            foreach ($comments as $comment) {
                $this->assertDoesNotMatchRegularExpression(
                    '/[\x{0400}-\x{04FF}]/u',
                    $comment,
                    "Cyrillic technical comment found in {$file}"
                );
            }
        }
    }

    public function test_backend_runtime_comments_do_not_contain_debug_temporary_noise(): void
    {
        $files = $this->collectPhpFiles([
            base_path('app'),
            base_path('config'),
            base_path('routes'),
        ]);

        $patterns = [
            '/temporary\s+debug/i',
            '/todo\s+debug/i',
            '/debug\s+only/i',
            '/for\s+debug/i',
            '/fixme/i',
        ];

        foreach ($files as $file) {
            $php = (string) file_get_contents($file);
            $comments = $this->extractComments($php);

            foreach ($comments as $comment) {
                foreach ($patterns as $pattern) {
                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $comment,
                        "Debug/noisy comment marker found in {$file}"
                    );
                }
            }
        }
    }

    public function test_critical_files_include_policy_or_why_comments_for_non_obvious_decisions(): void
    {
        $required = [
            base_path('app/Services/Monitoring/StructuredLogContextService.php'),
            base_path('app/Services/Rbac/PermissionCacheService.php'),
            base_path('app/Jobs/Chat/DeliverChatWebhookJob.php'),
            base_path('app/Services/Chat/ChatConversationQueryService.php'),
        ];

        foreach ($required as $file) {
            $this->assertFileExists($file);
            $contents = (string) file_get_contents($file);
            $this->assertStringContainsString('WHY:', $contents, "Expected WHY policy comments in {$file}");
        }

        $apiDocsCandidates = [
            base_path('app/Services/ApiDocsPermissionService.php'),
            base_path('app/Http/Controllers/ApiDocsFilteredSpecController.php'),
        ];

        $found = false;
        foreach ($apiDocsCandidates as $candidate) {
            if (! file_exists($candidate)) {
                continue;
            }

            $contents = (string) file_get_contents($candidate);
            if (str_contains($contents, 'WHY:')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected WHY/policy comment in ApiDocs permission-aware flow files.');
    }

    public function test_comments_do_not_contain_secret_like_sample_values(): void
    {
        $files = $this->collectPhpFiles([
            base_path('app'),
            base_path('config'),
            base_path('routes'),
        ]);

        foreach ($files as $file) {
            $php = (string) file_get_contents($file);
            $comments = $this->extractComments($php);

            foreach ($comments as $comment) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(token|secret|password)\b\s*[:=]\s*[\'"][^\'"]+[\'"]/i',
                    $comment,
                    "Secret-like sample value found in comment in {$file}"
                );
            }
        }
    }

    public function test_backend_runtime_comments_avoid_obvious_redundant_what_noise(): void
    {
        $files = $this->collectPhpFiles([
            base_path('app'),
            base_path('config'),
            base_path('routes'),
        ]);

        $patterns = [
            '/\/\/\s*return\s+response\b/i',
            '/\/\/\s*get\s+user\b/i',
            '/\/\/\s*set\s+variable\b/i',
        ];

        foreach ($files as $file) {
            $php = (string) file_get_contents($file);
            $comments = $this->extractComments($php);

            foreach ($comments as $comment) {
                foreach ($patterns as $pattern) {
                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $comment,
                        "Noisy comment pattern found in {$file}"
                    );
                }
            }
        }
    }
}
