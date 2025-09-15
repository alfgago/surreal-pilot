<?php

namespace App\Console\Commands;

use App\Services\GDevelopExportService;
use Illuminate\Console\Command;

class GDevelopCleanupExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:cleanup-exports 
                            {--hours=24 : Number of hours after which exports are considered old}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old GDevelop export files';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopExportService $exportService): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("Cleaning up GDevelop exports older than {$hours} hours...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
            
            // Show what would be deleted
            $exportPath = storage_path('gdevelop/exports');
            $cutoffTime = time() - ($hours * 60 * 60);
            $files = glob($exportPath . '/*.zip');
            $oldFiles = array_filter($files, fn($file) => filemtime($file) < $cutoffTime);
            
            if (empty($oldFiles)) {
                $this->info('No old export files found.');
            } else {
                $this->info('Files that would be deleted:');
                foreach ($oldFiles as $file) {
                    $age = round((time() - filemtime($file)) / 3600, 1);
                    $size = $this->formatFileSize(filesize($file));
                    $this->line("  - " . basename($file) . " (age: {$age}h, size: {$size})");
                }
            }
            
            return Command::SUCCESS;
        }

        try {
            $cleanedCount = $exportService->cleanupOldExports($hours);
            
            if ($cleanedCount > 0) {
                $this->info("Successfully cleaned up {$cleanedCount} old export files.");
            } else {
                $this->info('No old export files found to clean up.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to clean up exports: ' . $e->getMessage());
            return Command::FAILURE;
        }
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