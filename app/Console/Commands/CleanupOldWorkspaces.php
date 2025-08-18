<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WorkspaceCleanupService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldWorkspaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspace:cleanup
                            {--hours=24 : Number of hours after which workspaces should be cleaned up}
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--force : Force cleanup without confirmation}
                            {--engine= : Only cleanup workspaces of specific engine type (unreal|playcanvas)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old workspaces and their associated resources';

    private WorkspaceCleanupService $cleanupService;

    public function __construct(WorkspaceCleanupService $cleanupService)
    {
        parent::__construct();
        $this->cleanupService = $cleanupService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $engineType = $this->option('engine');

        $cutoffTime = Carbon::now()->subHours($hours);

        $this->info("Starting workspace cleanup for workspaces older than {$hours} hours (before {$cutoffTime->format('Y-m-d H:i:s')})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual cleanup will be performed');
        }

        if ($engineType) {
            $this->info("Filtering by engine type: {$engineType}");
        }

        try {
            // Get old workspaces
            $query = Workspace::where('created_at', '<', $cutoffTime)
                ->with(['company', 'multiplayerSessions']);

            if ($engineType) {
                $query->where('engine_type', $engineType);
            }

            $oldWorkspaces = $query->get();

            if ($oldWorkspaces->isEmpty()) {
                $this->info('No old workspaces found to cleanup.');
                return self::SUCCESS;
            }

            $this->info("Found {$oldWorkspaces->count()} workspaces to cleanup:");

            // Display workspaces to be cleaned up
            $this->table(
                ['ID', 'Name', 'Engine', 'Company', 'Created', 'Status', 'Sessions'],
                $oldWorkspaces->map(function ($workspace) {
                    return [
                        $workspace->id,
                        $workspace->name,
                        $workspace->engine_type,
                        $workspace->company->name ?? 'N/A',
                        $workspace->created_at->format('Y-m-d H:i:s'),
                        $workspace->status,
                        $workspace->multiplayerSessions->count(),
                    ];
                })->toArray()
            );

            if (!$dryRun && !$force) {
                if (!$this->confirm('Do you want to proceed with cleanup?')) {
                    $this->info('Cleanup cancelled.');
                    return self::SUCCESS;
                }
            }

            $cleanedUp = 0;
            $errors = 0;

            foreach ($oldWorkspaces as $workspace) {
                $this->line("Processing workspace {$workspace->id} ({$workspace->name})");

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would cleanup workspace and all associated resources");
                    continue;
                }

                try {
                    $result = $this->cleanupService->cleanupWorkspace($workspace);
                    
                    if ($result['success']) {
                        $this->info("  ✓ Workspace {$workspace->id} cleaned up successfully");
                        $this->line("    - Files cleaned: {$result['files_cleaned']}");
                        $this->line("    - Storage freed: {$result['storage_freed']}");
                        $this->line("    - Sessions terminated: {$result['sessions_terminated']}");
                        $cleanedUp++;
                    } else {
                        $this->error("  ✗ Failed to cleanup workspace {$workspace->id}: {$result['error']}");
                        $errors++;
                    }

                } catch (\Exception $e) {
                    $this->error("  ✗ Error cleaning up workspace {$workspace->id}: {$e->getMessage()}");
                    $errors++;
                }
            }

            if (!$dryRun) {
                $this->info("\nCleanup completed:");
                $this->info("  - Workspaces cleaned up: {$cleanedUp}");
                
                if ($errors > 0) {
                    $this->error("  - Errors encountered: {$errors}");
                }

                Log::info('Workspace cleanup completed', [
                    'cleaned_up' => $cleanedUp,
                    'errors' => $errors,
                    'total_processed' => $oldWorkspaces->count(),
                    'cutoff_hours' => $hours,
                    'engine_type' => $engineType,
                ]);
            }

            return $errors > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to run workspace cleanup: {$e->getMessage()}");
            
            Log::error('Workspace cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}