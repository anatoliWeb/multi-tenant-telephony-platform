<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // WHY:
        // Keep Horizon dashboard access aligned with platform RBAC semantics
        // in all environments (including local/testing), not only non-local.
        Horizon::auth(function (Request $request): bool {
            /** @var User|null $user */
            $user = $request->user();

            return $user?->hasPermission('system.monitoring') ?? false;
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user): bool {
            return $user->hasPermission('system.monitoring');
        });
    }
}
