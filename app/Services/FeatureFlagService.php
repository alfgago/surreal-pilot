<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeatureFlagService
{
    /**
     * Cache key prefix for feature flags.
     */
    protected const CACHE_PREFIX = 'feature_flags:';

    /**
     * Cache TTL for feature flags (in seconds).
     */
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if GDevelop integration is enabled.
     */
    public function isGDevelopEnabled(): bool
    {
        return $this->getCachedFlag('gdevelop_enabled', function () {
            return config('gdevelop.enabled', false) && 
                   config('gdevelop.engines.gdevelop_enabled', false);
        });
    }

    /**
     * Check if PlayCanvas integration is enabled.
     */
    public function isPlayCanvasEnabled(): bool
    {
        return $this->getCachedFlag('playcanvas_enabled', function () {
            return config('gdevelop.engines.playcanvas_enabled', true);
        });
    }

    /**
     * Check if any game engine is enabled.
     */
    public function hasAnyEngineEnabled(): bool
    {
        return $this->isGDevelopEnabled() || $this->isPlayCanvasEnabled();
    }

    /**
     * Get the primary enabled engine.
     */
    public function getPrimaryEngine(): ?string
    {
        if ($this->isGDevelopEnabled() && !$this->isPlayCanvasEnabled()) {
            return 'gdevelop';
        }

        if ($this->isPlayCanvasEnabled() && !$this->isGDevelopEnabled()) {
            return 'playcanvas';
        }

        // Both enabled or both disabled
        return null;
    }

    /**
     * Get all enabled engines.
     */
    public function getEnabledEngines(): array
    {
        $engines = [];

        if ($this->isGDevelopEnabled()) {
            $engines[] = 'gdevelop';
        }

        if ($this->isPlayCanvasEnabled()) {
            $engines[] = 'playcanvas';
        }

        return $engines;
    }

    /**
     * Check if a specific GDevelop feature is enabled.
     */
    public function isGDevelopFeatureEnabled(string $feature): bool
    {
        if (!$this->isGDevelopEnabled()) {
            return false;
        }

        return $this->getCachedFlag("gdevelop_feature_{$feature}", function () use ($feature) {
            return config("gdevelop.features.{$feature}", false);
        });
    }

    /**
     * Get engine configuration summary.
     */
    public function getEngineConfigurationSummary(): array
    {
        return [
            'gdevelop' => [
                'enabled' => $this->isGDevelopEnabled(),
                'features' => $this->getGDevelopEnabledFeatures(),
            ],
            'playcanvas' => [
                'enabled' => $this->isPlayCanvasEnabled(),
            ],
            'primary_engine' => $this->getPrimaryEngine(),
            'enabled_engines' => $this->getEnabledEngines(),
            'has_any_engine' => $this->hasAnyEngineEnabled(),
        ];
    }

    /**
     * Get enabled GDevelop features.
     */
    public function getGDevelopEnabledFeatures(): array
    {
        if (!$this->isGDevelopEnabled()) {
            return [];
        }

        $features = config('gdevelop.features', []);
        return array_keys(array_filter($features));
    }

    /**
     * Validate engine configuration and return issues.
     */
    public function validateEngineConfiguration(): array
    {
        $issues = [];
        $warnings = [];

        if (!$this->hasAnyEngineEnabled()) {
            $issues[] = 'No game engines are enabled. Enable at least one engine (GDevelop or PlayCanvas).';
        }

        if ($this->isGDevelopEnabled() && $this->isPlayCanvasEnabled()) {
            $warnings[] = 'Both GDevelop and PlayCanvas are enabled. Consider using one primary engine for better user experience.';
        }

        if ($this->isGDevelopEnabled()) {
            // Check GDevelop-specific configuration
            if (!config('gdevelop.cli_path')) {
                $issues[] = 'GDevelop CLI path is not configured. Set GDEVELOP_CLI_PATH in your environment.';
            }

            if (!config('gdevelop.templates_path')) {
                $issues[] = 'GDevelop templates path is not configured. Set GDEVELOP_TEMPLATES_PATH in your environment.';
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Clear feature flag cache.
     */
    public function clearCache(): void
    {
        $keys = [
            'gdevelop_enabled',
            'playcanvas_enabled',
        ];

        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }

        // Clear GDevelop feature flags
        $features = array_keys(config('gdevelop.features', []));
        foreach ($features as $feature) {
            Cache::forget(self::CACHE_PREFIX . "gdevelop_feature_{$feature}");
        }

        Log::info('Feature flag cache cleared');
    }

    /**
     * Get a cached feature flag value.
     */
    protected function getCachedFlag(string $key, callable $callback): bool
    {
        // Skip caching in testing environment to avoid interference
        if (app()->environment('testing')) {
            try {
                return (bool) $callback();
            } catch (\Exception $e) {
                Log::warning("Failed to evaluate feature flag: {$e->getMessage()}");
                return false;
            }
        }

        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($callback) {
            try {
                return (bool) $callback();
            } catch (\Exception $e) {
                Log::warning("Failed to evaluate feature flag: {$e->getMessage()}");
                return false;
            }
        });
    }

    /**
     * Force refresh a specific feature flag.
     */
    public function refreshFlag(string $key): bool
    {
        Cache::forget(self::CACHE_PREFIX . $key);
        
        switch ($key) {
            case 'gdevelop_enabled':
                return $this->isGDevelopEnabled();
            case 'playcanvas_enabled':
                return $this->isPlayCanvasEnabled();
            default:
                return false;
        }
    }

    /**
     * Get feature flag status for debugging.
     */
    public function getDebugInfo(): array
    {
        return [
            'gdevelop' => [
                'config_enabled' => config('gdevelop.enabled', false),
                'engine_enabled' => config('gdevelop.engines.gdevelop_enabled', false),
                'final_enabled' => $this->isGDevelopEnabled(),
                'env_var' => env('GDEVELOP_ENABLED'),
            ],
            'playcanvas' => [
                'engine_enabled' => config('gdevelop.engines.playcanvas_enabled', true),
                'final_enabled' => $this->isPlayCanvasEnabled(),
                'env_var' => env('PLAYCANVAS_ENABLED'),
            ],
            'cache_keys' => [
                'gdevelop_enabled' => Cache::has(self::CACHE_PREFIX . 'gdevelop_enabled'),
                'playcanvas_enabled' => Cache::has(self::CACHE_PREFIX . 'playcanvas_enabled'),
            ],
        ];
    }
}