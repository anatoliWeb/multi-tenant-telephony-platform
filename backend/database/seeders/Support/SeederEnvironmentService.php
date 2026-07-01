<?php

namespace Database\Seeders\Support;

use App\Support\TestingDatabaseGuard;
use RuntimeException;

class SeederEnvironmentService
{
    public function __construct(
        protected TestingDatabaseGuard $testingDatabaseGuard
    ) {
    }

    public function environment(): string
    {
        // Artisan may boot with `config('app.env')` still set to the base
        // local value even when `--env=testing` is active, so use Laravel's
        // resolved runtime environment here to keep seed branching accurate.
        return strtolower((string) app()->environment());
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    public function assertSafeTestingDatabase(?string $database, string $context): void
    {
        $this->testingDatabaseGuard->assertSafe($this->environment(), $database, $context);
    }

    public function assertNotProduction(string $context, bool $allowProduction = false): void
    {
        if (! $this->isProduction()) {
            return;
        }

        if ($allowProduction) {
            return;
        }

        throw new RuntimeException("Refusing to run {$context} in production.");
    }
}
