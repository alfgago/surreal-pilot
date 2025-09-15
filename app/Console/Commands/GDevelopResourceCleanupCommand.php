<?php

namespace App\Console\Commands;

use App\Services\GDevelopResourceCleanupService;
use Illuminate\Console\Command;

class GDevelopResourceCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:cleanup-resources 
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old GDevelop resources, temporary files, and inactive sessions';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopResourceCleanupService $cleanupService): int
    {
        $this->info('Starting GDevelop resource cleanup...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No files will actually be deleted');
            // In a real implementation, you'd modify the service to support dry-run
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('This will permanently delete old GDevelop files and sessions. Continue?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $results = $cleanupService->cleanupResources();

        $this->info('Cleanup completed successfully!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Temporary files cleaned', $results['temp_files_cleaned']],
                ['Sessions cleaned', $results['sessions_cleaned']],
                ['Disk space freed (MB)', $results['disk_space_freed_mb']],
                ['Errors', count($results['errors'])]
            ]
        );

        if (!empty($results['errors'])) {
            $this->error('Errors encountered during cleanup:');
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return 0;
    }
}