<?php

namespace App\Services;

use App\Models\GDevelopGameSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GDevelopResourceCleanupService
{
    /**
     * Maximum age for temporary files (in hours)
     */
    private const MAX_TEMP_FILE_AGE = 24;

    /**
     * Maximum age for inactive sessions (in hours)
     */
    private const MAX_INACTIVE_SESSION_AGE = 72;

    /**
     * Maximum disk space usage for GDevelop files (in MB)
     */
    private const MAX_DISK_USAGE = 5000; // 5GB

    /**
     * Clean up old and unused resources
     */
    public function cleanupResources(): array
    {
        $results = [
            'temp_files_cleaned' => 0,
            'sessions_cleaned' => 0,
            'disk_space_freed_mb' => 0,
            'errors' => []
        ];

        try {
            // Clean up temporary files
            $tempResults = $this->cleanupTemporaryFiles();
            $results['temp_files_cleaned'] = $tempResults['files_cleaned'];
            $results['disk_space_freed_mb'] += $tempResults['space_freed_mb'];

            // Clean up inactive sessions
            $sessionResults = $this->cleanupInactiveSessions();
            $results['sessions_cleaned'] = $sessionResults['sessions_cleaned'];
            $results['disk_space_freed_mb'] += $sessionResults['space_freed_mb'];

            // Clean up orphaned files
            $orphanResults = $this->cleanupOrphanedFiles();
            $results['disk_space_freed_mb'] += $orphanResults['space_freed_mb'];

            // Enforce disk usage limits
            $this->enforceDiskUsageLimits();

            Log::info('GDevelop resource cleanup completed', $results);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('GDevelop resource cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTemporaryFiles(): array
    {
        $filesCleanedCount = 0;
        $spaceFreed = 0;

        $tempDirectories = [
            storage_path('gdevelop/temp'),
            storage_path('gdevelop/isolated'),
            storage_path('gdevelop/previews/temp'),
            storage_path('gdevelop/exports/temp')
        ];

        foreach ($tempDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $cutoffTime = Carbon::now()->subHours(self::MAX_TEMP_FILE_AGE);
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                try {
                    $fileTime = Carbon::createFromTimestamp($file->getMTime());
                    
                    if ($fileTime->lt($cutoffTime)) {
                        $fileSize = $file->getSize();
                        
                        if ($file->isDir()) {
                            if ($this->isDirectoryEmpty($file->getPathname())) {
                                rmdir($file->getPathname());
                                $filesCleanedCount++;
                            }
                        } else {
                            unlink($file->getPathname());
                            $filesCleanedCount++;
                            $spaceFreed += $fileSize;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to clean up file', [
                        'file' => $file->getPathname(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return [
            'files_cleaned' => $filesCleanedCount,
            'space_freed_mb' => round($spaceFreed / 1024 / 1024, 2)
        ];
    }

    /**
     * Clean up inactive sessions
     */
    private function cleanupInactiveSessions(): array
    {
        $cutoffTime = Carbon::now()->subHours(self::MAX_INACTIVE_SESSION_AGE);
        
        $inactiveSessions = GDevelopGameSession::where('updated_at', '<', $cutoffTime)
            ->get();

        $sessionsCleanedCount = 0;
        $spaceFreed = 0;

        foreach ($inactiveSessions as $session) {
            try {
                // Calculate space used by session files
                $sessionSpaceUsed = $this->calculateSessionSpaceUsage($session);
                
                // Clean up session files
                $this->cleanupSessionFiles($session);
                
                // Delete session record
                $session->delete();
                
                $sessionsCleanedCount++;
                $spaceFreed += $sessionSpaceUsed;

                Log::info('Cleaned up inactive GDevelop session', [
                    'session_id' => $session->session_id,
                    'space_freed_mb' => round($sessionSpaceUsed / 1024 / 1024, 2)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to clean up session', [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'sessions_cleaned' => $sessionsCleanedCount,
            'space_freed_mb' => round($spaceFreed / 1024 / 1024, 2)
        ];
    }

    /**
     * Clean up orphaned files
     */
    private function cleanupOrphanedFiles(): array
    {
        $spaceFreed = 0;
        
        // Get all session IDs from database
        $activeSessions = GDevelopGameSession::pluck('session_id')->toArray();
        
        $gdevelopDirectories = [
            storage_path('gdevelop/sessions'),
            storage_path('gdevelop/previews'),
            storage_path('gdevelop/exports')
        ];

        foreach ($gdevelopDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $subdirectories = glob($directory . '/*', GLOB_ONLYDIR);
            
            foreach ($subdirectories as $subdirectory) {
                $sessionId = basename($subdirectory);
                
                // If session doesn't exist in database, it's orphaned
                if (!in_array($sessionId, $activeSessions)) {
                    try {
                        $directorySize = $this->getDirectorySize($subdirectory);
                        $this->recursiveDelete($subdirectory);
                        $spaceFreed += $directorySize;

                        Log::info('Cleaned up orphaned GDevelop files', [
                            'session_id' => $sessionId,
                            'directory' => $subdirectory,
                            'space_freed_mb' => round($directorySize / 1024 / 1024, 2)
                        ]);

                    } catch (\Exception $e) {
                        Log::error('Failed to clean up orphaned files', [
                            'session_id' => $sessionId,
                            'directory' => $subdirectory,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        return [
            'space_freed_mb' => round($spaceFreed / 1024 / 1024, 2)
        ];
    }

    /**
     * Enforce disk usage limits
     */
    private function enforceDiskUsageLimits(): void
    {
        $totalUsage = $this->calculateTotalDiskUsage();
        $usageMB = $totalUsage / 1024 / 1024;

        if ($usageMB > self::MAX_DISK_USAGE) {
            Log::warning('GDevelop disk usage exceeds limit', [
                'current_usage_mb' => round($usageMB, 2),
                'limit_mb' => self::MAX_DISK_USAGE
            ]);

            // Clean up oldest sessions until under limit
            $this->cleanupOldestSessions($totalUsage - (self::MAX_DISK_USAGE * 1024 * 1024));
        }
    }

    /**
     * Clean up oldest sessions to free space
     */
    private function cleanupOldestSessions(int $spaceToFree): void
    {
        $spaceFreed = 0;
        
        $oldestSessions = GDevelopGameSession::orderBy('updated_at', 'asc')->get();

        foreach ($oldestSessions as $session) {
            if ($spaceFreed >= $spaceToFree) {
                break;
            }

            try {
                $sessionSpaceUsed = $this->calculateSessionSpaceUsage($session);
                $this->cleanupSessionFiles($session);
                $session->delete();
                
                $spaceFreed += $sessionSpaceUsed;

                Log::info('Cleaned up old session to free space', [
                    'session_id' => $session->session_id,
                    'space_freed_mb' => round($sessionSpaceUsed / 1024 / 1024, 2)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to clean up old session', [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Calculate total disk usage for GDevelop files
     */
    private function calculateTotalDiskUsage(): int
    {
        $totalSize = 0;
        $gdevelopPath = storage_path('gdevelop');

        if (is_dir($gdevelopPath)) {
            $totalSize = $this->getDirectorySize($gdevelopPath);
        }

        return $totalSize;
    }

    /**
     * Calculate space usage for a specific session
     */
    private function calculateSessionSpaceUsage(GDevelopGameSession $session): int
    {
        $totalSize = 0;
        
        $sessionDirectories = [
            storage_path('gdevelop/sessions/' . $session->session_id),
            storage_path('gdevelop/previews/' . $session->session_id),
            storage_path('gdevelop/exports/' . $session->session_id)
        ];

        foreach ($sessionDirectories as $directory) {
            if (is_dir($directory)) {
                $totalSize += $this->getDirectorySize($directory);
            }
        }

        return $totalSize;
    }

    /**
     * Clean up files for a specific session
     */
    private function cleanupSessionFiles(GDevelopGameSession $session): void
    {
        $sessionDirectories = [
            storage_path('gdevelop/sessions/' . $session->session_id),
            storage_path('gdevelop/previews/' . $session->session_id),
            storage_path('gdevelop/exports/' . $session->session_id),
            storage_path('gdevelop/isolated/' . $session->session_id)
        ];

        foreach ($sessionDirectories as $directory) {
            if (is_dir($directory)) {
                $this->recursiveDelete($directory);
            }
        }
    }

    /**
     * Get directory size recursively
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        if (!is_dir($directory)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Check if directory is empty
     */
    private function isDirectoryEmpty(string $directory): bool
    {
        $handle = opendir($directory);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    /**
     * Recursively delete directory
     */
    private function recursiveDelete(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($directory);
    }
}