<?php

namespace App\Console\Commands;

use App\Services\MultiplayerService;
use App\Services\MultiplayerStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredMultiplayerSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiplayer:cleanup
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--force : Force cleanup even if sessions are not expired}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired multiplayer sessions and their associated resources';

    private MultiplayerService $multiplayerService;
    private MultiplayerStorageService $storageService;

    public function __construct(
        MultiplayerService $multiplayerService,
        MultiplayerStorageService $storageService
    ) {
        parent::__construct();
        $this->multiplayerService = $multiplayerService;
        $this->storageService = $storageService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting multiplayer session cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual cleanup will be performed');
        }

        try {
            // Get expired sessions
            $expiredSessions = \App\Models\MultiplayerSession::expired()
                ->whereNotIn('status', ['stopped'])
                ->with('workspace')
                ->get();

            if ($force) {
                // If force is enabled, also include active sessions that should be cleaned
                $allSessions = \App\Models\MultiplayerSession::whereNotIn('status', ['stopped'])
                    ->with('workspace')
                    ->get();
                
                $this->warn('FORCE MODE - Will cleanup all non-stopped sessions');
                $expiredSessions = $allSessions;
            }

            if ($expiredSessions->isEmpty()) {
                $this->info('No expired multiplayer sessions found to cleanup.');
                return self::SUCCESS;
            }

            $this->info("Found {$expiredSessions->count()} expired sessions to cleanup:");

            $cleanedUp = 0;
            $errors = 0;

            foreach ($expiredSessions as $session) {
                $this->line("Processing session {$session->id} (Workspace: {$session->workspace->name})");

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would stop session and cleanup files");
                    continue;
                }

                try {
                    // Stop the session
                    $stopped = $this->multiplayerService->stopSession($session->id);
                    
                    if ($stopped) {
                        // Cleanup storage files
                        $this->storageService->cleanupSession($session->workspace, $session->id);
                        
                        $this->info("  ✓ Session {$session->id} cleaned up successfully");
                        $cleanedUp++;
                    } else {
                        $this->error("  ✗ Failed to stop session {$session->id}");
                        $errors++;
                    }

                } catch (\Exception $e) {
                    $this->error("  ✗ Error cleaning up session {$session->id}: {$e->getMessage()}");
                    $errors++;
                }
            }

            if (!$dryRun) {
                $this->info("\nCleanup completed:");
                $this->info("  - Sessions cleaned up: {$cleanedUp}");
                
                if ($errors > 0) {
                    $this->error("  - Errors encountered: {$errors}");
                }

                Log::info('Multiplayer session cleanup completed', [
                    'cleaned_up' => $cleanedUp,
                    'errors' => $errors,
                    'total_processed' => $expiredSessions->count(),
                ]);
            }

            return $errors > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to run multiplayer cleanup: {$e->getMessage()}");
            
            Log::error('Multiplayer session cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}