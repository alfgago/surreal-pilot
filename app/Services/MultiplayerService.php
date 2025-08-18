<?php

namespace App\Services;

use App\Models\MultiplayerSession;
use App\Models\Workspace;
use Aws\Ecs\EcsClient;
use Aws\Exception\AwsException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MultiplayerService
{
    private EcsClient $ecsClient;
    private string $clusterName;
    private string $taskDefinition;
    private string $subnets;
    private string $securityGroups;

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
        $this->taskDefinition = config('multiplayer.task_definition', 'playcanvas-multiplayer:1');
        $this->subnets = config('multiplayer.subnets', '');
        $this->securityGroups = config('multiplayer.security_groups', '');
    }

    /**
     * Start a new multiplayer session for a workspace.
     *
     * @param Workspace $workspace
     * @param int $maxPlayers
     * @param int $ttlMinutes
     * @return array Returns [session_url, expires_at]
     * @throws \Exception
     */
    public function startSession(Workspace $workspace, int $maxPlayers = 8, int $ttlMinutes = 40): array
    {
        // Check if workspace is PlayCanvas
        if (!$workspace->isPlayCanvas()) {
            throw new \InvalidArgumentException('Multiplayer sessions are only supported for PlayCanvas workspaces');
        }

        // Check if there's already an active session
        $existingSession = $workspace->multiplayerSessions()
            ->active()
            ->first();

        if ($existingSession) {
            return [
                'session_url' => $existingSession->session_url,
                'expires_at' => $existingSession->expires_at,
                'session_id' => $existingSession->id,
            ];
        }

        // Generate unique session ID
        $sessionId = Str::uuid()->toString();
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);

        // Create session record
        $session = MultiplayerSession::create([
            'id' => $sessionId,
            'workspace_id' => $workspace->id,
            'status' => 'starting',
            'max_players' => $maxPlayers,
            'current_players' => 0,
            'expires_at' => $expiresAt,
        ]);

        try {
            // Start Fargate task
            $taskArn = $this->startFargateTask($workspace, $sessionId);
            
            // Generate ngrok tunnel URL (simulated for now)
            $ngrokUrl = $this->createNgrokTunnel($sessionId);
            
            // Update session with task details
            $session->update([
                'fargate_task_arn' => $taskArn,
                'ngrok_url' => $ngrokUrl,
                'session_url' => $ngrokUrl,
                'status' => 'active',
            ]);

            Log::info('Multiplayer session started', [
                'session_id' => $sessionId,
                'workspace_id' => $workspace->id,
                'task_arn' => $taskArn,
                'expires_at' => $expiresAt,
            ]);

            return [
                'session_url' => $ngrokUrl,
                'expires_at' => $expiresAt,
                'session_id' => $sessionId,
            ];

        } catch (\Exception $e) {
            // Mark session as failed and cleanup
            $session->update(['status' => 'stopped']);
            
            Log::error('Failed to start multiplayer session', [
                'session_id' => $sessionId,
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to start multiplayer session: ' . $e->getMessage());
        }
    }

    /**
     * Stop a multiplayer session.
     *
     * @param string $sessionId
     * @return bool
     */
    public function stopSession(string $sessionId): bool
    {
        $session = MultiplayerSession::find($sessionId);
        
        if (!$session) {
            return false;
        }

        if ($session->isStopped()) {
            return true;
        }

        try {
            $session->markAsStopping();

            // Stop Fargate task
            if ($session->fargate_task_arn) {
                $this->stopFargateTask($session->fargate_task_arn);
            }

            // Close ngrok tunnel
            if ($session->ngrok_url) {
                $this->closeNgrokTunnel($session->ngrok_url);
            }

            $session->markAsStopped();

            Log::info('Multiplayer session stopped', [
                'session_id' => $sessionId,
                'task_arn' => $session->fargate_task_arn,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to stop multiplayer session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the status of a multiplayer session.
     *
     * @param string $sessionId
     * @return array
     */
    public function getSessionStatus(string $sessionId): array
    {
        $session = MultiplayerSession::find($sessionId);
        
        if (!$session) {
            return [
                'exists' => false,
                'status' => 'not_found',
            ];
        }

        // Check if session is expired
        if ($session->isExpired() && !$session->isStopped()) {
            $this->stopSession($sessionId);
            $session->refresh();
        }

        return [
            'exists' => true,
            'status' => $session->status,
            'session_url' => $session->session_url,
            'current_players' => $session->current_players,
            'max_players' => $session->max_players,
            'expires_at' => $session->expires_at,
            'remaining_time' => $session->getRemainingTime(),
            'can_accept_players' => $session->canAcceptPlayers(),
        ];
    }

    /**
     * Clean up expired multiplayer sessions.
     *
     * @return int Number of sessions cleaned up
     */
    public function cleanupExpiredSessions(): int
    {
        $expiredSessions = MultiplayerSession::expired()
            ->whereNotIn('status', ['stopped'])
            ->get();

        $cleanedUp = 0;

        foreach ($expiredSessions as $session) {
            if ($this->stopSession($session->id)) {
                $cleanedUp++;
            }
        }

        Log::info('Cleaned up expired multiplayer sessions', [
            'count' => $cleanedUp,
        ]);

        return $cleanedUp;
    }

    /**
     * Start a Fargate task for the multiplayer server.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @return string Task ARN
     * @throws \Exception
     */
    private function startFargateTask(Workspace $workspace, string $sessionId): string
    {
        try {
            $result = $this->ecsClient->runTask([
                'cluster' => $this->clusterName,
                'taskDefinition' => $this->taskDefinition,
                'launchType' => 'FARGATE',
                'networkConfiguration' => [
                    'awsvpcConfiguration' => [
                        'subnets' => explode(',', $this->subnets),
                        'securityGroups' => explode(',', $this->securityGroups),
                        'assignPublicIp' => 'ENABLED',
                    ],
                ],
                'overrides' => [
                    'containerOverrides' => [
                        [
                            'name' => 'playcanvas-server',
                            'environment' => [
                                ['name' => 'SESSION_ID', 'value' => $sessionId],
                                ['name' => 'WORKSPACE_ID', 'value' => (string) $workspace->id],
                                ['name' => 'COMPANY_ID', 'value' => (string) $workspace->company_id],
                            ],
                        ],
                    ],
                ],
                'tags' => [
                    [
                        'key' => 'SessionId',
                        'value' => $sessionId,
                    ],
                    [
                        'key' => 'WorkspaceId',
                        'value' => (string) $workspace->id,
                    ],
                    [
                        'key' => 'Service',
                        'value' => 'PlayCanvasMultiplayer',
                    ],
                ],
            ]);

            if (empty($result['tasks'])) {
                throw new \Exception('No tasks were started');
            }

            return $result['tasks'][0]['taskArn'];

        } catch (AwsException $e) {
            throw new \Exception('Failed to start Fargate task: ' . $e->getMessage());
        }
    }

    /**
     * Stop a Fargate task.
     *
     * @param string $taskArn
     * @return bool
     */
    private function stopFargateTask(string $taskArn): bool
    {
        try {
            $this->ecsClient->stopTask([
                'cluster' => $this->clusterName,
                'task' => $taskArn,
                'reason' => 'Session ended',
            ]);

            return true;

        } catch (AwsException $e) {
            Log::error('Failed to stop Fargate task', [
                'task_arn' => $taskArn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create an ngrok tunnel for the session.
     * This is a simplified implementation - in production, you'd integrate with ngrok API.
     *
     * @param string $sessionId
     * @return string Tunnel URL
     */
    private function createNgrokTunnel(string $sessionId): string
    {
        // In a real implementation, this would:
        // 1. Call ngrok API to create a tunnel
        // 2. Return the actual tunnel URL
        // For now, we'll simulate this
        
        $tunnelId = Str::random(8);
        return "https://{$tunnelId}.ngrok.io";
    }

    /**
     * Close an ngrok tunnel.
     *
     * @param string $tunnelUrl
     * @return bool
     */
    private function closeNgrokTunnel(string $tunnelUrl): bool
    {
        // In a real implementation, this would call ngrok API to close the tunnel
        // For now, we'll just log it
        
        Log::info('Closing ngrok tunnel', ['url' => $tunnelUrl]);
        return true;
    }

    /**
     * Get all active sessions for a workspace.
     *
     * @param Workspace $workspace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveSessionsForWorkspace(Workspace $workspace)
    {
        return $workspace->multiplayerSessions()->active()->get();
    }

    /**
     * Get session statistics.
     *
     * @return array
     */
    public function getSessionStats(): array
    {
        return [
            'active_sessions' => MultiplayerSession::active()->count(),
            'total_sessions_today' => MultiplayerSession::whereDate('created_at', today())->count(),
            'expired_sessions' => MultiplayerSession::expired()->whereNotIn('status', ['stopped'])->count(),
        ];
    }
}