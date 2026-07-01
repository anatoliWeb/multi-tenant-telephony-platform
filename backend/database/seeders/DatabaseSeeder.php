<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CoreSeeder::class);

        if (app()->environment('production')) {
            return;
        }

        if (app()->environment('testing')) {
            // Testing resets must always land on the deterministic fixture set
            // so feature tests never depend on stale config state.
            $this->call(TestSeeder::class);
        } elseif (app()->environment('local')) {
            // Local developer resets should include the full demo baseline so
            // the admin UI starts with known users, tenants, and telephony data.
            $this->call(DemoSeeder::class);
        }
    }
}
