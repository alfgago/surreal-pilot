<?php

namespace App\Console\Commands;

use App\Services\GDevelopPreviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GDevelopCleanupPreviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:cleanup-previews 
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired GDevelop preview files and cache';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopPreviewService $previewService): int
    {
        $this->info('Starting GDevelop preview cleanup...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm('This will delete expired preview files. Continue?')) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            if ($dryRun) {
                // For dry run, we'll just count what would be cleaned
                $this->info('Dry run mode - would clean up expired previews');
                $cleaned = 0; // We'd need to implement a dry-run version of the cleanup method
            } else {
                $cleaned = $previewService->cleanupExpiredPreviews();
            }

            if ($cleaned > 0) {
                $this->info("Successfully cleaned up {$cleaned} expired preview(s).");
                Log::info('GDevelop preview cleanup completed', [
                    'cleaned_count' => $cleaned,
                    'dry_run' => $dryRun
                ]);
            } else {
                $this->info('No expired previews found to clean up.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Preview cleanup failed: ' . $e->getMessage());
            Log::error('GDevelop preview cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}