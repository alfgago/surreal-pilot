<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PrismPHP\Prism\Prism;

class PrismProviderManager
{
    /**
     * Get the best available provider based on preference and availability.
     */
    public function resolveProvider(string $preferredProvider = null): string
    {
        $preferredProvider = $preferredProvider ?? Config::get('prism.default');

        // First, try the preferred provider
        if ($this->isProviderAvailable($preferredProvider)) {
            Log::info("Using preferred provider: {$preferredProvider}");
            return $preferredProvider;
        }

        Log::warning("Preferred provider {$preferredProvider} is unavailable, trying fallbacks");

        // If preferred provider is not available, try fallback chain
        $fallbackChain = Config::get('prism.fallback_chain', []);

        foreach ($fallbackChain as $provider) {
            if ($provider === $preferredProvider) {
                continue; // Skip the already tried preferred provider
            }

            if ($this->isProviderAvailable($provider)) {
                Log::info("Using fallback provider: {$provider}");
                return $provider;
            }
        }

        // If no providers are available, throw an exception
        throw new Exception('No AI providers are currently available');
    }

    /**
     * Check if a specific provider is available and properly configured.
     */
    public function isProviderAvailable(string $provider): bool
    {
        // In testing, use config-driven checks to respect tests that toggle availability
        if (app()->environment('testing')) {
            $config = Config::get("prism.providers.{$provider}");
            if ($provider === 'ollama') {
                // Defer to health check path for ollama in tests too
                return $this->checkOllamaAvailability($config ?? []);
            }
            if (empty($config)) {
                Log::debug("Provider {$provider} is not configured");
                return false;
            }
            return match ($provider) {
                'openai' => $this->checkOpenAIAvailability($config),
                'anthropic' => $this->checkAnthropicAvailability($config),
                'gemini' => $this->checkGeminiAvailability($config),
                default => false,
            };
        }

        $providerConfig = Config::get("prism.providers.{$provider}");

        if (!$providerConfig) {
            Log::debug("Provider {$provider} is not configured");
            return false;
        }

        try {
            switch ($provider) {
                case 'openai':
                    return $this->checkOpenAIAvailability($providerConfig);

                case 'anthropic':
                    return $this->checkAnthropicAvailability($providerConfig);

                case 'gemini':
                    return $this->checkGeminiAvailability($providerConfig);

                case 'ollama':
                    return $this->checkOllamaAvailability($providerConfig);

                default:
                    Log::warning("Unknown provider: {$provider}");
                    return false;
            }
        } catch (Exception $e) {
            Log::error("Error checking provider {$provider} availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all available providers.
     */
    public function getAvailableProviders(): array
    {
        $providers = array_keys(Config::get('prism.providers', []));
        $available = [];

        foreach ($providers as $provider) {
            if ($this->isProviderAvailable($provider)) {
                $available[] = $provider;
            }
        }

        return $available;
    }

    /**
     * Get provider configuration with fallback resolution.
     */
    public function getProviderConfig(string $provider): array
    {
        $resolvedProvider = $this->resolveProvider($provider);
        $config = Config::get("prism.providers.{$resolvedProvider}");

        // If BYO keys enabled and present on company, override
        $company = auth()->user()?->currentCompany;
        if ($company && $company->subscriptionPlan?->allow_byo_keys) {
            if ($resolvedProvider === 'openai' && !empty($company->openai_api_key_enc)) {
                $config['api_key'] = decrypt($company->openai_api_key_enc);
            }
            if ($resolvedProvider === 'anthropic' && !empty($company->anthropic_api_key_enc)) {
                $config['api_key'] = decrypt($company->anthropic_api_key_enc);
            }
            if ($resolvedProvider === 'gemini' && !empty($company->gemini_api_key_enc)) {
                $config['api_key'] = decrypt($company->gemini_api_key_enc);
            }
        }

        return $config;
    }

    /**
     * Create a Prism instance with the resolved provider.
     */
    public function createPrismInstance(string $preferredProvider = null): Prism
    {
        $resolvedProvider = $this->resolveProvider($preferredProvider);
        // Vizra orchestrates Prism under the hood; exposing this only for diagnostics
        return Prism::with($resolvedProvider);
    }

    /**
     * Check OpenAI availability.
     */
    private function checkOpenAIAvailability(array $config): bool
    {
        if (empty($config['api_key'])) {
            Log::debug('OpenAI API key is not configured');
            return false;
        }

        // Quick health check - just verify the API key format
        return strlen($config['api_key']) > 20 && str_starts_with($config['api_key'], 'sk-');
    }

    /**
     * Check Anthropic availability.
     */
    private function checkAnthropicAvailability(array $config): bool
    {
        if (empty($config['api_key'])) {
            Log::debug('Anthropic API key is not configured');
            return false;
        }

        // Quick health check - verify the API key format (support both old sk-ant- and newer sk- formats)
        return strlen($config['api_key']) > 20 &&
               (str_starts_with($config['api_key'], 'sk-ant-') || str_starts_with($config['api_key'], 'sk-'));
    }

    /**
     * Check Gemini availability.
     */
    private function checkGeminiAvailability(array $config): bool
    {
        if (empty($config['api_key'])) {
            Log::debug('Gemini API key is not configured');
            return false;
        }

        // Quick health check - just verify the API key is present
        return strlen($config['api_key']) > 10;
    }

    /**
     * Check Ollama availability with health check.
     */
    private function checkOllamaAvailability(array $config): bool
    {
        if (!($config['health_check']['enabled'] ?? true)) {
            // If health check is disabled, assume it's available
            return true;
        }

        try {
            $baseUrl = $config['base_url'] ?? 'http://localhost:11434';
            $healthEndpoint = $config['health_check']['endpoint'] ?? '/api/tags';
            $timeout = $config['health_check']['timeout'] ?? 5;

            $response = Http::timeout($timeout)->get($baseUrl . $healthEndpoint);

            if ($response->successful()) {
                Log::debug('Ollama health check passed');
                return true;
            }

            Log::debug("Ollama health check failed with status: {$response->status()}");
            return false;
        } catch (Exception $e) {
            Log::debug("Ollama health check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get provider statistics and health status.
     */
    public function getProviderStats(): array
    {
        $providers = Config::get('prism.providers', []);
        $stats = [];

        foreach ($providers as $name => $config) {
            $stats[$name] = [
                'name' => $name,
                'available' => $this->isProviderAvailable($name),
                'configured' => !empty($config['api_key']) || $name === 'ollama',
                'default_model' => $config['models']['default'] ?? null,
                'available_models' => $config['models']['available'] ?? [],
            ];
        }

        return $stats;
    }
}
