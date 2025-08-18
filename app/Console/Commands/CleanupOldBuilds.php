<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupOldBuilds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspace:cleanup-builds 
                            {--days= : Number of days to retain builds (overrides config)}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old build artifacts based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = $this->option('days') ?? config('workspace.build_retention_days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!$retentionDays) {
            $this->info('Build cleanup is disabled (retention days not configured)');
            return 0;
        }

        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        $this->info("Cleaning up build artifacts older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        // Get workspaces with build metadata
        $workspaces = Workspace::whereNotNull('metadata')->get();
        
        $totalCleaned = 0;
        $totalSize = 0;

        foreach ($workspaces as $workspace) {
            $metadata = $workspace->metadata ?? [];
            
            if (!isset($metadata['build_timestamp'])) {
                continue;
            }

            $buildTimestamp = Carbon::parse($metadata['build_timestamp']);
            
            if ($buildTimestamp->isAfter($cutoffDate)) {
                continue; // Build is still within retention period
            }

            $buildPath = $metadata['latest_build_path'] ?? null;
            $storageDisk = $metadata['build_storage_disk'] ?? config('workspace.builds_disk', 'local');

            if (!$buildPath) {
                continue;
            }

            $disk = Storage::disk($storageDisk);
            
            try {
                // Get files to be deleted
                $files = $disk->allFiles($buildPath);
                $fileCount = count($files);
                
                if ($fileCount === 0) {
                    continue;
                }

                // Calculate size
                $size = 0;
                foreach ($files as $file) {
                    $size += $disk->size($file);
                }

                $this->line("Workspace {$workspace->id}: {$fileCount} files, " . $this->formatBytes($size) . " (built {$buildTimestamp->format('Y-m-d H:i:s')})");

                if (!$dryRun) {
                    if (!$force && !$this->confirm("Delete build artifacts for workspace {$workspace->id}?", true)) {
                        continue;
                    }

                    // Delete files
                    $disk->deleteDirectory($buildPath);
                    
                    // Update workspace metadata
                    $metadata = $workspace->metadata;
                    unset($metadata['latest_build_path']);
                    unset($metadata['build_timestamp']);
                    unset($metadata['build_storage_disk']);
                    
                    $workspace->update(['metadata' => $metadata]);
                    
                    $this->info("✓ Deleted build artifacts for workspace {$workspace->id}");
                }

                $totalCleaned += $fileCount;
                $totalSize += $size;

            } catch (\Exception $e) {
                $this->error("Failed to clean up workspace {$workspace->id}: " . $e->getMessage());
            }
        }

        if ($totalCleaned > 0) {
            $action = $dryRun ? 'Would clean up' : 'Cleaned up';
            $this->info("{$action} {$totalCleaned} files totaling " . $this->formatBytes($totalSize));
        } else {
            $this->info('No old build artifacts found to clean up');
        }

        return 0;
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}