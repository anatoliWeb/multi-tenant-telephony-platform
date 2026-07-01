<?php

namespace Tests\Feature\Seeders;

use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Extension;
use App\Models\Permission;
use App\Models\PhoneNumber;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\SystemTranslation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentSeedBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_local_development_baseline(): void
    {
        $this->seedDatabaseAs('local');

        $platformAdmin = User::where('email', 'platform-admin@test.local')->firstOrFail();
        $platformSupport = User::where('email', 'platform-support@test.local')->firstOrFail();
        $tenantOwner = User::where('email', 'tenant-a-owner@test.local')->firstOrFail();
        $tenantAgent = User::where('email', 'tenant-b-agent@test.local')->firstOrFail();

        $this->assertTrue(Hash::check('password', $platformAdmin->password));
        $this->assertTrue(Hash::check('password', $platformSupport->password));
        $this->assertTrue(Hash::check('password', $tenantOwner->password));
        $this->assertTrue(Hash::check('password', $tenantAgent->password));

        $this->assertGreaterThanOrEqual(3, Tenant::count());
        $this->assertGreaterThanOrEqual(8, User::count());
        $this->assertGreaterThan(0, Role::count());
        $this->assertGreaterThan(0, Permission::count());
        $this->assertGreaterThan(0, SystemSetting::count());
        $this->assertGreaterThan(0, SystemTranslation::count());
        $this->assertGreaterThan(0, Contact::count());
        $this->assertGreaterThan(0, Extension::count());
        $this->assertGreaterThan(0, PhoneNumber::count());
        $this->assertGreaterThan(0, CallLog::count());
    }

    public function test_database_seeder_populates_testing_fixtures(): void
    {
        $this->seedDatabaseAs('testing');

        $this->assertTrue(User::where('email', 'test-platform-admin@test.local')->exists());
        $this->assertTrue(User::where('email', 'test-tenant-owner@test.local')->exists());
        $this->assertTrue(User::where('email', 'test-tenant-admin@test.local')->exists());
        $this->assertTrue(User::where('email', 'test-tenant-agent@test.local')->exists());

        $this->assertSame(3, Tenant::count());
        $this->assertSame(8, User::count());
        $this->assertSame(2, Contact::count());
        $this->assertSame(2, Extension::count());
        $this->assertSame(2, PhoneNumber::count());
        $this->assertSame(2, CallLog::count());
        $this->assertGreaterThan(0, Role::count());
        $this->assertGreaterThan(0, Permission::count());
        $this->assertGreaterThan(0, SystemSetting::count());
        $this->assertGreaterThan(0, SystemTranslation::count());
    }

    public function test_seed_only_logic_is_not_left_under_app_services_seeding(): void
    {
        $seedFiles = glob(app_path('Services/Seeding/*.php')) ?: [];

        $this->assertCount(0, $seedFiles);
    }

    private function seedDatabaseAs(string $environment): void
    {
        $previousEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => $environment);

            app(DatabaseSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }
}
