<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\SystemTranslation;
use Database\Seeders\settings\SettingsSeeder;
use Database\Seeders\Support\RbacSeedService;
use Database\Seeders\translations\TranslationsSeeder;
use Illuminate\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed();
    }

    /**
     * @return array<string, int>
     */
    public function seed(): array
    {
        $rbacSeed = app(RbacSeedService::class);
        $permissions = $rbacSeed->seedPermissionCatalog();
        $platformRoles = $rbacSeed->seedPlatformRoles();

        $rbacSeed->syncPermissions($platformRoles['platform_super_admin'], $permissions['platform']);
        $rbacSeed->syncPermissions($platformRoles['platform_support'], $permissions['platform_support']);
        $rbacSeed->syncPermissions($platformRoles['admin'], $permissions['platform_admin']);
        $rbacSeed->syncPermissions($platformRoles['manager'], ['users.view', 'users.edit']);
        $rbacSeed->syncPermissions($platformRoles['user'], ['users.view']);

        $this->call([
            SettingsSeeder::class,
            TranslationsSeeder::class,
        ]);

        return [
            'permissions' => Permission::query()->count(),
            'roles' => Role::query()->count(),
            'settings' => SystemSetting::query()->count(),
            'translations' => SystemTranslation::query()->count(),
        ];
    }
}
