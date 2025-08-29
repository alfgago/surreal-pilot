<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class OnDemandMcpManager
{
    private const PORT_RANGE_START = 3001;
    private const PORT_RANGE_END = 8000; // Expanded range for more users
    private const SERVER_IDLE_TIMEOUT = 300; // 5 minutes idle timeout
    private const MAX_CONCURRENT_SERVERS = 100; // Limit concurrent servers
    
    private PlayCanvasMcpManager $mcpManager;
    
    public function __construct(PlayCanvasMcpManager $mcpManager)
    {
        $this->mcpManager = $mcpManager;
    }

    /**
     * Get or start MCP server for workspace when needed.
     */
    public function getOrStartServer(Workspace $workspace): array
    {
        if (!$workspace->isPlayCanvas()) {
            throw new Exception('Workspace is not a PlayCanvas workspace');
        }

        // Check if server is already running
        if ($workspace->mcp_port && $this->isServerHealthy($workspace)) {
            $this->updateLastActivity($workspace);
            return [
                'port' => $workspace->mcp_port,
                'pid' => $workspace->mcp_pid,
                'preview_url' => $workspace->preview_url,
                'status' => 'running'
            ];
        }

        // Check server limits
        if (!$this->canStartNewServer()) {
            // Try to free up resources by stopping idle servers
            $this->cleanupIdleServers();
            
            if (!$this->canStartNewServer()) {
                throw new Exception('Server capacity reached. Please try again in a few minutes.');
            }
        }

        // Start new server
        try {
            $result = $this->mcpManager->startServer($workspace);
            $this->updateLastActivity($workspace);
            
            Log::info('On-demand MCP server started', [
                'workspace_id' => $workspace->id,
                'port' => $result['port']
            ]);
            
            return array_merge($result, ['status' => 'started']);
            
        } catch (Exception $e) {
            Log::error('Failed to start on-demand MCP server', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send command to workspace MCP server, starting it if needed.
     */
    public function sendCommand(Workspace $workspace, string $command, ?ChatConversation $conversation = null): array
    {
        // Ensure server is running
        $this->getOrStartServer($workspace);
        
        // Send command through the MCP manager
        return $this->mcpManager->sendCommand($workspace, $command, $conversation);
    }

    /**
     * Check if we can start a new server.
     */
    private function canStartNewServer(): bool
    {
        $activeServers = $this->getActiveServerCount();
        return $activeServers < self::MAX_CONCURRENT_SERVERS;
    }

    /**
     * Get count of currently active MCP servers.
     */
    private function getActiveServerCount(): int
    {
        return Workspace::where('engine_type', 'playcanvas')
            ->whereNotNull('mcp_port')
            ->whereNotNull('mcp_pid')
            ->count();
    }

    /**
     * Clean up idle servers to free resources.
     */
    public function cleanupIdleServers(): int
    {
        $cleaned = 0;
        $cutoffTime = now()->subSeconds(self::SERVER_IDLE_TIMEOUT);
        
        $idleWorkspaces = Workspace::where('engine_type', 'playcanvas')
            ->whereNotNull('mcp_port')
            ->whereNotNull('mcp_pid')
            ->get()
            ->filter(function ($workspace) use ($cutoffTime) {
                $lastActivity = $this->getLastActivity($workspace);
                return $lastActivity && $lastActivity < $cutoffTime;
            });

        foreach ($idleWorkspaces as $workspace) {
            try {
                $this->mcpManager->stopServer($workspace);
                $cleaned++;
                
                Log::info('Cleaned up idle MCP server', [
                    'workspace_id' => $workspace->id,
                    'port' => $workspace->mcp_port
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to cleanup idle server', [
                    'workspace_id' => $workspace->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $cleaned;
    }

    /**
     * Update last activity timestamp for workspace.
     */
    private function updateLastActivity(Workspace $workspace): void
    {
        Cache::put("mcp_activity:{$workspace->id}", now(), self::SERVER_IDLE_TIMEOUT + 60);
    }

    /**
     * Get last activity timestamp for workspace.
     */
    private function getLastActivity(Workspace $workspace): ?\Carbon\Carbon
    {
        return Cache::get("mcp_activity:{$workspace->id}");
    }

    /**
     * Check if server is healthy and responding.
     */
    private function isServerHealthy(Workspace $workspace): bool
    {
        if (!$workspace->mcp_port || !$workspace->mcp_pid) {
            return false;
        }

        try {
            // Check if process is still running
            if (!$this->isProcessRunning($workspace->mcp_pid)) {
                return false;
            }

            // Check if server responds to health check
            $response = \Illuminate\Support\Facades\Http::timeout(2)
                ->get("http://localhost:{$workspace->mcp_port}/health");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if process is still running.
     */
    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>NUL");
            return $output && strpos($output, (string)$pid) !== false;
        } else {
            return file_exists("/proc/{$pid}");
        }
    }

    /**
     * Get server statistics.
     */
    public function getServerStats(): array
    {
        $activeServers = $this->getActiveServerCount();
        $maxServers = self::MAX_CONCURRENT_SERVERS;
        
        return [
            'active_servers' => $activeServers,
            'max_servers' => $maxServers,
            'utilization' => round(($activeServers / $maxServers) * 100, 1),
            'available_slots' => $maxServers - $activeServers,
            'port_range' => [
                'start' => self::PORT_RANGE_START,
                'end' => self::PORT_RANGE_END,
                'total_ports' => self::PORT_RANGE_END - self::PORT_RANGE_START + 1
            ]
        ];
    }
}