<?php

namespace App\Providers;

use App\Services\TeamContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TeamContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('agent-api', function (Request $request): Limit {
            $agent = app()->bound('agent') ? app('agent') : null;

            return Limit::perMinute(60)->by($agent?->id ?: $request->ip());
        });

        RateLimiter::for('heartbeat', function (Request $request): Limit {
            $agent = app()->bound('agent') ? app('agent') : null;

            return Limit::perMinute(30)->by('heartbeat:'.$agent?->id ?: $request->ip());
        });
    }
}
