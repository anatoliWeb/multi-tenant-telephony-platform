<?php

namespace App\Support;

use RuntimeException;

class TestingDatabaseGuard
{
    public function isSafeTestingDatabaseName(?string $database): bool
    {
        $value = strtolower(trim((string) $database));
        if ($value === '') {
            return false;
        }

        return str_contains($value, 'test');
    }

    public function assertSafe(string $appEnv, ?string $database, ?string $context = null): void
    {
        if (strtolower($appEnv) !== 'testing') {
            return;
        }

        if ($this->isSafeTestingDatabaseName($database)) {
            return;
        }

        $suffix = $context ? " ({$context})" : '';

        throw new RuntimeException(sprintf(
            'Refusing to run testing flow against non-testing database%s: %s',
            $suffix,
            (string) $database
        ));
    }
}

