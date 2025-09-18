<?php

namespace App\Providers;

use App\Interfaces\ProfileSerivceInterface;
use App\Services\ProfileService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
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
        $this->app->bind(
            abstract: ProfileSerivceInterface::class,
            concrete: fn(Application $app) => $app->make(ProfileService::class)
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Add limiter general limiter for the lookup endpoint
         **/
        RateLimiter::for('lookup', function (Request $request) {
            return Limit::perMinute(500)->by($request->ip());
        });
    }
}
