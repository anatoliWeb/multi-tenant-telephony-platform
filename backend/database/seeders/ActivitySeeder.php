<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Seed demo activity logs.
 *
 * Creates realistic activity timeline for dashboard.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Prevent duplicate seeding
         */
        if (ActivityLog::count() > 0) {
            return;
        }

        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        /**
         * Demo actions pool
         */
        $actions = [
            'create_token' => 'Created API token',
            'delete_token' => 'Deleted API token',
            'update_role' => 'Updated user roles',
            'update_permissions' => 'Updated permissions',
            'login' => 'User logged in',
        ];

        /**
         * Generate activity logs
         */
        for ($i = 0; $i < 30; $i++) {

            $user = $users->random();
            $actionKey = array_rand($actions);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $actionKey,
                'description' => $actions[$actionKey],
                'meta' => [
                    'ip' => '192.168.1.' . rand(1, 255),
                ],
                'created_at' => now()->subMinutes(rand(1, 1440)),
                'updated_at' => now(),
            ]);
        }
    }
}
