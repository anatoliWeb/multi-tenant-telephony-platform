<?php

namespace App\Providers;

use App\Services\Telephony\FakeTelephonyProvider;
use App\Services\Telephony\TelephonyProviderRegistry;
use App\Services\Telephony\TelephonyService;
use Illuminate\Support\ServiceProvider;

class TelephonyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/telephony.php'), 'telephony');

        $this->app->singleton(FakeTelephonyProvider::class, fn () => new FakeTelephonyProvider());

        $this->app->singleton(TelephonyProviderRegistry::class, function ($app): TelephonyProviderRegistry {
            $providers = [];

            if ((bool) config('telephony.providers.fake.enabled', true)) {
                $providers[] = $app->make(FakeTelephonyProvider::class);
            }

            return new TelephonyProviderRegistry($providers);
        });

        $this->app->scoped(TelephonyService::class, function ($app): TelephonyService {
            return new TelephonyService(
                $app->make(TelephonyProviderRegistry::class),
                $app->make(\App\Services\Tenancy\TenantContext::class),
                $app->make(\App\Services\Monitoring\StructuredLogContextService::class),
                $app->make(\App\Services\CallLogs\CallRecordingService::class),
            );
        });
    }
}
