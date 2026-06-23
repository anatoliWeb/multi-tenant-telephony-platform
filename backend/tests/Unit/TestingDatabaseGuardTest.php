<?php

namespace Tests\Unit;

use App\Support\TestingDatabaseGuard;
use RuntimeException;
use Tests\TestCase;

class TestingDatabaseGuardTest extends TestCase
{
    public function test_testing_database_name_with_testing_suffix_passes_guard(): void
    {
        $guard = new TestingDatabaseGuard();

        $guard->assertSafe('testing', 'saas_testing');

        $this->assertTrue(true);
    }

    public function test_unsafe_testing_database_name_throws_exception(): void
    {
        $guard = new TestingDatabaseGuard();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run testing flow against non-testing database');

        $guard->assertSafe('testing', 'saas');
    }
}

