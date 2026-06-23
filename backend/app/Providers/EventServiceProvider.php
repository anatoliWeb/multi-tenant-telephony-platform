<?php

namespace App\Providers;

use App\Events\Auth\TokenCreated;
use App\Events\Auth\TokenRevoked;
use App\Events\Notifications\NotificationCreated;
use App\Events\Users\UserCreated;
use App\Events\Users\UserUpdated;
use App\Events\Rbac\PermissionChanged;
use App\Events\Rbac\RolePermissionsChanged;
use App\Listeners\Auth\LogTokenCreatedActivity;
use App\Listeners\Auth\LogTokenRevokedActivity;
use App\Listeners\Notifications\LogNotificationCreatedActivity;
use App\Listeners\Rbac\InvalidatePermissionCache;
use App\Listeners\Users\LogUserCreatedActivity;
use App\Listeners\Users\LogUserUpdatedActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider.
 *
 * Registers application events and listeners.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserCreated::class => [
            LogUserCreatedActivity::class,
        ],
        UserUpdated::class => [
            LogUserUpdatedActivity::class,
        ],
        RolePermissionsChanged::class => [
            InvalidatePermissionCache::class,
        ],
        PermissionChanged::class => [
            InvalidatePermissionCache::class,
        ],
        TokenCreated::class => [
            LogTokenCreatedActivity::class,
        ],
        TokenRevoked::class => [
            LogTokenRevokedActivity::class,
        ],
        NotificationCreated::class => [
            LogNotificationCreatedActivity::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
