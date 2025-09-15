<?php

namespace App\Console\Commands;

use App\Services\GDevelopSessionManager;
use Illuminate\Console\Command;

class GDevelopCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gdevelop:cleanup 
                            {--archive-days=7 : Days of inactivity before archiving sessions}
                            {--cleanup-days=30 : Days after archiving before permanent deletion}
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--stats : Show session statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up and archive old GDevelop game sessions';

    /**
     * Execute the console command.
     */
    public function handle(GDevelopSessionManager $sessionManager)
    {
        if ($this->option('stats')) {
            $this->showStatistics($sessionManager);
            return;
        }

        $archiveDays = (int) $this->option('archive-days');
        $cleanupDays = (int) $this->option('cleanup-days');
        $dryRun = $this->option('dry-run');

        $this->info("GDevelop Session Cleanup");
        $this->info("Archive inactive sessions after: {$archiveDays} days");
        $this->info("Delete archived sessions after: {$cleanupDays} days");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }

        $this->newLine();

        // Show current statistics
        $this->showStatistics($sessionManager);
        $this->newLine();

        if (!$dryRun) {
            // Archive inactive sessions
            $this->info("Archiving inactive sessions...");
            $archivedCount = $sessionManager->archiveInactiveSessions($archiveDays);
            $this->info("Archived {$archivedCount} sessions");

            // Clean up old archived sessions
            $this->info("Cleaning up old archived sessions...");
            $cleanedCount = $sessionManager->cleanupArchivedSessions($cleanupDays);
            $this->info("Cleaned up {$cleanedCount} sessions");

            $this->newLine();
            $this->info("Cleanup completed successfully!");
            
            // Show updated statistics
            $this->newLine();
            $this->info("Updated statistics:");
            $this->showStatistics($sessionManager);
        } else {
            // Dry run - show what would be done
            $sessionsToArchive = \App\Models\GDevelopGameSession::getSessionsForArchival($archiveDays);
            $sessionsToCleanup = \App\Models\GDevelopGameSession::getSessionsForCleanup($cleanupDays);
            
            $this->info("Would archive {$sessionsToArchive->count()} sessions");
            $this->info("Would cleanup {$sessionsToCleanup->count()} sessions");
        }
    }

    /**
     * Show session statistics.
     */
    private function showStatistics(GDevelopSessionManager $sessionManager): void
    {
        $stats = $sessionManager->getSessionStatistics();
        $storage = $sessionManager->getStorageUsage();

        $this->info("Session Statistics:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Sessions', $stats['total_sessions']],
                ['Active Sessions', $stats['active_sessions']],
                ['Archived Sessions', $stats['archived_sessions']],
                ['Error Sessions', $stats['error_sessions']],
                ['Sessions (Last 24h)', $stats['sessions_last_24h']],
                ['Sessions (Last Week)', $stats['sessions_last_week']],
                ['Sessions (Last Month)', $stats['sessions_last_month']],
            ]
        );

        $this->newLine();
        $this->info("Storage Usage:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Storage', $storage['total_size_mb'] . ' MB'],
                ['Sessions with Files', $storage['session_count']],
                ['Average Size per Session', $storage['average_size_mb'] . ' MB'],
            ]
        );
    }
}
