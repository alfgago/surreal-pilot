<?php

namespace App\Console\Commands;

use App\Services\WorkspaceCleanupService;
use App\Services\MultiplayerService;
use App\Services\CloudFrontCleanupService;
use App\Services\EcsCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupAllResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:all
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--force : Force cleanup without confirmation}
                            {--workspace-hours=24 : Hours after which workspaces should be cleaned up}
                            {--skip-workspaces : Skip workspace cleanup}
                            {--skip-multiplayer : Skip multiplayer session cleanup}
                            {--skip-cloudfront : Skip CloudFront cleanup}
                            {--skip-ecs : Skip ECS task cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive cleanup of all PlayCanvas integration resources';

    private WorkspaceCleanupService $workspaceCleanupService;
    private MultiplayerService $multiplayerService;
    private CloudFrontCleanupService $cloudFrontCleanupService;
    private EcsCleanupService $ecsCleanupService;

    public function __construct(
        WorkspaceCleanupService $workspaceCleanupService,
        MultiplayerService $multiplayerService,
        CloudFrontCleanupService $cloudFrontCleanupService,
        EcsCleanupService $ecsCleanupService
    ) {
        parent::__construct();
        $this->workspaceCleanupService = $workspaceCleanupService;
        $this->multiplayerService = $multiplayerService;
        $this->cloudFrontCleanupService = $cloudFrontCleanupService;
        $this->ecsCleanupService = $ecsCleanupService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $workspaceHours = (int) $this->option('workspace-hours');

        $this->info('Starting comprehensive resource cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual cleanup will be performed');
        }

        $totalCleaned = [
            'workspaces' => 0,
            'multiplayer_sessions' => 0,
            'cloudfront_invalidations' => 0,
            'ecs_tasks' => 0,
        ];

        $errors = 0;

        try {
            // 1. Clean up expired multiplayer sessions first
            if (!$this->option('skip-multiplayer')) {
                $this->info("\n1. Cleaning up expired multiplayer sessions...");
                
                if (!$dryRun) {
                    $sessionsResult = $this->cleanupMultiplayerSessions();
                    $totalCleaned['multiplayer_sessions'] = $sessionsResult['cleaned'];
                    $errors += $sessionsResult['errors'];
                } else {
                    $this->line('  [DRY RUN] Would cleanup expired multiplayer sessions');
                }
            }

            // 2. Clean up orphaned ECS tasks
            if (!$this->option('skip-ecs')) {
                $this->info("\n2. Cleaning up orphaned ECS tasks...");
                
                if (!$dryRun) {
                    $ecsResult = $this->cleanupEcsTasks();
                    $totalCleaned['ecs_tasks'] = $ecsResult['cleaned'];
                    $errors += $ecsResult['errors'];
                } else {
                    $this->line('  [DRY RUN] Would cleanup orphaned ECS tasks');
                }
            }

            // 3. Clean up old workspaces
            if (!$this->option('skip-workspaces')) {
                $this->info("\n3. Cleaning up old workspaces (older than {$workspaceHours} hours)...");
                
                if (!$dryRun) {
                    $workspaceResult = $this->cleanupOldWorkspaces($workspaceHours, $force);
                    $totalCleaned['workspaces'] = $workspaceResult['cleaned'];
                    $errors += $workspaceResult['errors'];
                } else {
                    $this->line('  [DRY RUN] Would cleanup old workspaces');
                }
            }

            // 4. Clean up CloudFront cache
            if (!$this->option('skip-cloudfront')) {
                $this->info("\n4. Cleaning up CloudFront cache...");
                
                if (!$dryRun) {
                    $cloudFrontResult = $this->cleanupCloudFrontCache();
                    $totalCleaned['cloudfront_invalidations'] = $cloudFrontResult['cleaned'];
                    $errors += $cloudFrontResult['errors'];
                } else {
                    $this->line('  [DRY RUN] Would cleanup CloudFront cache');
                }
            }

            // Display summary
            $this->displaySummary($totalCleaned, $errors, $dryRun);

            return $errors > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Comprehensive cleanup failed: {$e->getMessage()}");
            
            Log::error('Comprehensive cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Clean up expired multiplayer sessions.
     *
     * @return array
     */
    private function cleanupMultiplayerSessions(): array
    {
        $cleaned = 0;
        $errors = 0;

        try {
            $cleaned = $this->multiplayerService->cleanupExpiredSessions();
            $this->info("  ✓ Cleaned up {$cleaned} expired multiplayer sessions");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to cleanup multiplayer sessions: {$e->getMessage()}");
            $errors++;
        }

        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    /**
     * Clean up orphaned ECS tasks.
     *
     * @return array
     */
    private function cleanupEcsTasks(): array
    {
        $cleaned = 0;
        $errors = 0;

        try {
            if (!$this->ecsCleanupService->isEnabled()) {
                $this->line('  ECS cleanup is disabled (cluster not configured)');
                return ['cleaned' => 0, 'errors' => 0];
            }

            // Clean up orphaned tasks
            $orphanedTasks = $this->ecsCleanupService->cleanupOrphanedTasks();
            
            // Clean up expired session tasks
            $expiredTasks = $this->ecsCleanupService->cleanupExpiredSessionTasks();
            
            $cleaned = $orphanedTasks + $expiredTasks;
            
            $this->info("  ✓ Cleaned up {$cleaned} ECS tasks ({$orphanedTasks} orphaned, {$expiredTasks} expired)");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to cleanup ECS tasks: {$e->getMessage()}");
            $errors++;
        }

        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    /**
     * Clean up old workspaces.
     *
     * @param int $hours
     * @param bool $force
     * @return array
     */
    private function cleanupOldWorkspaces(int $hours, bool $force): array
    {
        $cleaned = 0;
        $errors = 0;

        try {
            $oldWorkspaces = \App\Models\Workspace::where('created_at', '<', now()->subHours($hours))
                ->with(['company', 'multiplayerSessions'])
                ->get();

            if ($oldWorkspaces->isEmpty()) {
                $this->line('  No old workspaces found to cleanup');
                return ['cleaned' => 0, 'errors' => 0];
            }

            $this->line("  Found {$oldWorkspaces->count()} workspaces to cleanup");

            if (!$force) {
                if (!$this->confirm("  Proceed with cleaning up {$oldWorkspaces->count()} workspaces?")) {
                    $this->line('  Workspace cleanup cancelled');
                    return ['cleaned' => 0, 'errors' => 0];
                }
            }

            foreach ($oldWorkspaces as $workspace) {
                try {
                    $result = $this->workspaceCleanupService->cleanupWorkspace($workspace);
                    
                    if ($result['success']) {
                        $cleaned++;
                        $this->line("    ✓ Cleaned workspace {$workspace->id} ({$workspace->name})");
                    } else {
                        $this->error("    ✗ Failed to clean workspace {$workspace->id}: {$result['error']}");
                        $errors++;
                    }

                } catch (\Exception $e) {
                    $this->error("    ✗ Error cleaning workspace {$workspace->id}: {$e->getMessage()}");
                    $errors++;
                }
            }

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to cleanup workspaces: {$e->getMessage()}");
            $errors++;
        }

        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    /**
     * Clean up CloudFront cache.
     *
     * @return array
     */
    private function cleanupCloudFrontCache(): array
    {
        $cleaned = 0;
        $errors = 0;

        try {
            if (!$this->cloudFrontCleanupService->isEnabled()) {
                $this->line('  CloudFront cleanup is disabled (distribution not configured)');
                return ['cleaned' => 0, 'errors' => 0];
            }

            $cleaned = $this->cloudFrontCleanupService->cleanupStaleCache();
            
            if ($cleaned > 0) {
                $this->info("  ✓ Created {$cleaned} CloudFront invalidation(s) for stale cache");
            } else {
                $this->line('  No CloudFront invalidations needed');
            }

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to cleanup CloudFront cache: {$e->getMessage()}");
            $errors++;
        }

        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    /**
     * Display cleanup summary.
     *
     * @param array $totalCleaned
     * @param int $errors
     * @param bool $dryRun
     */
    private function displaySummary(array $totalCleaned, int $errors, bool $dryRun): void
    {
        $this->info("\n" . str_repeat('=', 50));
        $this->info('CLEANUP SUMMARY');
        $this->info(str_repeat('=', 50));

        $action = $dryRun ? 'Would clean' : 'Cleaned';

        $this->line("Workspaces: {$action} {$totalCleaned['workspaces']} items");
        $this->line("Multiplayer Sessions: {$action} {$totalCleaned['multiplayer_sessions']} items");
        $this->line("ECS Tasks: {$action} {$totalCleaned['ecs_tasks']} items");
        $this->line("CloudFront Invalidations: {$action} {$totalCleaned['cloudfront_invalidations']} items");

        $total = array_sum($totalCleaned);
        $this->info("\nTotal: {$action} {$total} items");

        if ($errors > 0) {
            $this->error("Errors encountered: {$errors}");
        }

        if (!$dryRun) {
            Log::info('Comprehensive cleanup completed', [
                'cleaned' => $totalCleaned,
                'total_cleaned' => $total,
                'errors' => $errors,
            ]);
        }
    }
}