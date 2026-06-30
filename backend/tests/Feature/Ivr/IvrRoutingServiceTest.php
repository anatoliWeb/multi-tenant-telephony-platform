<?php

namespace Tests\Feature\Ivr;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\IvrMenu;
use App\Services\Ivr\IvrMenuService;
use App\Services\Ivr\IvrRoutingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class IvrRoutingServiceTest extends TestCase
{
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_digit_and_timeout_routes_resolve_tenant_safe_destinations(): void
    {
        $tenant = $this->createTenant('ivr-routing');
        $menu = $this->createActiveMenu($tenant, 'routing-menu');

        app(TenantContext::class)->setTenant($tenant);
        app(IvrMenuService::class)->createOption($menu, [
            'digit' => '1',
            'label' => 'Sales',
            'destination_type' => 'hangup',
            'priority' => 1,
            'is_active' => true,
        ]);

        $plan = app(IvrRoutingService::class)->resolve($menu, '1', 'digit');
        $this->assertSame('digit', $plan['input_type']);
        $this->assertSame('hangup', $plan['destination']['type']);
        $this->assertSame('Sales', $plan['option']['label']);

        $timeoutPlan = app(IvrRoutingService::class)->resolve($menu, null, 'timeout');
        $this->assertSame('timeout', $timeoutPlan['reason']);
        $this->assertSame('hangup', $timeoutPlan['destination']['type']);
    }

    public function test_route_validation_rejects_self_loops_and_nested_loops(): void
    {
        $tenant = $this->createTenant('ivr-routing-loops');
        $sourceMenu = $this->createActiveMenu($tenant, 'source-menu');
        $destinationMenu = $this->createActiveMenu($tenant, 'destination-menu');

        app(TenantContext::class)->setTenant($tenant);

        app(IvrMenuService::class)->createOption($destinationMenu, [
            'digit' => '9',
            'label' => 'Back to source',
            'destination_type' => 'ivr_menu',
            'destination_id' => $sourceMenu->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->expectException(TelephonyConflictException::class);

        app(IvrMenuService::class)->createOption($sourceMenu, [
            'digit' => '1',
            'label' => 'Loop target',
            'destination_type' => 'ivr_menu',
            'destination_id' => $destinationMenu->id,
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    private function createActiveMenu(\App\Models\Tenant $tenant, string $slug): IvrMenu
    {
        return IvrMenu::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'description' => null,
            'status' => 'active',
            'greeting_text' => 'Welcome',
            'greeting_audio_path' => null,
            'repeat_count' => 1,
            'input_timeout_seconds' => 5,
            'max_invalid_attempts' => 3,
            'timeout_action_type' => 'hangup',
            'timeout_destination_type' => null,
            'timeout_destination_id' => null,
            'invalid_action_type' => 'repeat',
            'invalid_destination_type' => null,
            'invalid_destination_id' => null,
            'settings' => [],
            'metadata' => [],
        ]);
    }
}
