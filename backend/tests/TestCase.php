<?php

namespace Tests;

use App\Services\Tenancy\TenantContext;
use App\Support\TestingDatabaseGuard;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestingDatabaseBootstrap;

abstract class TestCase extends BaseTestCase
{
    private static bool $testingDatabaseBootstrapped = false;

    public function createApplication()
    {
        $testingDatabase = (string) ($_ENV['DB_TEST_DATABASE']
            ?? $_SERVER['DB_TEST_DATABASE']
            ?? getenv('DB_TEST_DATABASE')
            ?? getenv('TEST_DB_DATABASE')
            ?? 'saas_testing');

        // Ensure test process boots with isolated testing database variables
        // before Laravel initializes connections for RefreshDatabase.
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_HOST'] = 'mysql';
        $_SERVER['DB_HOST'] = 'mysql';
        $_ENV['DB_PORT'] = '3306';
        $_SERVER['DB_PORT'] = '3306';
        $_ENV['DB_DATABASE'] = $testingDatabase;
        $_SERVER['DB_DATABASE'] = $testingDatabase;
        $_ENV['DB_TEST_DATABASE'] = $testingDatabase;
        $_SERVER['DB_TEST_DATABASE'] = $testingDatabase;
        $_ENV['DB_USERNAME'] = 'saas';
        $_SERVER['DB_USERNAME'] = 'saas';
        $_ENV['DB_PASSWORD'] = 'secret';
        $_SERVER['DB_PASSWORD'] = 'secret';

        if (! self::$testingDatabaseBootstrapped) {
            // The testing database must start from an empty schema so a
            // partially-finished migration from a previous aborted run cannot
            // poison the next feature-test bootstrap.
            (new TestingDatabaseBootstrap())->reset(
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'],
                $testingDatabase,
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD']
            );

            self::$testingDatabaseBootstrapped = true;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->clear();

        // Keep docs tooling tests backward-compatible in testing by default.
        // Strict docs access suites explicitly override this to false.
        config()->set('api-docs.local_bypass', true);

        // WHY:
        // Web feature tests in this project post directly to auth/profile routes
        // and expect Laravel default behavior without manual CSRF token plumbing.
        $this->withoutMiddleware(PreventRequestForgery::class);

        $activeDatabase = (string) config('database.connections.mysql.database');
        $guard = app(TestingDatabaseGuard::class);

        // Fail fast if a test process points at non-testing database.
        if (! app()->environment('testing')) {
            $this->fail(sprintf('Unsafe test environment detected. APP_ENV=%s', app()->environment()));
        }

        try {
            $guard->assertSafe(app()->environment(), $activeDatabase, 'tests-bootstrap');
        } catch (\RuntimeException $exception) {
            $this->fail($exception->getMessage());
        }
    }
}
