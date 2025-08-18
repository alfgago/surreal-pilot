<?php

namespace App\Services;

use App\Models\Workspace;
use App\Services\MultiplayerService;
use App\Services\MultiplayerStorageService;
use App\Services\CloudFrontCleanupService;
use App\Services\EcsCleanupService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WorkspaceCleanupService
{
    private MultiplayerService $multiplayerService;
    private MultiplayerStorageService $multiplayerStorageService;
    private CloudFrontCleanupService $cloudFrontCleanupService;
    private EcsCleanupService $ecsCleanupService;

    public function __construct(
        MultiplayerService $multiplayerService,
        MultiplayerStorageService $multiplayerStorageService,
        CloudFrontCleanupService $cloudFrontCleanupService,
        EcsCleanupService $ecsCleanupService
    ) {
        $this->multiplayerService = $multiplayerService;
        $this->multiplayerStorageService = $multiplayerStorageService;
        $this->cloudFrontCleanupService = $cloudFrontCleanupService;
        $this->ecsCleanupService = $ecsCleanupService;
    }

    /**
     * Clean up a workspace and all its associated resources.
     *
     * @param Workspace $workspace
     * @return array Returns cleanup result with success status and statistics
     */
    public function cleanupWorkspace(Workspace $workspace): array
    {
        $result = [
            'success' => false,
            'files_cleaned' => 0,
            'storage_freed' => 0,
            'sessions_terminated' => 0,
            'cloudfront_paths_cleaned' => 0,
            'ecs_tasks_cleaned' => 0,
            'error' => null,
        ];

        try {
            Log::info("Starting cleanup for workspace {$workspace->id}");

            // 1. Stop and cleanup multiplayer sessions
            $sessionsResult = $this->cleanupMultiplayerSessions($workspace);
            $result['sessions_terminated'] = $sessionsResult['terminated'];

            // 2. Stop MCP server if running
            $this->stopMcpServer($workspace);

            // 3. Clean up workspace files
            $filesResult = $this->cleanupWorkspaceFiles($workspace);
            $result['files_cleaned'] = $filesResult['files_cleaned'];
            $result['storage_freed'] = $filesResult['storage_freed'];

            // 4. Clean up build artifacts
            $buildsResult = $this->cleanupBuildArtifacts($workspace);
            $result['files_cleaned'] += $buildsResult['files_cleaned'];
            $result['storage_freed'] += $buildsResult['storage_freed'];

            // 5. Clean up CloudFront paths
            $cloudFrontResult = $this->cleanupCloudFrontPaths($workspace);
            $result['cloudfront_paths_cleaned'] = $cloudFrontResult['paths_cleaned'];

            // 6. Clean up any orphaned ECS tasks
            $ecsResult = $this->cleanupOrphanedEcsTasks($workspace);
            $result['ecs_tasks_cleaned'] = $ecsResult['tasks_cleaned'];

            // 7. Delete the workspace record
            $workspace->delete();

            $result['success'] = true;

            Log::info("Successfully cleaned up workspace {$workspace->id}", $result);

            return $result;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            
            Log::error("Failed to cleanup workspace {$workspace->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $result;
        }
    }

    /**
     * Clean up multiplayer sessions for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function cleanupMultiplayerSessions(Workspace $workspace): array
    {
        $terminated = 0;

        try {
            $sessions = $workspace->multiplayerSessions;

            foreach ($sessions as $session) {
                if (!$session->isStopped()) {
                    if ($this->multiplayerService->stopSession($session->id)) {
                        $terminated++;
                    }
                }

                // Clean up session storage files
                $this->multiplayerStorageService->cleanupSession($workspace, $session->id);
            }

            Log::info("Cleaned up {$terminated} multiplayer sessions for workspace {$workspace->id}");

            return ['terminated' => $terminated];

        } catch (\Exception $e) {
            Log::error("Failed to cleanup multiplayer sessions for workspace {$workspace->id}: {$e->getMessage()}");
            return ['terminated' => $terminated];
        }
    }

    /**
     * Stop MCP server for a workspace.
     *
     * @param Workspace $workspace
     * @return bool
     */
    private function stopMcpServer(Workspace $workspace): bool
    {
        try {
            if ($workspace->mcp_pid) {
                // Try to kill the process gracefully
                $result = Process::run("taskkill /PID {$workspace->mcp_pid} /F");
                
                if ($result->successful()) {
                    Log::info("Stopped MCP server for workspace {$workspace->id} (PID: {$workspace->mcp_pid})");
                    return true;
                } else {
                    Log::warning("Failed to stop MCP server for workspace {$workspace->id} (PID: {$workspace->mcp_pid}): {$result->errorOutput()}");
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Error stopping MCP server for workspace {$workspace->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Clean up workspace source files.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function cleanupWorkspaceFiles(Workspace $workspace): array
    {
        $filesDeleted = 0;
        $storageFreed = 0;

        try {
            $storageDisk = config('workspace.workspace_disk', 'local');
            $disk = Storage::disk($storageDisk);
            $workspacePath = "workspaces/{$workspace->company_id}/{$workspace->id}";

            if ($disk->exists($workspacePath)) {
                // Calculate storage used before deletion
                $files = $disk->allFiles($workspacePath);
                $filesDeleted = count($files);

                foreach ($files as $file) {
                    $storageFreed += $disk->size($file);
                }

                // Delete the workspace directory
                $disk->deleteDirectory($workspacePath);

                Log::info("Cleaned up workspace files for workspace {$workspace->id}: {$filesDeleted} files, " . $this->formatBytes($storageFreed));
            }

            return [
                'files_cleaned' => $filesDeleted,
                'storage_freed' => $storageFreed,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to cleanup workspace files for workspace {$workspace->id}: {$e->getMessage()}");
            return [
                'files_cleaned' => 0,
                'storage_freed' => 0,
            ];
        }
    }

    /**
     * Clean up build artifacts for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function cleanupBuildArtifacts(Workspace $workspace): array
    {
        $filesDeleted = 0;
        $storageFreed = 0;

        try {
            $metadata = $workspace->metadata ?? [];
            
            if (isset($metadata['latest_build_path'])) {
                $storageDisk = $metadata['build_storage_disk'] ?? config('workspace.builds_disk', 'local');
                $disk = Storage::disk($storageDisk);
                $buildPath = $metadata['latest_build_path'];

                if ($disk->exists($buildPath)) {
                    // Calculate storage used before deletion
                    $files = $disk->allFiles($buildPath);
                    $filesDeleted = count($files);

                    foreach ($files as $file) {
                        $storageFreed += $disk->size($file);
                    }

                    // Delete the build directory
                    $disk->deleteDirectory($buildPath);

                    Log::info("Cleaned up build artifacts for workspace {$workspace->id}: {$filesDeleted} files, " . $this->formatBytes($storageFreed));
                }
            }

            // Also clean up any other build artifacts in the builds directory
            $buildsStorageDisk = config('workspace.builds_disk', 'local');
            $buildsDisk = Storage::disk($buildsStorageDisk);
            $buildsPath = "builds/{$workspace->company_id}/{$workspace->id}";

            if ($buildsDisk->exists($buildsPath)) {
                $additionalFiles = $buildsDisk->allFiles($buildsPath);
                $additionalFilesCount = count($additionalFiles);
                $additionalStorage = 0;

                foreach ($additionalFiles as $file) {
                    $additionalStorage += $buildsDisk->size($file);
                }

                $buildsDisk->deleteDirectory($buildsPath);

                $filesDeleted += $additionalFilesCount;
                $storageFreed += $additionalStorage;

                if ($additionalFilesCount > 0) {
                    Log::info("Cleaned up additional build artifacts for workspace {$workspace->id}: {$additionalFilesCount} files, " . $this->formatBytes($additionalStorage));
                }
            }

            return [
                'files_cleaned' => $filesDeleted,
                'storage_freed' => $storageFreed,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to cleanup build artifacts for workspace {$workspace->id}: {$e->getMessage()}");
            return [
                'files_cleaned' => 0,
                'storage_freed' => 0,
            ];
        }
    }

    /**
     * Clean up CloudFront paths for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function cleanupCloudFrontPaths(Workspace $workspace): array
    {
        try {
            $pathsCleaned = $this->cloudFrontCleanupService->cleanupWorkspacePaths($workspace);

            return ['paths_cleaned' => $pathsCleaned];

        } catch (\Exception $e) {
            Log::error("Failed to cleanup CloudFront paths for workspace {$workspace->id}: {$e->getMessage()}");
            return ['paths_cleaned' => 0];
        }
    }

    /**
     * Clean up orphaned ECS tasks for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function cleanupOrphanedEcsTasks(Workspace $workspace): array
    {
        try {
            $tasksCleaned = $this->ecsCleanupService->cleanupWorkspaceTasks($workspace);

            return ['tasks_cleaned' => $tasksCleaned];

        } catch (\Exception $e) {
            Log::error("Failed to cleanup ECS tasks for workspace {$workspace->id}: {$e->getMessage()}");
            return ['tasks_cleaned' => 0];
        }
    }

    /**
     * Format bytes into human readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get cleanup statistics for all workspaces.
     *
     * @return array
     */
    public function getCleanupStats(): array
    {
        try {
            $totalWorkspaces = Workspace::count();
            $oldWorkspaces = Workspace::where('created_at', '<', now()->subHours(24))->count();
            $activeMultiplayerSessions = \App\Models\MultiplayerSession::active()->count();
            $expiredMultiplayerSessions = \App\Models\MultiplayerSession::expired()
                ->whereNotIn('status', ['stopped'])
                ->count();

            return [
                'total_workspaces' => $totalWorkspaces,
                'old_workspaces' => $oldWorkspaces,
                'active_multiplayer_sessions' => $activeMultiplayerSessions,
                'expired_multiplayer_sessions' => $expiredMultiplayerSessions,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get cleanup stats: {$e->getMessage()}");
            return [
                'total_workspaces' => 0,
                'old_workspaces' => 0,
                'active_multiplayer_sessions' => 0,
                'expired_multiplayer_sessions' => 0,
            ];
        }
    }
}