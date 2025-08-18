<?php

namespace App\Console\Commands;

use App\Services\PrismProviderManager;
use Illuminate\Console\Command;

class TestPrismProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prism:test-providers {--provider= : Test a specific provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Prism-PHP provider configuration and availability';

    /**
     * Execute the console command.
     */
    public function handle(PrismProviderManager $providerManager)
    {
        $this->info('Testing Prism-PHP Provider Configuration');
        $this->newLine();

        $specificProvider = $this->option('provider');

        if ($specificProvider) {
            $this->testSpecificProvider($providerManager, $specificProvider);
        } else {
            $this->testAllProviders($providerManager);
        }

        $this->newLine();
        $this->testFallbackLogic($providerManager);
    }

    private function testAllProviders(PrismProviderManager $providerManager)
    {
        $stats = $providerManager->getProviderStats();

        $this->table(
            ['Provider', 'Configured', 'Available', 'Default Model'],
            collect($stats)->map(function ($stat) {
                return [
                    $stat['name'],
                    $stat['configured'] ? '✅' : '❌',
                    $stat['available'] ? '✅' : '❌',
                    $stat['default_model'] ?? 'N/A',
                ];
            })->toArray()
        );
    }

    private function testSpecificProvider(PrismProviderManager $providerManager, string $provider)
    {
        $this->info("Testing provider: {$provider}");
        
        $isAvailable = $providerManager->isProviderAvailable($provider);
        $config = config("prism.providers.{$provider}");

        if ($isAvailable) {
            $this->info("✅ Provider {$provider} is available");
        } else {
            $this->error("❌ Provider {$provider} is not available");
        }

        if ($config) {
            $this->info("Configuration found:");
            $this->line("  Default Model: " . ($config['models']['default'] ?? 'N/A'));
            $this->line("  Available Models: " . implode(', ', $config['models']['available'] ?? []));
        } else {
            $this->error("No configuration found for provider: {$provider}");
        }
    }

    private function testFallbackLogic(PrismProviderManager $providerManager)
    {
        $this->info('Testing Fallback Logic:');
        
        $availableProviders = $providerManager->getAvailableProviders();
        $this->line("Available providers: " . (empty($availableProviders) ? 'None' : implode(', ', $availableProviders)));

        try {
            $resolved = $providerManager->resolveProvider();
            $this->info("✅ Default provider resolved to: {$resolved}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to resolve default provider: " . $e->getMessage());
        }

        // Test fallback with unavailable provider
        try {
            $resolved = $providerManager->resolveProvider('nonexistent');
            $this->info("✅ Fallback from 'nonexistent' resolved to: {$resolved}");
        } catch (\Exception $e) {
            $this->error("❌ Fallback failed: " . $e->getMessage());
        }
    }
}
