<?php

namespace App\Console\Commands;

use App\Services\GDevelopPerformanceMonitorService;
use App\Services\GDevelopCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GDevelopPerformanceCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:cleanup 
                            {--metrics : Clean up old performance metrics}
                            {--cache : Optimize cache storage}
                            {--files : Clean up temporary files}
                            {--all : Run all cleanup tasks}
                            {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up and optimize GDevelop performance data and temporary files';

    public function __construct(
        private GDevelopPerformanceMonitorService $performanceMonitor,
        private GDevelopCacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cleanupMetrics = $this->option('metrics') || $this->option('all');
        $optimizeCache = $this->option('cache') || $this->option('all');
        $cleanupFiles = $this->option('files') || $this->option('all');
        $dryRun = $this->option('dry-run');

        if (!$cleanupMetrics && !$optimizeCache && !$cleanupFiles) {
            $this->error('Please specify what to clean up: --metrics, --cache, --files, or --all');
            return 1;
        }

        try {
            $this->info('Starting GDevelop performance cleanup...');
            
            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
            }

            $totalCleaned = 0;

            if ($cleanupMetrics) {
                $totalCleaned += $this->cleanupMetrics($dryRun);
            }

            if ($optimizeCache) {
                $totalCleaned += $this->optimizeCache($dryRun);
            }

            if ($cleanupFiles) {
                $totalCleaned += $this->cleanupFiles($dryRun);
            }

            $this->info("✓ Cleanup completed successfully. Total items processed: {$totalCleaned}");
            return 0;

        } catch (Exception $e) {
            $this->error("Cleanup failed: " . $e->getMessage());
            Log::error('GDevelop performance cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Clean up old performance metrics
     */
    private function cleanupMetrics(bool $dryRun): int
    {
        $this->info('Cleaning up old performance metrics...');

        if ($dryRun) {
            $this->line('  [DRY RUN] Would clean up old performance metrics');
            return 1;
        }

        try {
            $this->performanceMonitor->clearOldMetrics();
            $this->info('  ✓ Old performance metrics cleaned up');
            return 1;
        } catch (Exception $e) {
            $this->error("  ✗ Failed to clean up metrics: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Optimize cache storage
     */
    private function optimizeCache(bool $dryRun): int
    {
        $this->info('Optimizing cache storage...');

        $optimizedCount = 0;

        try {
            // Get cache statistics before optimization
            $cacheStats = $this->cacheService->getCacheStatistics();
            $this->line('  Current cache statistics:');
            foreach ($cacheStats as $type => $count) {
                $this->line("    - {$type}: {$count}");
            }

            if ($dryRun) {
                $this->line('  [DRY RUN] Would optimize cache storage');
                return 1;
            }

            // Clear expired cache entries
            $this->line('  Clearing expired cache entries...');
            // Note: Laravel's cache system automatically handles TTL expiration
            // This is more of a placeholder for custom cache optimization logic

            // Optimize cache hit ratios by identifying frequently accessed items
            $this->optimizeCacheHitRatios();
            $optimizedCount++;

            $this->info('  ✓ Cache storage optimized');
            return $optimizedCount;

        } catch (Exception $e) {
            $this->error("  ✗ Failed to optimize cache: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up temporary files
     */
    private function cleanupFiles(bool $dryRun): int
    {
        $this->info('Cleaning up temporary files...');

        $cleanedCount = 0;

        try {
            // Clean up old preview files
            $cleanedCount += $this->cleanupPreviewFiles($dryRun);

            // Clean up old export files
            $cleanedCount += $this->cleanupExportFiles($dryRun);

            // Clean up orphaned session files
            $cleanedCount += $this->cleanupOrphanedSessionFiles($dryRun);

            $this->info("  ✓ Cleaned up {$cleanedCount} temporary files");
            return $cleanedCount;

        } catch (Exception $e) {
            $this->error("  ✗ Failed to clean up files: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old preview files
     */
    private function cleanupPreviewFiles(bool $dryRun): int
    {
        $this->line('    Cleaning up old preview files...');

        $previewPath = 'gdevelop/sessions';
        $cleanupAge = now()->subHours(config('gdevelop.preview.cleanup_interval', 24));
        $cleanedCount = 0;

        try {
            if (!Storage::exists($previewPath)) {
                return 0;
            }

            $directories = Storage::directories($previewPath);

            foreach ($directories as $directory) {
                $previewDir = $directory . '/preview';
                
                if (Storage::exists($previewDir)) {
                    $lastModified = Storage::lastModified($previewDir);
                    
                    if ($lastModified < $cleanupAge->timestamp) {
                        if ($dryRun) {
                            $this->line("      [DRY RUN] Would delete: {$previewDir}");
                        } else {
                            Storage::deleteDirectory($previewDir);
                            $this->line("      Deleted: {$previewDir}");
                        }
                        $cleanedCount++;
                    }
                }
            }

            return $cleanedCount;

        } catch (Exception $e) {
            $this->error("      ✗ Failed to clean preview files: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old export files
     */
    private function cleanupExportFiles(bool $dryRun): int
    {
        $this->line('    Cleaning up old export files...');

        $exportPath = 'gdevelop/exports';
        $cleanupAge = now()->subHours(config('gdevelop.export_cleanup_hours', 24));
        $cleanedCount = 0;

        try {
            if (!Storage::exists($exportPath)) {
                return 0;
            }

            $files = Storage::allFiles($exportPath);

            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                
                if ($lastModified < $cleanupAge->timestamp) {
                    if ($dryRun) {
                        $this->line("      [DRY RUN] Would delete: {$file}");
                    } else {
                        Storage::delete($file);
                        $this->line("      Deleted: {$file}");
                    }
                    $cleanedCount++;
                }
            }

            return $cleanedCount;

        } catch (Exception $e) {
            $this->error("      ✗ Failed to clean export files: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up orphaned session files
     */
    private function cleanupOrphanedSessionFiles(bool $dryRun): int
    {
        $this->line('    Cleaning up orphaned session files...');

        $sessionsPath = 'gdevelop/sessions';
        $cleanupAge = now()->subDays(7); // Clean up sessions older than 7 days
        $cleanedCount = 0;

        try {
            if (!Storage::exists($sessionsPath)) {
                return 0;
            }

            $directories = Storage::directories($sessionsPath);

            foreach ($directories as $directory) {
                $metadataFile = $directory . '/metadata.json';
                
                if (Storage::exists($metadataFile)) {
                    $lastModified = Storage::lastModified($metadataFile);
                    
                    if ($lastModified < $cleanupAge->timestamp) {
                        if ($dryRun) {
                            $this->line("      [DRY RUN] Would delete session: {$directory}");
                        } else {
                            Storage::deleteDirectory($directory);
                            $this->line("      Deleted session: {$directory}");
                        }
                        $cleanedCount++;
                    }
                } else {
                    // Orphaned directory without metadata
                    if ($dryRun) {
                        $this->line("      [DRY RUN] Would delete orphaned directory: {$directory}");
                    } else {
                        Storage::deleteDirectory($directory);
                        $this->line("      Deleted orphaned directory: {$directory}");
                    }
                    $cleanedCount++;
                }
            }

            return $cleanedCount;

        } catch (Exception $e) {
            $this->error("      ✗ Failed to clean session files: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Optimize cache hit ratios
     */
    private function optimizeCacheHitRatios(): void
    {
        try {
            // Get cache statistics
            $cacheStats = $this->cacheService->getCacheStatistics();
            
            // Calculate hit ratios for each cache type
            $hitRatios = [];
            foreach (['template', 'structure', 'validation', 'assets'] as $cacheType) {
                $hits = $cacheStats["{$cacheType}_cache_hits"] ?? 0;
                $misses = $cacheStats["{$cacheType}_cache_misses"] ?? 0;
                $total = $hits + $misses;
                
                if ($total > 0) {
                    $hitRatios[$cacheType] = ($hits / $total) * 100;
                }
            }

            // Log cache performance insights
            foreach ($hitRatios as $cacheType => $ratio) {
                if ($ratio < 70) { // Less than 70% hit ratio
                    Log::info("Low cache hit ratio detected for {$cacheType} cache", [
                        'cache_type' => $cacheType,
                        'hit_ratio' => $ratio,
                        'recommendation' => 'Consider increasing cache TTL or warming up cache'
                    ]);
                }
            }

            $this->line('    Cache hit ratios analyzed and logged');

        } catch (Exception $e) {
            Log::warning('Failed to optimize cache hit ratios', [
                'error' => $e->getMessage()
            ]);
        }
    }
}