<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4('MongoDB\\Laravel\\', base_path('vendor/mongodb/laravel-mongodb/src'));
        $loader->addPsr4('MongoDB\\', base_path('vendor/mongodb/mongodb/src'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\DB::extend('mongodb', function ($config, $name) {
            $config['name'] = $name;
            return new \MongoDB\Laravel\Connection($config);
        });

        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        // Configure Rate Limiting
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for API endpoints
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Authenticated users: 120 requests per minute
            // Guest users: 60 requests per minute
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(60)->by($request->ip());
        });

        // Strict limit for login attempts (prevent brute force)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Moderate limit for user invitations (prevent spam)
        RateLimiter::for('invitations', function (Request $request) {
            return $request->user()
                ? Limit::perHour(20)->by($request->user()->id)
                : Limit::perHour(5)->by($request->ip());
        });

        // Higher limit for admin dashboard stats
        RateLimiter::for('dashboard', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(180)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}