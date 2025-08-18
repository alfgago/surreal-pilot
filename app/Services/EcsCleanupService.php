<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\MultiplayerSession;
use Aws\Ecs\EcsClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class EcsCleanupService
{
    private EcsClient $ecsClient;
    private string $clusterName;

    public function __construct(?EcsClient $ecsClient = null)
    {
        if ($ecsClient) {
            $this->ecsClient = $ecsClient;
        } else {
            $credentials = [];
            
            // Only set credentials if they are configured
            if (config('aws.access_key_id') && config('aws.secret_access_key')) {
                $credentials = [
                    'key' => config('aws.access_key_id'),
                    'secret' => config('aws.secret_access_key'),
                ];
            }

            $this->ecsClient = new EcsClient([
                'region' => config('aws.region', 'us-east-1'),
                'version' => 'latest',
                'credentials' => $credentials ?: false, // Use false for default provider chain
            ]);
        }

        $this->clusterName = config('multiplayer.ecs_cluster', 'playcanvas-multiplayer');
    }

    /**
     * Clean up ECS tasks for a workspace.
     *
     * @param Workspace $workspace
     * @return int Number of tasks cleaned up
     */
    public function cleanupWorkspaceTasks(Workspace $workspace): int
    {
        $tasksCleaned = 0;

        try {
            // Get all multiplayer sessions for this workspace
            $sessions = $workspace->multiplayerSessions;

            foreach ($sessions as $session) {
                if ($session->fargate_task_arn) {
                    if ($this->stopTask($session->fargate_task_arn, "Workspace {$workspace->id} cleanup")) {
                        $tasksCleaned++;
                    }
                }
            }

            // Also look for orphaned tasks by tags
            $orphanedTasks = $this->findOrphanedTasksForWorkspace($workspace);
            
            foreach ($orphanedTasks as $taskArn) {
                if ($this->stopTask($taskArn, "Orphaned task cleanup for workspace {$workspace->id}")) {
                    $tasksCleaned++;
                }
            }

            if ($tasksCleaned > 0) {
                Log::info("Cleaned up {$tasksCleaned} ECS tasks for workspace {$workspace->id}");
            }

            return $tasksCleaned;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup ECS tasks for workspace {$workspace->id}: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Clean up all orphaned ECS tasks.
     *
     * @return int Number of tasks cleaned up
     */
    public function cleanupOrphanedTasks(): int
    {
        $tasksCleaned = 0;

        try {
            // Find all running tasks in the cluster
            $runningTasks = $this->listRunningTasks();

            foreach ($runningTasks as $taskArn) {
                // Check if this task is associated with an active session
                if ($this->isOrphanedTask($taskArn)) {
                    if ($this->stopTask($taskArn, "Orphaned task cleanup")) {
                        $tasksCleaned++;
                    }
                }
            }

            if ($tasksCleaned > 0) {
                Log::info("Cleaned up {$tasksCleaned} orphaned ECS tasks");
            }

            return $tasksCleaned;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup orphaned ECS tasks: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Clean up expired multiplayer session tasks.
     *
     * @return int Number of tasks cleaned up
     */
    public function cleanupExpiredSessionTasks(): int
    {
        $tasksCleaned = 0;

        try {
            // Get expired sessions that still have running tasks
            $expiredSessions = MultiplayerSession::expired()
                ->whereNotNull('fargate_task_arn')
                ->whereNotIn('status', ['stopped'])
                ->get();

            foreach ($expiredSessions as $session) {
                if ($this->stopTask($session->fargate_task_arn, "Expired session cleanup")) {
                    $tasksCleaned++;
                    
                    // Update session status
                    $session->markAsStopped();
                }
            }

            if ($tasksCleaned > 0) {
                Log::info("Cleaned up {$tasksCleaned} expired session ECS tasks");
            }

            return $tasksCleaned;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup expired session ECS tasks: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Stop an ECS task.
     *
     * @param string $taskArn
     * @param string $reason
     * @return bool
     */
    private function stopTask(string $taskArn, string $reason = 'Cleanup'): bool
    {
        try {
            $this->ecsClient->stopTask([
                'cluster' => $this->clusterName,
                'task' => $taskArn,
                'reason' => $reason,
            ]);

            Log::info("Stopped ECS task: {$taskArn}", ['reason' => $reason]);
            return true;

        } catch (AwsException $e) {
            // Task might already be stopped or not exist
            if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                Log::info("ECS task already stopped or not found: {$taskArn}");
                return true;
            }

            Log::error("Failed to stop ECS task {$taskArn}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * List all running tasks in the cluster.
     *
     * @return array Array of task ARNs
     */
    private function listRunningTasks(): array
    {
        try {
            $result = $this->ecsClient->listTasks([
                'cluster' => $this->clusterName,
                'desiredStatus' => 'RUNNING',
            ]);

            return $result['taskArns'] ?? [];

        } catch (AwsException $e) {
            Log::error("Failed to list running ECS tasks: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Find orphaned tasks for a specific workspace.
     *
     * @param Workspace $workspace
     * @return array Array of task ARNs
     */
    private function findOrphanedTasksForWorkspace(Workspace $workspace): array
    {
        try {
            // List tasks with workspace tag
            $result = $this->ecsClient->listTasks([
                'cluster' => $this->clusterName,
                'desiredStatus' => 'RUNNING',
            ]);

            $taskArns = $result['taskArns'] ?? [];
            
            if (empty($taskArns)) {
                return [];
            }

            // Describe tasks to get their tags
            $describedTasks = $this->ecsClient->describeTasks([
                'cluster' => $this->clusterName,
                'tasks' => $taskArns,
                'include' => ['TAGS'],
            ]);

            $orphanedTasks = [];

            foreach ($describedTasks['tasks'] ?? [] as $task) {
                $tags = $task['tags'] ?? [];
                $workspaceId = null;

                // Find workspace ID in tags
                foreach ($tags as $tag) {
                    if ($tag['key'] === 'WorkspaceId') {
                        $workspaceId = $tag['value'];
                        break;
                    }
                }

                // If this task belongs to the workspace we're cleaning up
                if ($workspaceId == $workspace->id) {
                    $taskArn = $task['taskArn'];
                    
                    // Check if this task is associated with an active session
                    $hasActiveSession = MultiplayerSession::where('fargate_task_arn', $taskArn)
                        ->where('workspace_id', $workspace->id)
                        ->whereNotIn('status', ['stopped'])
                        ->exists();

                    if (!$hasActiveSession) {
                        $orphanedTasks[] = $taskArn;
                    }
                }
            }

            return $orphanedTasks;

        } catch (AwsException $e) {
            Log::error("Failed to find orphaned tasks for workspace {$workspace->id}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Check if a task is orphaned (not associated with an active session).
     *
     * @param string $taskArn
     * @return bool
     */
    private function isOrphanedTask(string $taskArn): bool
    {
        try {
            // Check if this task is associated with an active multiplayer session
            $hasActiveSession = MultiplayerSession::where('fargate_task_arn', $taskArn)
                ->whereNotIn('status', ['stopped'])
                ->where('expires_at', '>', now())
                ->exists();

            return !$hasActiveSession;

        } catch (\Exception $e) {
            Log::error("Failed to check if task is orphaned {$taskArn}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get ECS cluster statistics.
     *
     * @return array
     */
    public function getClusterStats(): array
    {
        try {
            // Get cluster information
            $clusterResult = $this->ecsClient->describeClusters([
                'clusters' => [$this->clusterName],
                'include' => ['STATISTICS'],
            ]);

            $cluster = $clusterResult['clusters'][0] ?? null;

            if (!$cluster) {
                return [
                    'cluster_exists' => false,
                    'running_tasks' => 0,
                    'pending_tasks' => 0,
                    'active_services' => 0,
                ];
            }

            $statistics = $cluster['statistics'] ?? [];
            $runningTasks = 0;
            $pendingTasks = 0;
            $activeServices = 0;

            foreach ($statistics as $stat) {
                switch ($stat['name']) {
                    case 'runningTasksCount':
                        $runningTasks = (int) $stat['value'];
                        break;
                    case 'pendingTasksCount':
                        $pendingTasks = (int) $stat['value'];
                        break;
                    case 'activeServicesCount':
                        $activeServices = (int) $stat['value'];
                        break;
                }
            }

            return [
                'cluster_exists' => true,
                'cluster_name' => $cluster['clusterName'],
                'status' => $cluster['status'],
                'running_tasks' => $runningTasks,
                'pending_tasks' => $pendingTasks,
                'active_services' => $activeServices,
                'registered_container_instances' => $cluster['registeredContainerInstancesCount'] ?? 0,
            ];

        } catch (AwsException $e) {
            Log::error("Failed to get ECS cluster stats: {$e->getMessage()}");
            return [
                'cluster_exists' => false,
                'error' => $e->getMessage(),
                'running_tasks' => 0,
                'pending_tasks' => 0,
                'active_services' => 0,
            ];
        }
    }

    /**
     * Check if ECS cleanup is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->clusterName);
    }
}