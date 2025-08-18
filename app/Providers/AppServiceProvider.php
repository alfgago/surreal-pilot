<?php

namespace App\Providers;

use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

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

        // Preserve Filament v3-like defaults application-wide (see v4 upgrade guide)
        Table::configureUsing(fn (Table $table) => $table
            ->defaultKeySort(false)
            ->deferFilters(false)
            ->paginationPageOptions([5, 10, 25, 50, 'all']));

        FileUpload::configureUsing(fn (FileUpload $fileUpload) => $fileUpload->visibility('public'));
        ImageColumn::configureUsing(fn (ImageColumn $imageColumn) => $imageColumn->visibility('public'));
        ImageEntry::configureUsing(fn (ImageEntry $imageEntry) => $imageEntry->visibility('public'));
    }
}
