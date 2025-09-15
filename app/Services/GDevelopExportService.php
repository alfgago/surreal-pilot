<?php

namespace App\Services;

use App\Models\GDevelopGameSession;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GDevelopExportService
{
    private GDevelopRuntimeService $runtimeService;
    private string $exportsPath;
    private string $tempPath;
    private int $maxExportSize;
    private int $exportTimeout;

    public function __construct(GDevelopRuntimeService $runtimeService)
    {
        $this->runtimeService = $runtimeService;
        $this->exportsPath = storage_path(config('gdevelop.exports_path', 'gdevelop/exports'));
        $this->tempPath = storage_path('gdevelop/temp');
        $this->maxExportSize = config('gdevelop.max_export_size', 100 * 1024 * 1024); // 100MB
        $this->exportTimeout = config('gdevelop.export_timeout', 30); // 30 seconds
        
        $this->ensureDirectoriesExist();
    }

    /**
     * Generate a complete HTML5 export with ZIP packaging
     */
    public function generateExport(string $sessionId, array $options = []): ExportResult
    {
        try {
            Log::info('Starting GDevelop export generation', [
                'session_id' => $sessionId,
                'options' => $options
            ]);

            // Load the game session
            $session = GDevelopGameSession::where('session_id', $sessionId)->first();
            if (!$session) {
                throw new Exception("Game session not found: {$sessionId}");
            }

            // Prepare export options
            $exportOptions = $this->prepareExportOptions($options);
            
            // Create temporary export directory
            $tempExportPath = $this->createTempExportDirectory($sessionId);
            
            // Generate game.json file
            $gameJsonPath = $this->createGameJsonFile($session, $tempExportPath);
            
            // Build HTML5 export using runtime service (includes ZIP creation)
            $buildResult = $this->buildHTML5Export($sessionId, $gameJsonPath, $exportOptions);
            
            if (!$buildResult->success) {
                throw new Exception("Export build failed: " . $buildResult->error);
            }

            // Runtime service already created the ZIP, so we use its result
            $zipPath = $buildResult->zipPath;
            $fileSize = $zipPath && file_exists($zipPath) ? filesize($zipPath) : 0;
            
            // Generate download URL
            $downloadUrl = $buildResult->downloadUrl ?? $this->generateDownloadUrl($sessionId);
            
            // Update session with export info (only if we have a valid zip path)
            if ($zipPath) {
                $this->updateSessionExportInfo($session, $zipPath, $downloadUrl);
            }
            
            // Schedule cleanup
            $this->scheduleCleanup($sessionId, $tempExportPath);

            Log::info('GDevelop export generated successfully', [
                'session_id' => $sessionId,
                'zip_path' => $zipPath,
                'download_url' => $downloadUrl
            ]);

            return new ExportResult(
                success: true,
                exportPath: $buildResult->exportPath,
                zipPath: $zipPath,
                downloadUrl: $downloadUrl,
                error: null,
                buildTime: $buildResult->buildTime,
                fileSize: $fileSize
            );

        } catch (Exception $e) {
            Log::error('GDevelop export generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new ExportResult(
                success: false,
                exportPath: null,
                zipPath: null,
                downloadUrl: null,
                error: $e->getMessage(),
                buildTime: time(),
                fileSize: 0
            );
        }
    }

    /**
     * Get export status and information
     */
    public function getExportStatus(string $sessionId): ?ExportStatus
    {
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        if (!$session) {
            return null;
        }

        $zipPath = $this->getZipPath($sessionId);
        $exists = file_exists($zipPath);
        
        return new ExportStatus(
            sessionId: $sessionId,
            exists: $exists,
            downloadUrl: $exists ? $session->export_url : null,
            fileSize: $exists ? filesize($zipPath) : 0,
            createdAt: $exists ? filemtime($zipPath) : null,
            expiresAt: $exists ? filemtime($zipPath) + (24 * 60 * 60) : null // 24 hours
        );
    }

    /**
     * Download export ZIP file
     */
    public function downloadExport(string $sessionId): ?DownloadResult
    {
        $zipPath = $this->getZipPath($sessionId);
        
        if (!file_exists($zipPath)) {
            return null;
        }

        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        $filename = $this->generateDownloadFilename($session);

        return new DownloadResult(
            filePath: $zipPath,
            filename: $filename,
            mimeType: 'application/zip',
            fileSize: filesize($zipPath)
        );
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldExports(int $olderThanHours = 24): int
    {
        $cleanedCount = 0;
        $cutoffTime = time() - ($olderThanHours * 60 * 60);
        
        // Ensure exports directory exists
        if (!is_dir($this->exportsPath)) {
            return 0;
        }
        
        $exportFiles = glob($this->exportsPath . DIRECTORY_SEPARATOR . '*.zip');
        
        foreach ($exportFiles as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $cleanedCount++;
                    Log::info('Cleaned up old export file', ['file' => $file]);
                }
            }
        }

        return $cleanedCount;
    }

    /**
     * Prepare export options with defaults
     */
    private function prepareExportOptions(array $options): array
    {
        return array_merge([
            'minify' => true,
            'mobile_optimized' => false,
            'compression_level' => 'standard',
            'export_format' => 'html5',
            'include_assets' => true
        ], $options);
    }

    /**
     * Create temporary export directory
     */
    private function createTempExportDirectory(string $sessionId): string
    {
        $tempPath = $this->tempPath . DIRECTORY_SEPARATOR . $sessionId . '_' . time();
        
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        return $tempPath;
    }

    /**
     * Create game.json file in export directory
     */
    private function createGameJsonFile(GDevelopGameSession $session, string $exportPath): string
    {
        $gameJsonPath = $exportPath . DIRECTORY_SEPARATOR . 'game.json';
        
        $gameJson = json_encode($session->game_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($gameJsonPath, $gameJson);

        return $gameJsonPath;
    }

    /**
     * Build HTML5 export using runtime service
     */
    private function buildHTML5Export(string $sessionId, string $gameJsonPath, array $options): ExportResult
    {
        // The runtime service already creates a ZIP file, so we use it directly
        return $this->runtimeService->buildExport($sessionId, $gameJsonPath, $options);
    }

    /**
     * Create ZIP package from export files
     */
    private function createZipPackage(string $sessionId, string $exportPath, array $options): ZipResult
    {
        $zipPath = $this->getZipPath($sessionId);
        
        // Remove existing ZIP if it exists
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            return new ZipResult(
                success: false,
                zipPath: null,
                error: "Failed to create ZIP file: " . $this->getZipError($result),
                fileSize: 0
            );
        }

        try {
            $this->addDirectoryToZip($zip, $exportPath, '', $options);
            $zip->close();

            $fileSize = filesize($zipPath);
            
            // Check file size limits
            if ($fileSize > $this->maxExportSize) {
                unlink($zipPath);
                return new ZipResult(
                    success: false,
                    zipPath: null,
                    error: "Export file too large: " . $this->formatFileSize($fileSize),
                    fileSize: $fileSize
                );
            }

            return new ZipResult(
                success: true,
                zipPath: $zipPath,
                error: null,
                fileSize: $fileSize
            );

        } catch (Exception $e) {
            if (isset($zip) && $zip instanceof \ZipArchive) {
                try {
                    $zip->close();
                } catch (Exception $closeException) {
                    // Ignore close errors
                }
            }
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            
            return new ZipResult(
                success: false,
                zipPath: null,
                error: "ZIP creation failed: " . $e->getMessage(),
                fileSize: 0
            );
        }
    }

    /**
     * Add directory contents to ZIP archive
     */
    private function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPath, array $options): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . substr($filePath, strlen($sourcePath) + 1);
            
            // Replace backslashes with forward slashes for cross-platform compatibility
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                // Apply compression based on options
                $compressionMethod = $this->getCompressionMethod($options['compression_level']);
                $zip->addFile($filePath, $relativePath);
                $zip->setCompressionName($relativePath, $compressionMethod);
            }
        }
    }

    /**
     * Get compression method based on level
     */
    private function getCompressionMethod(string $level): int
    {
        return match ($level) {
            'none' => ZipArchive::CM_STORE,
            'maximum' => ZipArchive::CM_BZIP2,
            default => ZipArchive::CM_DEFLATE
        };
    }

    /**
     * Generate download URL for export
     */
    private function generateDownloadUrl(string $sessionId): string
    {
        try {
            return route('gdevelop.export.download', ['sessionId' => $sessionId]);
        } catch (Exception $e) {
            return "/api/gdevelop/export/{$sessionId}/download";
        }
    }

    /**
     * Update session with export information
     */
    private function updateSessionExportInfo(GDevelopGameSession $session, string $zipPath, string $downloadUrl): void
    {
        $session->update([
            'export_url' => $downloadUrl,
            'status' => 'exported'
        ]);
    }

    /**
     * Schedule cleanup of temporary files
     */
    private function scheduleCleanup(string $sessionId, string $tempPath): void
    {
        // Schedule cleanup after 1 hour
        $cleanupTime = time() + 3600;
        
        // In a real implementation, you might use Laravel's job queue
        // For now, we'll just log the cleanup requirement
        Log::info('Scheduled cleanup for export temp files', [
            'session_id' => $sessionId,
            'temp_path' => $tempPath,
            'cleanup_time' => $cleanupTime
        ]);
    }

    /**
     * Get ZIP file path for session
     */
    private function getZipPath(string $sessionId): string
    {
        return $this->exportsPath . DIRECTORY_SEPARATOR . $sessionId . '.zip';
    }

    /**
     * Generate download filename
     */
    private function generateDownloadFilename(?GDevelopGameSession $session): string
    {
        $gameName = $session?->game_title ?? 'gdevelop-game';
        $timestamp = date('Y-m-d_H-i-s') . '_' . substr(microtime(), 2, 6);
        
        // Sanitize filename
        $gameName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $gameName);
        
        return "{$gameName}_{$timestamp}.zip";
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [$this->exportsPath, $this->tempPath];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Get ZIP error message
     */
    private function getZipError(int $code): string
    {
        return match ($code) {
            ZipArchive::ER_OK => 'No error',
            ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
            ZipArchive::ER_RENAME => 'Renaming temporary file failed',
            ZipArchive::ER_CLOSE => 'Closing zip archive failed',
            ZipArchive::ER_SEEK => 'Seek error',
            ZipArchive::ER_READ => 'Read error',
            ZipArchive::ER_WRITE => 'Write error',
            ZipArchive::ER_CRC => 'CRC error',
            ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
            ZipArchive::ER_NOENT => 'No such file',
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_OPEN => 'Can\'t open file',
            ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
            ZipArchive::ER_ZLIB => 'Zlib error',
            ZipArchive::ER_MEMORY => 'Memory allocation failure',
            ZipArchive::ER_CHANGED => 'Entry has been changed',
            ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
            ZipArchive::ER_EOF => 'Premature EOF',
            ZipArchive::ER_INVAL => 'Invalid argument',
            ZipArchive::ER_NOZIP => 'Not a zip archive',
            ZipArchive::ER_INTERNAL => 'Internal error',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent',
            ZipArchive::ER_REMOVE => 'Can\'t remove file',
            ZipArchive::ER_DELETED => 'Entry has been deleted',
            default => "Unknown error code: {$code}"
        };
    }

    /**
     * Format file size for human reading
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

/**
 * ZIP creation result
 */
class ZipResult
{
    public function __construct(
        public bool $success,
        public ?string $zipPath,
        public ?string $error,
        public int $fileSize
    ) {}
}

/**
 * Export status information
 */
class ExportStatus
{
    public function __construct(
        public string $sessionId,
        public bool $exists,
        public ?string $downloadUrl,
        public int $fileSize,
        public ?int $createdAt,
        public ?int $expiresAt
    ) {}
}

/**
 * Download result information
 */
class DownloadResult
{
    public function __construct(
        public string $filePath,
        public string $filename,
        public string $mimeType,
        public int $fileSize
    ) {}
}