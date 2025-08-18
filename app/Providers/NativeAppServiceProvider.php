<?php

namespace App\Providers;

use App\Services\LocalConfigManager;
use Native\Laravel\Facades\Window;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Illuminate\Support\Facades\Log;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Set up port collision handling
        $this->setupServerPort();
        
        // Configure and open the main window
        $this->openMainWindow();
        
        Log::info('SurrealPilot Desktop application started');
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '256M',
            'max_execution_time' => '300',
        ];
    }

    /**
     * Set up server port with collision handling
     */
    private function setupServerPort(): void
    {
        try {
            $configManager = app(LocalConfigManager::class);
            $availablePort = $configManager->findAvailablePort();
            
            // Update the server configuration
            config(['app.url' => "http://127.0.0.1:{$availablePort}"]);
            
            Log::info("Desktop server will run on port: {$availablePort}");
            
        } catch (\Exception $e) {
            Log::error('Failed to setup server port: ' . $e->getMessage());
        }
    }

    /**
     * Open the main application window
     */
    private function openMainWindow(): void
    {
        $windowConfig = config('nativephp.desktop.window', []);
        
        Window::open()
            ->title($windowConfig['title'] ?? 'SurrealPilot')
            ->width($windowConfig['width'] ?? 1200)
            ->height($windowConfig['height'] ?? 800)
            ->minWidth($windowConfig['min_width'] ?? 800)
            ->minHeight($windowConfig['min_height'] ?? 600)
            ->resizable($windowConfig['resizable'] ?? true)
            ->url('/');
            
        if ($windowConfig['show_dev_tools'] ?? false) {
            Window::current()->showDevTools();
        }
    }
}
