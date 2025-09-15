<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GDevelopPreviewService
{
    private string $sessionsPath;
    private string $previewsPath;
    private GDevelopRuntimeService $runtimeService;
    private int $cacheTimeout;

    public function __construct(GDevelopRuntimeService $runtimeService)
    {
        $this->runtimeService = $runtimeService;
        $this->sessionsPath = storage_path(config('gdevelop.sessions_path', 'gdevelop/sessions'));
        $this->previewsPath = $this->sessionsPath . DIRECTORY_SEPARATOR . 'previews';
        $this->cacheTimeout = config('gdevelop.preview_timeout', 120);
        
        // Ensure directories exist
        if (!is_dir($this->previewsPath)) {
            mkdir($this->previewsPath, 0755, true);
        }
    }

    /**
     * Generate HTML5 preview for a GDevelop game
     */
    public function generatePreview(string $sessionId, array $gameJson): PreviewGenerationResult
    {
        try {
            Log::info('Generating GDevelop preview', [
                'session_id' => $sessionId,
                'game_name' => $gameJson['properties']['name'] ?? 'Unknown'
            ]);

            // Create session-specific preview directory
            $previewPath = $this->getPreviewPath($sessionId);
            if (!is_dir($previewPath)) {
                mkdir($previewPath, 0755, true);
            }

            // Save game JSON for CLI processing
            $gameJsonPath = $this->saveGameJson($sessionId, $gameJson);

            // Build preview using runtime service
            $buildResult = $this->runtimeService->buildPreview($sessionId, $gameJsonPath);

            if (!$buildResult->success) {
                Log::error('Preview build failed', [
                    'session_id' => $sessionId,
                    'error' => $buildResult->error
                ]);

                return new PreviewGenerationResult(
                    success: false,
                    previewUrl: null,
                    previewPath: null,
                    indexPath: null,
                    error: $buildResult->error,
                    buildTime: $buildResult->buildTime,
                    cached: false
                );
            }

            // Verify index.html exists
            $indexPath = $buildResult->previewPath . DIRECTORY_SEPARATOR . 'index.html';
            if (!file_exists($indexPath)) {
                Log::error('Preview index.html not found', [
                    'session_id' => $sessionId,
                    'expected_path' => $indexPath
                ]);

                return new PreviewGenerationResult(
                    success: false,
                    previewUrl: null,
                    previewPath: $buildResult->previewPath,
                    indexPath: null,
                    error: 'Preview index.html not generated',
                    buildTime: $buildResult->buildTime,
                    cached: false
                );
            }

            // Generate preview URL
            $previewUrl = $this->generatePreviewUrl($sessionId);

            // Cache preview metadata
            $this->cachePreviewMetadata($sessionId, [
                'preview_path' => $buildResult->previewPath,
                'index_path' => $indexPath,
                'build_time' => $buildResult->buildTime,
                'game_name' => $gameJson['properties']['name'] ?? 'Unknown Game'
            ]);

            Log::info('GDevelop preview generated successfully', [
                'session_id' => $sessionId,
                'preview_url' => $previewUrl,
                'build_time' => $buildResult->buildTime
            ]);

            return new PreviewGenerationResult(
                success: true,
                previewUrl: $previewUrl,
                previewPath: $buildResult->previewPath,
                indexPath: $indexPath,
                error: null,
                buildTime: $buildResult->buildTime,
                cached: false
            );

        } catch (Exception $e) {
            Log::error('Preview generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new PreviewGenerationResult(
                success: false,
                previewUrl: null,
                previewPath: null,
                indexPath: null,
                error: $e->getMessage(),
                buildTime: time(),
                cached: false
            );
        }
    }

    /**
     * Serve preview file with proper MIME types
     */
    public function servePreviewFile(string $sessionId, string $filePath = 'index.html'): Response|BinaryFileResponse
    {
        try {
            // Get cached preview metadata
            $metadata = $this->getCachedPreviewMetadata($sessionId);
            if (!$metadata) {
                return response('Preview not found or expired', 404);
            }

            // Construct full file path
            $fullPath = $metadata['preview_path'] . DIRECTORY_SEPARATOR . $filePath;
            
            // Security check - ensure file is within preview directory
            $realPath = realpath($fullPath);
            $previewDir = realpath($metadata['preview_path']);
            
            if (!$realPath || !str_starts_with($realPath, $previewDir)) {
                Log::warning('Attempted access to file outside preview directory', [
                    'session_id' => $sessionId,
                    'requested_path' => $filePath,
                    'full_path' => $fullPath
                ]);
                return response('File not found', 404);
            }

            if (!file_exists($realPath)) {
                return response('File not found', 404);
            }

            // Determine MIME type
            $mimeType = $this->getMimeType($realPath);
            
            // Set appropriate headers for caching and MIME type
            $headers = [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
                'X-Preview-Session' => $sessionId,
                'X-Build-Time' => $metadata['build_time']
            ];

            // For HTML files, add no-cache headers to ensure dynamic reloading
            if ($mimeType === 'text/html') {
                $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
                $headers['Pragma'] = 'no-cache';
                $headers['Expires'] = '0';
            }

            Log::debug('Serving preview file', [
                'session_id' => $sessionId,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => filesize($realPath)
            ]);

            return response()->file($realPath, $headers);

        } catch (Exception $e) {
            Log::error('Failed to serve preview file', [
                'session_id' => $sessionId,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Refresh preview by regenerating it
     */
    public function refreshPreview(string $sessionId, array $gameJson): PreviewGenerationResult
    {
        try {
            Log::info('Refreshing GDevelop preview', [
                'session_id' => $sessionId
            ]);

            // Clear cached metadata
            $this->clearPreviewCache($sessionId);

            // Clean up old preview files
            $this->cleanupPreviewFiles($sessionId);

            // Generate new preview
            return $this->generatePreview($sessionId, $gameJson);

        } catch (Exception $e) {
            Log::error('Failed to refresh preview', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return new PreviewGenerationResult(
                success: false,
                previewUrl: null,
                previewPath: null,
                indexPath: null,
                error: $e->getMessage(),
                buildTime: time(),
                cached: false
            );
        }
    }

    /**
     * Check if preview exists and is valid
     */
    public function previewExists(string $sessionId): bool
    {
        $metadata = $this->getCachedPreviewMetadata($sessionId);
        if (!$metadata) {
            return false;
        }

        $indexPath = $metadata['index_path'] ?? null;
        return $indexPath && file_exists($indexPath);
    }

    /**
     * Get preview URL for a session
     */
    public function getPreviewUrl(string $sessionId): ?string
    {
        if (!$this->previewExists($sessionId)) {
            return null;
        }

        return $this->generatePreviewUrl($sessionId);
    }

    /**
     * Get preview metadata
     */
    public function getPreviewMetadata(string $sessionId): ?array
    {
        return $this->getCachedPreviewMetadata($sessionId);
    }

    /**
     * Clean up old preview files for a session
     */
    public function cleanupPreviewFiles(string $sessionId): bool
    {
        try {
            $previewPath = $this->getPreviewPath($sessionId);
            
            if (is_dir($previewPath)) {
                $this->deleteDirectory($previewPath);
                Log::info('Cleaned up preview files', [
                    'session_id' => $sessionId,
                    'path' => $previewPath
                ]);
            }

            return true;

        } catch (Exception $e) {
            Log::error('Failed to cleanup preview files', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clean up all expired previews
     */
    public function cleanupExpiredPreviews(): int
    {
        $cleaned = 0;
        
        try {
            if (!is_dir($this->previewsPath)) {
                return 0;
            }

            $directories = scandir($this->previewsPath);
            $expireTime = time() - ($this->cacheTimeout * 60); // Convert minutes to seconds

            foreach ($directories as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $dirPath = $this->previewsPath . DIRECTORY_SEPARATOR . $dir;
                if (is_dir($dirPath)) {
                    $lastModified = filemtime($dirPath);
                    
                    if ($lastModified < $expireTime) {
                        $this->deleteDirectory($dirPath);
                        $this->clearPreviewCache($dir);
                        $cleaned++;
                        
                        Log::info('Cleaned up expired preview', [
                            'session_id' => $dir,
                            'last_modified' => date('Y-m-d H:i:s', $lastModified)
                        ]);
                    }
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup expired previews', [
                'error' => $e->getMessage()
            ]);
        }

        return $cleaned;
    }

    /**
     * Get preview path for a session
     */
    private function getPreviewPath(string $sessionId): string
    {
        return $this->previewsPath . DIRECTORY_SEPARATOR . $sessionId;
    }

    /**
     * Save game JSON for preview generation
     */
    private function saveGameJson(string $sessionId, array $gameJson): string
    {
        $sessionPath = $this->sessionsPath . DIRECTORY_SEPARATOR . $sessionId;
        
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0755, true);
        }

        $gameJsonPath = $sessionPath . DIRECTORY_SEPARATOR . 'game.json';
        file_put_contents($gameJsonPath, json_encode($gameJson, JSON_PRETTY_PRINT));

        return $gameJsonPath;
    }

    /**
     * Generate preview URL
     */
    private function generatePreviewUrl(string $sessionId): string
    {
        try {
            return route('gdevelop.preview.serve', ['sessionId' => $sessionId]);
        } catch (Exception $e) {
            // Fallback for testing or when routes are not defined
            return "/gdevelop/preview/{$sessionId}";
        }
    }

    /**
     * Cache preview metadata
     */
    private function cachePreviewMetadata(string $sessionId, array $metadata): void
    {
        $cacheKey = "gdevelop_preview_{$sessionId}";
        Cache::put($cacheKey, $metadata, $this->cacheTimeout * 60); // Convert minutes to seconds
    }

    /**
     * Get cached preview metadata
     */
    private function getCachedPreviewMetadata(string $sessionId): ?array
    {
        $cacheKey = "gdevelop_preview_{$sessionId}";
        return Cache::get($cacheKey);
    }

    /**
     * Clear preview cache
     */
    private function clearPreviewCache(string $sessionId): void
    {
        $cacheKey = "gdevelop_preview_{$sessionId}";
        Cache::forget($cacheKey);
    }

    /**
     * Get MIME type for file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'html', 'htm' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream'
        };
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}

/**
 * Preview generation result
 */
class PreviewGenerationResult
{
    public function __construct(
        public bool $success,
        public ?string $previewUrl,
        public ?string $previewPath,
        public ?string $indexPath,
        public ?string $error,
        public int $buildTime,
        public bool $cached
    ) {}
}