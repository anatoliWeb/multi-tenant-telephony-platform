<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

/**
 * Seed full RBAC demo data.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Permissions
         */
        $permissions = [

            /*
            |--------------------------------------------------------------------------
            | Admin Access
            |--------------------------------------------------------------------------
            */

            'access_admin',

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            'dashboard.view',

            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign_permissions',

            /*
            |--------------------------------------------------------------------------
            | Permissions
            |--------------------------------------------------------------------------
            */

            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            /*
            |--------------------------------------------------------------------------
            | API Tokens
            |--------------------------------------------------------------------------
            */

            'tokens.view',
            'tokens.create',
            'tokens.edit',
            'tokens.delete',

            /*
            |--------------------------------------------------------------------------
            | API Documentation
            |--------------------------------------------------------------------------
            */

            'api.docs.view',
            'api.docs.view.full',

            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */

            'settings.view',
            'settings.edit',

            /*
            |--------------------------------------------------------------------------
            | Activity / Audit
            |--------------------------------------------------------------------------
            */

            'activity.view',
            'system.monitoring',

            /*
            |--------------------------------------------------------------------------
            | Translations
            |--------------------------------------------------------------------------
            */

            'translations.view',
            'translations.create',
            'translations.edit',
            'translations.delete',

            /*
            |--------------------------------------------------------------------------
            | Notifications
            |--------------------------------------------------------------------------
            */

            'notifications.view',
            'notifications.create',
            'notifications.delete',
            'notifications.preferences',

            /*
            |--------------------------------------------------------------------------
            | Chat
            |--------------------------------------------------------------------------
            */

            'chat.view',
            'chat.create',
            'chat.send',
            'chat.edit',
            'chat.delete',

            /*
            |--------------------------------------------------------------------------
            | Chat Conversations
            |--------------------------------------------------------------------------
            */

            'chat.conversations.view',
            'chat.conversations.create',
            'chat.conversations.edit',
            'chat.conversations.close',
            'chat.conversations.archive',
            'chat.conversations.delete',

            /*
            |--------------------------------------------------------------------------
            | Chat Participants
            |--------------------------------------------------------------------------
            */

            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',

            /*
            |--------------------------------------------------------------------------
            | Chat Attachments
            |--------------------------------------------------------------------------
            */

            'chat.attachments.view',
            'chat.attachments.upload',
            'chat.attachments.download',
            'chat.attachments.delete',

            /*
            |--------------------------------------------------------------------------
            | Chat Admin / Monitoring
            |--------------------------------------------------------------------------
            */

            'chat.admin.view',
            'chat.admin.reply',
            'chat.admin.moderate',
            'chat.admin.delete_messages',
            'chat.admin.close_conversations',
            'chat.admin.view_metadata',

            /*
            |--------------------------------------------------------------------------
            | Chat External API
            |--------------------------------------------------------------------------
            */

            'chat.external_api.use',
            'chat.external_api.manage',
            'chat.external_api.view_logs',

            /*
            |--------------------------------------------------------------------------
            | Chat Webhooks
            |--------------------------------------------------------------------------
            */

            'chat.webhooks.view',
            'chat.webhooks.create',
            'chat.webhooks.edit',
            'chat.webhooks.delete',
            'chat.webhooks.manage',
            'chat.webhooks.view_deliveries',
            'chat.webhooks.retry_deliveries',
        ];
        
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm],
                ['description' => ucfirst(str_replace('.', ' ', $perm))]
            );
        }

        // WHY:
        // Permissions use entity.action format for consistency
        // and easier scaling across modules.
        Permission::where('name', 'like', 'admin.%')->delete();

        /**
         * Roles
         */
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator']
        );

        $managerRole = Role::updateOrCreate(
            ['name' => 'manager'],
            ['description' => 'Manager']
        );

        $userRole = Role::updateOrCreate(
            ['name' => 'user'],
            ['description' => 'User']
        );

        /**
         * Assign permissions to roles
         */
        // WHY:
        // Admin must receive full permission set so newly added capabilities
        // (including roles.* actions) are immediately available in UI and API.
        $adminRole->permissions()->sync(Permission::pluck('id'));

        // Manager: can view and edit users.
        $managerRole->permissions()->sync(
            Permission::whereIn('name', [
                'users.view',
                'users.edit',
            ])->pluck('id')
        );

        // User: read-only access to users.
        $userRole->permissions()->sync(
            Permission::whereIn('name', [
                'users.view',
            ])->pluck('id')
        );

        /**
         * Admin user
         */
        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        $admin->roles()->sync([$adminRole->id]);

        /**
         * Create demo users
         *
         * WHY:
         * Faker makes demo data look more natural than user1/user2 records.
         * This helps test admin tables, filters, pagination, roles,
         * permissions and token screens with realistic-looking data.
         */
        $faker = Faker::create('en_US');

        /**
         * WHY:
         * Fixed seed keeps generated demo users stable between fresh seed runs.
         * Without this, every seed run would create different emails
         * and updateOrCreate would keep adding new users.
         */
        $faker->seed(20260513);

        $usersViewPermission = Permission::where('name', 'users.view')->first();

        /**
         * Create more than 15 realistic demo users.
         */
        for ($i = 1; $i <= 30; $i++) {
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();

            $email = strtolower(
                $firstName . '.' . $lastName . $i . '@test.com'
            );

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$firstName} {$lastName}",
                    'password' => Hash::make('password'),
                ]
            );

            /**
             * Role distribution:
             * - a few admins
             * - several managers
             * - most users as regular users
             */
            $role = match (true) {
                $i <= 3 => $adminRole,
                $i <= 10 => $managerRole,
                default => $userRole,
            };

            $user->roles()->sync([$role->id]);

            /**
             * Direct permissions are attached only to some users
             * to make permission badges and detail pages more realistic.
             */
            if ($usersViewPermission && $i % 4 === 0) {
                $user->permissions()->syncWithoutDetaching([
                    $usersViewPermission->id,
                ]);
            }

            /**
             * Create demo personal access tokens.
             *
             * Token count is intentionally different per user
             * so token tables do not look artificially identical.
             */
            $tokenCount = $faker->numberBetween(1, 3);

            for ($t = 1; $t <= $tokenCount; $t++) {
                $user->createToken("demo_token_{$i}_{$t}");
            }
        }
    }
}
