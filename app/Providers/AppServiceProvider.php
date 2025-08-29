<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register LocalConfigManager as singleton for desktop app
        $this->app->singleton(\App\Services\LocalConfigManager::class, function ($app) {
            return new \App\Services\LocalConfigManager();
        });

        // Ensure Unreal services are registered and available for tests/features
        $this->app->singleton(\App\Services\UnrealMcpManager::class, function ($app) {
            return new \App\Services\UnrealMcpManager();
        });

        $this->app->singleton(\App\Services\PlayCanvasMcpManager::class, function ($app) {
            return new \App\Services\PlayCanvasMcpManager();
        });

        $this->app->singleton(\App\Services\OnDemandMcpManager::class, function ($app) {
            return new \App\Services\OnDemandMcpManager(
                $app->make(\App\Services\PlayCanvasMcpManager::class)
            );
        });

        // Core services temporarily disabled due to autoload issues
        // Will be re-enabled once the autoload issue is resolved
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiting by plan (per company)
        \Illuminate\Support\Facades\RateLimiter::for('chat', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $company = $user?->currentCompany;
            $plan = $company?->plan ?? 'starter';
            $limits = [
                'starter' => [60, 1],    // 60 per minute
                'pro' => [180, 1],       // 180 per minute
                'enterprise' => [600, 1] // 600 per minute
            ];
            [$max, $per] = $limits[$plan] ?? $limits['starter'];
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($max)->by('company:'.$company?->id ?: 'guest');
        });

        // API rate limiting (general)
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $company = $user?->currentCompany;
            $plan = $company?->plan ?? 'starter';
            $limits = [
                'starter' => [60, 1],    // 60 per minute
                'pro' => [180, 1],       // 180 per minute
                'enterprise' => [600, 1] // 600 per minute
            ];
            [$max, $per] = $limits[$plan] ?? $limits['starter'];
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($max)->by('api:company:'.$company?->id ?: 'guest');
        });

        // Configure Inertia
        Inertia::share([
            'auth' => function () {
                return [
                    'user' => auth()->user() ? [
                        'id' => auth()->user()->id,
                        'name' => auth()->user()->name,
                        'email' => auth()->user()->email,
                        'current_company_id' => auth()->user()->current_company_id,
                    ] : null,
                ];
            },
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'error' => session('error'),
                ];
            },
        ]);
    }
}
