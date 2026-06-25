<?php

namespace Database\Factories;

use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtensionCredential>
 */
class ExtensionCredentialFactory extends Factory
{
    protected $model = ExtensionCredential::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $extension = Extension::factory()->create(['tenant_id' => $tenant->getKey()]);
        $rotator = User::factory()->create();

        return [
            'tenant_id' => $tenant->getKey(),
            'extension_id' => $extension->getKey(),
            'username' => $extension->number,
            'secret_encrypted' => encrypt('secret-password'),
            'secret_hint' => 'word',
            'version' => 1,
            'rotated_by' => $rotator->getKey(),
            'rotated_at' => now(),
        ];
    }
}
