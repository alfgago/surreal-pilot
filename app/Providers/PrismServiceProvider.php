<?php

namespace App\Providers;

use App\Services\PrismHelper;
use App\Services\PrismProviderManager;
use Illuminate\Support\ServiceProvider;

class PrismServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PrismProviderManager::class, function ($app) {
            return new PrismProviderManager();
        });

        $this->app->singleton(PrismHelper::class, function ($app) {
            return new PrismHelper($app->make(PrismProviderManager::class));
        });

        // Register aliases for easier access
        $this->app->alias(PrismProviderManager::class, 'prism.manager');
        $this->app->alias(PrismHelper::class, 'prism.helper');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish the configuration file if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/prism.php' => config_path('prism.php'),
            ], 'prism-config');
        }
    }
}