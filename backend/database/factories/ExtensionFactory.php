<?php

namespace Database\Factories;

use App\Enums\Extensions\ExtensionProvisioningStatus;
use App\Enums\Extensions\ExtensionRegistrationStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Extension>
 */
class ExtensionFactory extends Factory
{
    protected $model = Extension::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->create();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'number' => (string) fake()->numberBetween(100, 9999),
            'label' => fake()->words(2, true),
            'status' => ExtensionStatus::Active,
            'provisioning_status' => ExtensionProvisioningStatus::Provisioned,
            'registration_status' => ExtensionRegistrationStatus::Unknown,
            'endpoint_key' => 'extension:'.Str::uuid()->toString(),
            'provider_name' => 'fake',
            'provider_resource_id' => 'endpoint-0001',
            'credential_username' => '100',
            'last_provisioned_at' => now(),
            'created_by' => $creator->getKey(),
            'updated_by' => $creator->getKey(),
            'metadata' => [],
        ];
    }
}
