<?php

namespace Tests\Feature\Seeders;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Seeding\PerformanceSeedService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_seeder_is_idempotent_and_does_not_create_demo_data(): void
    {
        $coreSeeder = app(CoreSeeder::class);

        $beforeCounts = [
            'users' => User::count(),
            'tenants' => Tenant::count(),
            'memberships' => TenantMembership::count(),
        ];

        $firstReport = $coreSeeder->seed();

        $this->assertSame($beforeCounts['users'], User::count());
        $this->assertSame($beforeCounts['tenants'], Tenant::count());
        $this->assertSame($beforeCounts['memberships'], TenantMembership::count());
        $this->assertSame(5, Role::where('scope', 'platform')->count());
        $this->assertGreaterThan(0, Permission::where('scope', 'platform')->count());
        $this->assertGreaterThan(0, Permission::where('scope', 'tenant')->count());

        $secondReport = $coreSeeder->seed();

        $this->assertSame($firstReport, $secondReport);
        $this->assertSame($firstReport['permissions'], Permission::count());
        $this->assertSame($firstReport['roles'], Role::count());
        $this->assertSame($firstReport['settings'], \App\Models\SystemSetting::count());
        $this->assertSame($firstReport['translations'], \App\Models\SystemTranslation::count());
    }

    public function test_demo_seeder_creates_deterministic_tenant_scenarios_and_is_idempotent(): void
    {
        app(CoreSeeder::class)->seed();

        $demoSeeder = app(DemoSeeder::class);
        $firstReport = $demoSeeder->seed();

        $countsBefore = $this->snapshotDemoCounts();

        $defaultTenant = Tenant::where('slug', 'default-tenant')->firstOrFail();
        $secondaryTenant = Tenant::where('slug', 'secondary-tenant')->firstOrFail();
        $suspendedTenant = Tenant::where('slug', 'suspended-tenant')->firstOrFail();

        $this->assertSame(TenantStatus::Active->value, $defaultTenant->status->value);
        $this->assertSame(TenantStatus::Active->value, $secondaryTenant->status->value);
        $this->assertSame(TenantStatus::Suspended->value, $suspendedTenant->status->value);

        $platformAdmin = User::where('email', 'platform-admin@test.local')->firstOrFail();
        $platformSupport = User::where('email', 'platform-support@test.local')->firstOrFail();
        $multiTenantUser = User::where('email', 'multi-tenant-user@test.local')->firstOrFail();
        $suspendedMembershipUser = User::where('email', 'suspended-membership@test.local')->firstOrFail();
        $observerUser = User::where('email', 'tenant-a-observer@test.local')->firstOrFail();

        $this->assertTrue(Hash::check('password', $platformAdmin->password));
        $this->assertTrue(Hash::check('password', $platformSupport->password));
        $this->assertSame(0, $platformAdmin->tenantMemberships()->count());
        $this->assertSame(0, $platformSupport->tenantMemberships()->count());
        $this->assertSame(0, User::where('email', 'no-membership@test.local')->count());
        $this->assertGreaterThan(0, Contact::count());
        $this->assertGreaterThan(0, ContactPhone::count());
        $this->assertGreaterThan(0, ContactTag::count());

        $multiTenantMemberships = $multiTenantUser->tenantMemberships()->orderBy('tenant_id')->get();
        $this->assertCount(2, $multiTenantMemberships);
        $this->assertTrue($multiTenantMemberships->every(fn (TenantMembership $membership): bool => $membership->status->value === TenantMembershipStatus::Active->value));

        $multiTenantRoles = $multiTenantUser->roles()->get()->keyBy('pivot.tenant_id');
        $this->assertSame('tenant_owner', $multiTenantRoles[$defaultTenant->id]->name);
        $this->assertSame('agent', $multiTenantRoles[$secondaryTenant->id]->name);

        $this->assertSame(TenantMembershipStatus::Suspended->value, $suspendedMembershipUser->tenantMemberships()->firstOrFail()->status->value);
        $this->assertSame('custom_observer', $observerUser->roles()->firstOrFail()->name);
        $this->assertSame(1, ContactPhone::query()->where('tenant_id', $defaultTenant->id)->where('normalized_number', '+15550009999')->count());
        $this->assertSame(1, ContactPhone::query()->where('tenant_id', $secondaryTenant->id)->where('normalized_number', '+15550009999')->count());
        $this->assertSame(1, PhoneNumber::query()->where('tenant_id', $defaultTenant->id)->where('normalized_number', '+15550001001')->count());
        $this->assertSame(1, PhoneNumber::query()->where('tenant_id', $secondaryTenant->id)->where('normalized_number', '+15550001001')->count());
        $this->assertGreaterThanOrEqual(4, PhoneNumber::query()->where('tenant_id', $defaultTenant->id)->count());
        $this->assertGreaterThanOrEqual(2, PhoneNumber::query()->where('tenant_id', $defaultTenant->id)->where('is_primary', true)->count());

        $tenantOwnerRoles = Role::query()
            ->where('scope', 'tenant')
            ->where('name', 'tenant_owner')
            ->pluck('tenant_id');

        $this->assertCount(3, $tenantOwnerRoles);
        $this->assertSame(3, $tenantOwnerRoles->unique()->count());

        $demoSeeder->seed();
        $countsAfter = $this->snapshotDemoCounts();

        $this->assertSame($countsBefore, $countsAfter);
        $this->assertSame($firstReport, $demoSeeder->seed());
    }

    public function test_test_seeder_refuses_non_testing_environment(): void
    {
        $previousEnv = config('app.env');
        config(['app.env' => 'production']);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('TestSeeder may only run in the testing environment.');

            app(TestSeeder::class)->run();
        } finally {
            config(['app.env' => $previousEnv]);
        }
    }

    public function test_performance_seeder_refuses_production_and_is_repeatable(): void
    {
        $previousEnv = config('app.env');
        config(['app.env' => 'production']);

        try {
            $this->expectException(RuntimeException::class);
            app(PerformanceSeedService::class)->seed(1, 1, false);
        } finally {
            config(['app.env' => $previousEnv]);
        }

        $service = app(PerformanceSeedService::class);
        $firstReport = $service->seed(2, 3, false);
        $secondReport = $service->seed(2, 3, false);

        $this->assertSame(2, $firstReport['tenants']);
        $this->assertSame(6, $firstReport['users']);
        $this->assertSame(6, $firstReport['memberships']);
        $this->assertSame(6, $firstReport['role_assignments']);
        $this->assertSame($firstReport, $secondReport);
    }

    /**
     * @return array{tenants:int,users:int,memberships:int,roles:int,permissions:int}
     */
    private function snapshotDemoCounts(): array
    {
        return [
            'tenants' => Tenant::count(),
            'users' => User::count(),
            'memberships' => TenantMembership::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ];
    }
}
