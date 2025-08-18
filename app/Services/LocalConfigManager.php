<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LocalConfigManager
{
    private string $configPath;
    private string $configDir;
    private array $defaultConfig;

    public function __construct()
    {
        // Resolve home directory robustly across OS/platforms
        $homeDir = ($_SERVER['HOME'] ?? null)
            ?: ($_SERVER['USERPROFILE'] ?? null)
            ?: getenv('HOME')
            ?: getenv('USERPROFILE')
            ?: ((getenv('HOMEDRIVE') && getenv('HOMEPATH')) ? (getenv('HOMEDRIVE') . getenv('HOMEPATH')) : null)
            ?: base_path();
        $this->configDir = rtrim($homeDir, "\\/") . DIRECTORY_SEPARATOR . '.surrealpilot';
        $this->configPath = $this->configDir . '/config.json';

        $this->defaultConfig = [
            'preferred_provider' => 'openai',
            'api_keys' => [
                'openai' => null,
                'anthropic' => null,
                'gemini' => null,
            ],
            'saas_token' => null,
            'saas_url' => 'https://api.surrealpilot.com',
            'server_port' => 8000,
            'created_at' => now()->toISOString(),
        ];

        $this->ensureConfigExists();
    }

    /**
     * Get the complete configuration
     */
    public function getConfig(): array
    {
        try {
            if (!File::exists($this->configPath)) {
                return $this->defaultConfig;
            }

            $content = File::get($this->configPath);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Invalid JSON in config file, using defaults');
                return $this->defaultConfig;
            }

            // Deep merge with defaults to ensure all keys exist
            return $this->deepMerge($this->defaultConfig, $config);

        } catch (\Exception $e) {
            Log::error('Failed to read config: ' . $e->getMessage());
            return $this->defaultConfig;
        }
    }

    /**
     * Update configuration with new values
     */
    public function updateConfig(array $updates): void
    {
        $currentConfig = $this->getConfig();
        $newConfig = $this->deepMerge($currentConfig, $updates);
        $newConfig['updated_at'] = now()->toISOString();

        $this->saveConfig($newConfig);
    }

    /**
     * Get API keys for all providers
     */
    public function getApiKeys(): array
    {
        $config = $this->getConfig();
        return $config['api_keys'] ?? [];
    }

    /**
     * Set API key for a specific provider
     */
    public function setApiKey(string $provider, ?string $key): void
    {
        $config = $this->getConfig();
        $config['api_keys'][$provider] = $key;
        $this->saveConfig($config);
    }

    /**
     * Get the preferred AI provider
     */
    public function getPreferredProvider(): string
    {
        $config = $this->getConfig();
        return $config['preferred_provider'] ?? 'openai';
    }

    /**
     * Setup Ollama configuration
     */
    public function setupOllama(array $ollamaConfig): void
    {
        $config = $this->getConfig();

        $config['ollama_url'] = $ollamaConfig['url'] ?? 'http://localhost:11434';
        $config['ollama_model'] = $ollamaConfig['model'] ?? 'qwen2.5-coder:7b';
        $config['ollama_enabled'] = $ollamaConfig['enabled'] ?? true;

        $this->saveConfig($config);
    }

    /**
     * Get Ollama configuration
     */
    public function getOllamaConfig(): array
    {
        $config = $this->getConfig();

        return [
            'url' => $config['ollama_url'] ?? 'http://localhost:11434',
            'model' => $config['ollama_model'] ?? 'qwen2.5-coder:7b',
            'enabled' => $config['ollama_enabled'] ?? false,
        ];
    }

    /**
     * Set the preferred AI provider
     */
    public function setPreferredProvider(string $provider): void
    {
        $this->updateConfig(['preferred_provider' => $provider]);
    }

    /**
     * Get SaaS API token
     */
    public function getSaasToken(): ?string
    {
        $config = $this->getConfig();
        return $config['saas_token'] ?? null;
    }

    /**
     * Set SaaS API token
     */
    public function setSaasToken(?string $token): void
    {
        $this->updateConfig(['saas_token' => $token]);
    }

    /**
     * Get current server port
     */
    public function getServerPort(): int
    {
        $config = $this->getConfig();
        return $config['server_port'] ?? 8000;
    }

    /**
     * Set server port (with fallback logic)
     */
    public function setServerPort(int $port): void
    {
        $this->updateConfig(['server_port' => $port]);
    }

    /**
     * Find available port starting from default
     */
    public function findAvailablePort(): int
    {
        $defaultPort = config('nativephp.desktop.server.default_port', 8000);
        $fallbackPort = config('nativephp.desktop.server.fallback_port', 8001);

        // Check if default port is available
        if ($this->isPortAvailable($defaultPort)) {
            $this->setServerPort($defaultPort);
            return $defaultPort;
        }

        // Try fallback port
        if ($this->isPortAvailable($fallbackPort)) {
            $this->setServerPort($fallbackPort);
            return $fallbackPort;
        }

        // Find any available port starting from fallback + 1
        for ($port = $fallbackPort + 1; $port <= $fallbackPort + 100; $port++) {
            if ($this->isPortAvailable($port)) {
                $this->setServerPort($port);
                return $port;
            }
        }

        // If no port found, use fallback anyway (will likely fail but at least we tried)
        $this->setServerPort($fallbackPort);
        return $fallbackPort;
    }

    /**
     * Check if a port is available
     */
    private function isPortAvailable(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if (is_resource($connection)) {
            fclose($connection);
            return false; // Port is in use
        }

        return true; // Port is available
    }

    /**
     * Ensure config directory and file exist
     */
    private function ensureConfigExists(): void
    {
        try {
            // Create directory if it doesn't exist
            if (!File::exists($this->configDir)) {
                File::makeDirectory($this->configDir, 0755, true);
            }

            // Create config file if it doesn't exist
            if (!File::exists($this->configPath)) {
                $this->saveConfig($this->defaultConfig);
            }

        } catch (\Exception $e) {
            Log::error('Failed to ensure config exists: ' . $e->getMessage());
        }
    }

    /**
     * Save configuration to file
     */
    private function saveConfig(array $config): void
    {
        try {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to encode config as JSON: ' . json_last_error_msg());
            }

            File::put($this->configPath, $json);

        } catch (\Exception $e) {
            Log::error('Failed to save config: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function resetConfig(): void
    {
        $this->saveConfig($this->defaultConfig);
    }

    /**
     * Get config file path
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * Check if config file exists
     */
    public function configExists(): bool
    {
        return File::exists($this->configPath);
    }

    /**
     * Deep merge two arrays, preserving nested structure
     */
    private function deepMerge(array $default, array $override): array
    {
        $result = $default;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->deepMerge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
