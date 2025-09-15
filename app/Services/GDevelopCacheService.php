<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class GDevelopCacheService
{
    private int $templateCacheTtl;
    private int $gameStructureCacheTtl;
    private int $validationCacheTtl;
    private int $assetsCacheTtl;
    private bool $cacheEnabled;

    public function __construct()
    {
        $this->cacheEnabled = config('gdevelop.performance.cache_enabled', true);
        $this->templateCacheTtl = config('gdevelop.performance.template_cache_ttl', 3600);
        $this->gameStructureCacheTtl = config('gdevelop.performance.game_structure_cache_ttl', 1800);
        $this->validationCacheTtl = config('gdevelop.performance.validation_cache_ttl', 600);
        $this->assetsCacheTtl = config('gdevelop.performance.assets_cache_ttl', 7200);
    }

    /**
     * Cache GDevelop templates for faster access
     */
    public function cacheTemplate(string $templateName, array $templateData): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $cacheKey = $this->getTemplateCacheKey($templateName);
            
            Cache::put($cacheKey, $templateData, $this->templateCacheTtl);
            
            Log::debug('Cached GDevelop template', [
                'template_name' => $templateName,
                'cache_key' => $cacheKey,
                'ttl' => $this->templateCacheTtl
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to cache template', [
                'template_name' => $templateName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached template data
     */
    public function getCachedTemplate(string $templateName): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            $cacheKey = $this->getTemplateCacheKey($templateName);
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                Log::debug('Retrieved cached template', [
                    'template_name' => $templateName,
                    'cache_key' => $cacheKey
                ]);
            }
            
            return $cachedData;
        } catch (Exception $e) {
            Log::warning('Failed to retrieve cached template', [
                'template_name' => $templateName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Cache common game structures for reuse
     */
    public function cacheGameStructure(string $structureType, array $structureData): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $cacheKey = $this->getGameStructureCacheKey($structureType);
            
            Cache::put($cacheKey, $structureData, $this->gameStructureCacheTtl);
            
            Log::debug('Cached game structure', [
                'structure_type' => $structureType,
                'cache_key' => $cacheKey,
                'ttl' => $this->gameStructureCacheTtl
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to cache game structure', [
                'structure_type' => $structureType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached game structure
     */
    public function getCachedGameStructure(string $structureType): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            $cacheKey = $this->getGameStructureCacheKey($structureType);
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                Log::debug('Retrieved cached game structure', [
                    'structure_type' => $structureType,
                    'cache_key' => $cacheKey
                ]);
            }
            
            return $cachedData;
        } catch (Exception $e) {
            Log::warning('Failed to retrieve cached game structure', [
                'structure_type' => $structureType,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Cache validation results to avoid repeated validation
     */
    public function cacheValidationResult(string $gameJsonHash, array $validationResult): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $cacheKey = $this->getValidationCacheKey($gameJsonHash);
            
            Cache::put($cacheKey, $validationResult, $this->validationCacheTtl);
            
            Log::debug('Cached validation result', [
                'game_json_hash' => $gameJsonHash,
                'cache_key' => $cacheKey,
                'ttl' => $this->validationCacheTtl
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to cache validation result', [
                'game_json_hash' => $gameJsonHash,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached validation result
     */
    public function getCachedValidationResult(string $gameJsonHash): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            $cacheKey = $this->getValidationCacheKey($gameJsonHash);
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                Log::debug('Retrieved cached validation result', [
                    'game_json_hash' => $gameJsonHash,
                    'cache_key' => $cacheKey
                ]);
            }
            
            return $cachedData;
        } catch (Exception $e) {
            Log::warning('Failed to retrieve cached validation result', [
                'game_json_hash' => $gameJsonHash,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Cache asset manifest for faster asset loading
     */
    public function cacheAssetManifest(string $sessionId, array $assetManifest): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $cacheKey = $this->getAssetManifestCacheKey($sessionId);
            
            Cache::put($cacheKey, $assetManifest, $this->assetsCacheTtl);
            
            Log::debug('Cached asset manifest', [
                'session_id' => $sessionId,
                'cache_key' => $cacheKey,
                'ttl' => $this->assetsCacheTtl
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to cache asset manifest', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached asset manifest
     */
    public function getCachedAssetManifest(string $sessionId): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            $cacheKey = $this->getAssetManifestCacheKey($sessionId);
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                Log::debug('Retrieved cached asset manifest', [
                    'session_id' => $sessionId,
                    'cache_key' => $cacheKey
                ]);
            }
            
            return $cachedData;
        } catch (Exception $e) {
            Log::warning('Failed to retrieve cached asset manifest', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Clear all GDevelop caches
     */
    public function clearAllCaches(): void
    {
        try {
            $patterns = [
                'gdevelop:template:*',
                'gdevelop:structure:*',
                'gdevelop:validation:*',
                'gdevelop:assets:*'
            ];
            
            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
            
            Log::info('Cleared all GDevelop caches');
        } catch (Exception $e) {
            Log::error('Failed to clear GDevelop caches', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear cache for specific session
     */
    public function clearSessionCache(string $sessionId): void
    {
        try {
            $cacheKeys = [
                $this->getAssetManifestCacheKey($sessionId),
                // Add other session-specific cache keys as needed
            ];
            
            foreach ($cacheKeys as $cacheKey) {
                Cache::forget($cacheKey);
            }
            
            Log::debug('Cleared session cache', [
                'session_id' => $sessionId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to clear session cache', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStatistics(): array
    {
        try {
            // This is a simplified implementation - in production you might want
            // to use Redis or another cache store that provides better statistics
            return [
                'template_cache_hits' => Cache::get('gdevelop:stats:template_hits', 0),
                'template_cache_misses' => Cache::get('gdevelop:stats:template_misses', 0),
                'structure_cache_hits' => Cache::get('gdevelop:stats:structure_hits', 0),
                'structure_cache_misses' => Cache::get('gdevelop:stats:structure_misses', 0),
                'validation_cache_hits' => Cache::get('gdevelop:stats:validation_hits', 0),
                'validation_cache_misses' => Cache::get('gdevelop:stats:validation_misses', 0),
                'assets_cache_hits' => Cache::get('gdevelop:stats:assets_hits', 0),
                'assets_cache_misses' => Cache::get('gdevelop:stats:assets_misses', 0),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get cache statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Record cache hit for statistics
     */
    public function recordCacheHit(string $cacheType): void
    {
        try {
            $key = "gdevelop:stats:{$cacheType}_hits";
            Cache::increment($key);
        } catch (Exception $e) {
            // Silently fail for statistics
        }
    }

    /**
     * Record cache miss for statistics
     */
    public function recordCacheMiss(string $cacheType): void
    {
        try {
            $key = "gdevelop:stats:{$cacheType}_misses";
            Cache::increment($key);
        } catch (Exception $e) {
            // Silently fail for statistics
        }
    }

    /**
     * Generate cache key for templates
     */
    private function getTemplateCacheKey(string $templateName): string
    {
        return "gdevelop:template:" . md5($templateName);
    }

    /**
     * Generate cache key for game structures
     */
    private function getGameStructureCacheKey(string $structureType): string
    {
        return "gdevelop:structure:" . md5($structureType);
    }

    /**
     * Generate cache key for validation results
     */
    private function getValidationCacheKey(string $gameJsonHash): string
    {
        return "gdevelop:validation:" . $gameJsonHash;
    }

    /**
     * Generate cache key for asset manifests
     */
    private function getAssetManifestCacheKey(string $sessionId): string
    {
        return "gdevelop:assets:" . md5($sessionId);
    }
}