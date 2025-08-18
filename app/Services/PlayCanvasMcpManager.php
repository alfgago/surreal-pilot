<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class PlayCanvasMcpManager
{
    private const MCP_SERVER_PATH = 'vendor/pc_mcp';
    private const PORT_RANGE_START = 3001;
    private const PORT_RANGE_END = 4000;
    private const HEALTH_CHECK_TIMEOUT = 5;
    private const MAX_RESTART_ATTEMPTS = 3;

    private static array $runningServers = [];
    private static array $portRegistry = [];

    /**
     * Start a PlayCanvas MCP server for the given workspace.
     *
     * @param Workspace $workspace
     * @return array Returns [port, pid, preview_url]
     * @throws Exception
     */
    public function startServer(Workspace $workspace): array
    {
        if (!$workspace->isPlayCanvas()) {
            throw new Exception('Workspace is not a PlayCanvas workspace');
        }

        // Find available port
        $port = $this->findAvailablePort();
        if (!$port) {
            throw new Exception('No available ports for MCP server');
        }

        // Start the MCP server process
        $pid = $this->startMcpProcess($workspace, $port);

        // Wait for server to be ready
        $this->waitForServerReady($port);

        // Generate preview URL
        $previewUrl = $this->generatePreviewUrl($workspace, $port);

        // Register the running server
        $this->registerRunningServer($workspace, $port, $pid);

        // Update workspace with server details
        $workspace->update([
            'mcp_port' => $port,
            'mcp_pid' => $pid,
            'preview_url' => $previewUrl,
            'status' => 'ready'
        ]);

        Log::info("PlayCanvas MCP server started", [
            'workspace_id' => $workspace->id,
            'port' => $port,
            'pid' => $pid
        ]);

        return [
            'port' => $port,
            'pid' => $pid,
            'preview_url' => $previewUrl
        ];
    }

    /**
     * Stop the MCP server for the given workspace.
     *
     * @param Workspace $workspace
     * @return bool
     */
    public function stopServer(Workspace $workspace): bool
    {
        if (!$workspace->mcp_pid) {
            return true; // Already stopped
        }

        $result = $this->killProcess($workspace->mcp_pid);

        if ($result) {
            // Unregister the server
            $this->unregisterRunningServer($workspace);

            // Clear workspace server details
            $workspace->update([
                'mcp_port' => null,
                'mcp_pid' => null,
                'status' => 'initializing'
            ]);
        }

        return $result;
    }

    /**
     * Send a command to the MCP server.
     *
     * @param Workspace $workspace
     * @param string $command
     * @param \App\Models\ChatConversation|null $conversation
     * @return array
     * @throws Exception
     */
    public function sendCommand(Workspace $workspace, string $command, ?\App\Models\ChatConversation $conversation = null): array
    {
        if (!$workspace->mcp_port) {
            throw new Exception('MCP server is not running for this workspace');
        }

        $serverUrl = $workspace->getMcpServerUrl();

        try {
            $response = Http::timeout(30)->post("{$serverUrl}/v1/command", [
                'command' => $command,
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation?->id
            ]);

            if (!$response->successful()) {
                throw new Exception("MCP server returned error: " . $response->body());
            }

            $result = $response->json();
            
            // Check if the command resulted in game creation and handle it
            $this->handleGameCreationFromCommand($workspace, $command, $result, $conversation);

            return $result;
        } catch (Exception $e) {
            Log::error("Failed to send command to MCP server", [
                'workspace_id' => $workspace->id,
                'command' => $command,
                'error' => $e->getMessage()
            ]);

            // Try to restart server if it's not responding
            if ($this->shouldRestartServer($e)) {
                $this->restartServer($workspace);
            }

            throw $e;
        }
    }

    /**
     * Request an undo from the MCP server for a given patch_id.
     *
     * @param Workspace $workspace
     * @param string $patchId
     * @return array
     * @throws Exception
     */
    public function undo(Workspace $workspace, string $patchId): array
    {
        if (!$workspace->mcp_port) {
            throw new Exception('MCP server is not running for this workspace');
        }

        $serverUrl = $workspace->getMcpServerUrl();

        try {
            $response = Http::timeout(30)->post("{$serverUrl}/v1/undo", [
                'patch_id' => $patchId,
                'workspace_id' => $workspace->id,
            ]);

            if (!$response->successful()) {
                throw new Exception("MCP server returned error on undo: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error("Failed to request undo from MCP server", [
                'workspace_id' => $workspace->id,
                'patch_id' => $patchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the status of the MCP server.
     *
     * @param Workspace $workspace
     * @return string
     */
    public function getServerStatus(Workspace $workspace): string
    {
        if (!$workspace->mcp_port || !$workspace->mcp_pid) {
            return 'stopped';
        }

        // Check if process is still running
        if (!$this->isProcessRunning($workspace->mcp_pid)) {
            return 'stopped';
        }

        // Check if server is responding
        if (!$this->isServerHealthy($workspace->mcp_port)) {
            return 'unhealthy';
        }

        return 'running';
    }

    /**
     * Restart the MCP server for the given workspace.
     *
     * @param Workspace $workspace
     * @return array
     * @throws Exception
     */
    public function restartServer(Workspace $workspace): array
    {
        Log::info("Restarting PlayCanvas MCP server", [
            'workspace_id' => $workspace->id
        ]);

        // Stop existing server
        $this->stopServer($workspace);

        // Clear server details
        $workspace->update([
            'mcp_port' => null,
            'mcp_pid' => null,
            'status' => 'initializing'
        ]);

        // Start new server
        return $this->startServer($workspace);
    }

    /**
     * Find an available port in the defined range.
     *
     * @return int|null
     */
    private function findAvailablePort(): ?int
    {
        // Clean up stale port registrations
        $this->cleanupStalePortRegistrations();

        for ($port = self::PORT_RANGE_START; $port <= self::PORT_RANGE_END; $port++) {
            if ($this->isPortAvailable($port) && !isset(self::$portRegistry[$port])) {
                self::$portRegistry[$port] = time();
                return $port;
            }
        }

        return null;
    }

    /**
     * Clean up stale port registrations.
     */
    private function cleanupStalePortRegistrations(): void
    {
        $currentTime = time();

        foreach (self::$portRegistry as $port => $timestamp) {
            // Remove registrations older than 5 minutes
            if ($currentTime - $timestamp > 300) {
                unset(self::$portRegistry[$port]);
            }
        }
    }

    /**
     * Register a running server.
     *
     * @param Workspace $workspace
     * @param int $port
     * @param int $pid
     */
    private function registerRunningServer(Workspace $workspace, int $port, int $pid): void
    {
        self::$runningServers[$workspace->id] = [
            'port' => $port,
            'pid' => $pid,
            'started_at' => time(),
            'workspace_id' => $workspace->id
        ];
    }

    /**
     * Unregister a running server.
     *
     * @param Workspace $workspace
     */
    private function unregisterRunningServer(Workspace $workspace): void
    {
        if (isset(self::$runningServers[$workspace->id])) {
            $serverInfo = self::$runningServers[$workspace->id];
            unset(self::$portRegistry[$serverInfo['port']]);
            unset(self::$runningServers[$workspace->id]);
        }
    }

    /**
     * Get all running servers.
     *
     * @return array
     */
    public static function getRunningServers(): array
    {
        return self::$runningServers;
    }

    /**
     * Get server info for a workspace.
     *
     * @param Workspace $workspace
     * @return array|null
     */
    public function getServerInfo(Workspace $workspace): ?array
    {
        return self::$runningServers[$workspace->id] ?? null;
    }

    /**
     * Check if a port is available.
     *
     * @param int $port
     * @return bool
     */
    private function isPortAvailable(int $port): bool
    {
        $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);

        if ($connection) {
            fclose($connection);
            return false; // Port is in use
        }

        return true; // Port is available
    }

    /**
     * Start the MCP server process.
     *
     * @param Workspace $workspace
     * @param int $port
     * @return int Process ID
     * @throws Exception
     */
    private function startMcpProcess(Workspace $workspace, int $port): int
    {
        $mcpPath = base_path(self::MCP_SERVER_PATH);
        $workspacePath = storage_path("workspaces/{$workspace->id}");

        if (!is_dir($mcpPath)) {
            throw new Exception('PlayCanvas MCP server not found. Please run git submodule update --init');
        }

        // Ensure workspace directory exists
        if (!is_dir($workspacePath)) {
            mkdir($workspacePath, 0755, true);
        }

        $command = [
            'node',
            'server.js',
            '--port=' . $port,
            '--workspace=' . $workspacePath
        ];

        $process = Process::path($mcpPath)
            ->start($command);

        if (!$process->running()) {
            throw new Exception('Failed to start PlayCanvas MCP server: ' . $process->errorOutput());
        }

        return $process->id();
    }

    /**
     * Wait for the server to be ready.
     *
     * @param int $port
     * @throws Exception
     */
    private function waitForServerReady(int $port): void
    {
        $maxAttempts = 30; // 30 seconds
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            if ($this->isServerHealthy($port)) {
                return;
            }

            sleep(1);
            $attempt++;
        }

        throw new Exception('MCP server failed to start within timeout period');
    }

    /**
     * Check if the server is healthy.
     *
     * @param int $port
     * @return bool
     */
    private function isServerHealthy(int $port): bool
    {
        try {
            $response = Http::timeout(self::HEALTH_CHECK_TIMEOUT)
                ->get("http://localhost:{$port}/health");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if a process is running.
     *
     * @param int $pid
     * @return bool
     */
    private function isProcessRunning(int $pid): bool
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $result = Process::run("tasklist /FI \"PID eq {$pid}\" /FO CSV");
                return str_contains($result->output(), (string)$pid);
            } else {
                $result = Process::run("ps -p {$pid}");
                return $result->successful();
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Kill a process by PID.
     *
     * @param int $pid
     * @return bool
     */
    private function killProcess(int $pid): bool
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $result = Process::run("taskkill /PID {$pid} /F");
            } else {
                $result = Process::run("kill -9 {$pid}");
            }

            Log::info("Killed MCP server process", ['pid' => $pid]);
            return $result->successful();
        } catch (Exception $e) {
            Log::error("Failed to kill MCP server process", [
                'pid' => $pid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate preview URL for the workspace.
     *
     * @param Workspace $workspace
     * @param int $port
     * @return string
     */
    private function generatePreviewUrl(Workspace $workspace, int $port): string
    {
        return "http://localhost:{$port}/preview/{$workspace->id}";
    }

    /**
     * Perform comprehensive health check on the MCP server.
     *
     * @param Workspace $workspace
     * @return array
     */
    public function performHealthCheck(Workspace $workspace): array
    {
        $healthStatus = [
            'workspace_id' => $workspace->id,
            'timestamp' => now()->toISOString(),
            'overall_status' => 'healthy',
            'checks' => []
        ];

        // Check if process is running
        $processCheck = $this->checkProcessHealth($workspace);
        $healthStatus['checks']['process'] = $processCheck;

        // Check if server is responding
        $serverCheck = $this->checkServerHealth($workspace);
        $healthStatus['checks']['server'] = $serverCheck;

        // Check workspace files
        $filesCheck = $this->checkWorkspaceFiles($workspace);
        $healthStatus['checks']['files'] = $filesCheck;

        // Determine overall status
        $allHealthy = collect($healthStatus['checks'])->every(fn($check) => $check['status'] === 'healthy');
        $healthStatus['overall_status'] = $allHealthy ? 'healthy' : 'unhealthy';

        return $healthStatus;
    }

    /**
     * Check process health.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function checkProcessHealth(Workspace $workspace): array
    {
        if (!$workspace->mcp_pid) {
            return [
                'status' => 'unhealthy',
                'message' => 'No process ID recorded',
                'details' => null
            ];
        }

        $isRunning = $this->isProcessRunning($workspace->mcp_pid);

        return [
            'status' => $isRunning ? 'healthy' : 'unhealthy',
            'message' => $isRunning ? 'Process is running' : 'Process not found',
            'details' => [
                'pid' => $workspace->mcp_pid,
                'running' => $isRunning
            ]
        ];
    }

    /**
     * Check server health.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function checkServerHealth(Workspace $workspace): array
    {
        if (!$workspace->mcp_port) {
            return [
                'status' => 'unhealthy',
                'message' => 'No port assigned',
                'details' => null
            ];
        }

        try {
            $response = Http::timeout(self::HEALTH_CHECK_TIMEOUT)
                ->get("http://localhost:{$workspace->mcp_port}/health");

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'status' => 'healthy',
                    'message' => 'Server responding normally',
                    'details' => [
                        'port' => $workspace->mcp_port,
                        'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                        'server_data' => $responseData
                    ]
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Server returned error status',
                    'details' => [
                        'port' => $workspace->mcp_port,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Server not responding',
                'details' => [
                    'port' => $workspace->mcp_port,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Check workspace files health.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function checkWorkspaceFiles(Workspace $workspace): array
    {
        $workspacePath = storage_path("workspaces/{$workspace->id}");

        if (!is_dir($workspacePath)) {
            return [
                'status' => 'unhealthy',
                'message' => 'Workspace directory not found',
                'details' => ['path' => $workspacePath]
            ];
        }

        $requiredFiles = ['package.json'];
        $missingFiles = [];

        foreach ($requiredFiles as $file) {
            if (!file_exists($workspacePath . DIRECTORY_SEPARATOR . $file)) {
                $missingFiles[] = $file;
            }
        }

        if (!empty($missingFiles)) {
            return [
                'status' => 'unhealthy',
                'message' => 'Required files missing',
                'details' => [
                    'path' => $workspacePath,
                    'missing_files' => $missingFiles
                ]
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'All required files present',
            'details' => ['path' => $workspacePath]
        ];
    }

    /**
     * Auto-restart server with exponential backoff.
     *
     * @param Workspace $workspace
     * @param int $attempt
     * @return array
     * @throws Exception
     */
    public function autoRestartServer(Workspace $workspace, int $attempt = 1): array
    {
        if ($attempt > self::MAX_RESTART_ATTEMPTS) {
            throw new Exception("Failed to restart server after {$attempt} attempts");
        }

        Log::warning("Auto-restarting PlayCanvas MCP server", [
            'workspace_id' => $workspace->id,
            'attempt' => $attempt
        ]);

        try {
            // Stop existing server
            $this->stopServer($workspace);

            // Wait with exponential backoff
            $waitTime = min(pow(2, $attempt - 1), 30); // Max 30 seconds
            sleep($waitTime);

            // Start new server
            return $this->startServer($workspace);

        } catch (Exception $e) {
            Log::error("Auto-restart attempt failed", [
                'workspace_id' => $workspace->id,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            // Try again with next attempt
            return $this->autoRestartServer($workspace, $attempt + 1);
        }
    }

    /**
     * Monitor all running servers and restart unhealthy ones.
     *
     * @return array
     */
    public function monitorAllServers(): array
    {
        $results = [];

        foreach (self::$runningServers as $workspaceId => $serverInfo) {
            $workspace = Workspace::find($workspaceId);

            if (!$workspace) {
                // Clean up orphaned server info
                unset(self::$runningServers[$workspaceId]);
                continue;
            }

            $healthCheck = $this->performHealthCheck($workspace);
            $results[$workspaceId] = $healthCheck;

            // Auto-restart if unhealthy
            if ($healthCheck['overall_status'] === 'unhealthy') {
                try {
                    $restartResult = $this->autoRestartServer($workspace);
                    $results[$workspaceId]['restart_attempted'] = true;
                    $results[$workspaceId]['restart_result'] = $restartResult;
                } catch (Exception $e) {
                    $results[$workspaceId]['restart_attempted'] = true;
                    $results[$workspaceId]['restart_error'] = $e->getMessage();

                    // Mark workspace as error state
                    $workspace->update(['status' => 'error']);
                }
            }
        }

        return $results;
    }

    /**
     * Handle game creation from MCP command results.
     *
     * @param Workspace $workspace
     * @param string $command
     * @param array $mcpResult
     * @param \App\Models\ChatConversation|null $conversation
     * @return void
     */
    private function handleGameCreationFromCommand(
        Workspace $workspace, 
        string $command, 
        array $mcpResult, 
        ?\App\Models\ChatConversation $conversation = null
    ): void {
        // Check if this command resulted in a game being created
        if (!$this->isGameCreationCommand($command, $mcpResult)) {
            return;
        }

        try {
            // Extract game information from the command and result
            $gameInfo = $this->extractGameInfoFromCommand($command, $mcpResult);
            
            // Check if a game with this title already exists in this workspace
            $existingGame = \App\Models\Game::where('workspace_id', $workspace->id)
                ->where('title', $gameInfo['title'])
                ->first();

            if ($existingGame) {
                // Update existing game with new information
                $this->updateExistingGame($existingGame, $gameInfo, $conversation);
                
                Log::info('Updated existing game from MCP command', [
                    'workspace_id' => $workspace->id,
                    'game_id' => $existingGame->id,
                    'conversation_id' => $conversation?->id,
                    'command_preview' => substr($command, 0, 100)
                ]);
            } else {
                // Create new game record
                $game = $this->createGameFromCommand($workspace, $gameInfo, $conversation);
                
                Log::info('Created new game from MCP command', [
                    'workspace_id' => $workspace->id,
                    'game_id' => $game->id,
                    'conversation_id' => $conversation?->id,
                    'command_preview' => substr($command, 0, 100)
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the MCP command
            Log::error('Failed to create/update game from MCP command', [
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation?->id,
                'command' => substr($command, 0, 200),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if the command and result indicate game creation.
     *
     * @param string $command
     * @param array $mcpResult
     * @return bool
     */
    private function isGameCreationCommand(string $command, array $mcpResult): bool
    {
        // Check for game creation indicators in the command
        $gameCreationPatterns = [
            '/create.*game/i',
            '/new.*game/i',
            '/build.*game/i',
            '/make.*game/i',
            '/generate.*game/i',
            '/start.*project/i',
            '/create.*project/i',
            '/platformer/i',
            '/fps/i',
            '/third.*person/i',
            '/racing/i',
            '/puzzle/i',
            '/tower.*defense/i'
        ];

        foreach ($gameCreationPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        // Check for game creation indicators in the MCP result
        if (isset($mcpResult['success']) && $mcpResult['success']) {
            if (isset($mcpResult['changes'])) {
                foreach ($mcpResult['changes'] as $change) {
                    if (isset($change['type']) && in_array($change['type'], ['project_creation', 'game_creation', 'template_instantiation'])) {
                        return true;
                    }
                }
            }
            
            // Check if preview URL was generated (indicates a playable game)
            if (isset($mcpResult['preview_url']) || isset($mcpResult['published_url'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract game information from command and MCP result.
     *
     * @param string $command
     * @param array $mcpResult
     * @return array
     */
    private function extractGameInfoFromCommand(string $command, array $mcpResult): array
    {
        $gameInfo = [
            'title' => $this->extractGameTitle($command, $mcpResult),
            'description' => $this->extractGameDescription($command, $mcpResult),
            'preview_url' => $mcpResult['preview_url'] ?? null,
            'published_url' => $mcpResult['published_url'] ?? null,
            'thumbnail_url' => $mcpResult['thumbnail_url'] ?? null,
            'metadata' => [
                'created_from_command' => true,
                'original_command' => substr($command, 0, 500),
                'mcp_result' => $mcpResult,
                'creation_timestamp' => now()->toISOString()
            ]
        ];

        return $gameInfo;
    }

    /**
     * Extract game title from command or result.
     *
     * @param string $command
     * @param array $mcpResult
     * @return string
     */
    private function extractGameTitle(string $command, array $mcpResult): string
    {
        // Check if title is provided in MCP result
        if (isset($mcpResult['title'])) {
            return $mcpResult['title'];
        }

        // Try to extract title from command
        $patterns = [
            '/create.*game.*called\s+"([^"]+)"/i',
            '/create.*game.*named\s+"([^"]+)"/i',
            '/make.*game.*called\s+"([^"]+)"/i',
            '/build.*"([^"]+)".*game/i',
            '/create.*"([^"]+)"/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $command, $matches)) {
                return trim($matches[1]);
            }
        }

        // Detect game type from command
        $gameTypes = [
            'platformer' => 'Platformer Game',
            'fps' => 'FPS Game',
            'third.*person' => 'Third Person Game',
            'racing' => 'Racing Game',
            'puzzle' => 'Puzzle Game',
            'tower.*defense' => 'Tower Defense Game'
        ];

        foreach ($gameTypes as $pattern => $title) {
            if (preg_match("/$pattern/i", $command)) {
                return $title;
            }
        }

        // Default title with timestamp
        return 'PlayCanvas Game ' . now()->format('Y-m-d H:i');
    }

    /**
     * Extract game description from command.
     *
     * @param string $command
     * @param array $mcpResult
     * @return string|null
     */
    private function extractGameDescription(string $command, array $mcpResult): ?string
    {
        // Check if description is provided in MCP result
        if (isset($mcpResult['description'])) {
            return $mcpResult['description'];
        }

        // Use the command itself as description (truncated)
        return 'Created from command: ' . substr($command, 0, 200);
    }

    /**
     * Create a new game record from MCP command.
     *
     * @param Workspace $workspace
     * @param array $gameInfo
     * @param \App\Models\ChatConversation|null $conversation
     * @return \App\Models\Game
     */
    private function createGameFromCommand(
        Workspace $workspace, 
        array $gameInfo, 
        ?\App\Models\ChatConversation $conversation = null
    ): \App\Models\Game {
        $gameStorageService = app(\App\Services\GameStorageService::class);
        
        $game = $gameStorageService->createGame(
            $workspace,
            $gameInfo['title'],
            $conversation
        );

        // Update with additional information
        $updateData = array_filter([
            'description' => $gameInfo['description'],
            'preview_url' => $gameInfo['preview_url'],
            'published_url' => $gameInfo['published_url'],
            'thumbnail_url' => $gameInfo['thumbnail_url'],
            'metadata' => $gameInfo['metadata']
        ]);

        if (!empty($updateData)) {
            $game->update($updateData);
        }

        return $game->fresh();
    }

    /**
     * Update existing game with new information.
     *
     * @param \App\Models\Game $game
     * @param array $gameInfo
     * @param \App\Models\ChatConversation|null $conversation
     * @return void
     */
    private function updateExistingGame(
        \App\Models\Game $game, 
        array $gameInfo, 
        ?\App\Models\ChatConversation $conversation = null
    ): void {
        $updateData = [];

        // Update URLs if provided
        if (!empty($gameInfo['preview_url'])) {
            $updateData['preview_url'] = $gameInfo['preview_url'];
        }
        
        if (!empty($gameInfo['published_url'])) {
            $updateData['published_url'] = $gameInfo['published_url'];
        }
        
        if (!empty($gameInfo['thumbnail_url'])) {
            $updateData['thumbnail_url'] = $gameInfo['thumbnail_url'];
        }

        // Update conversation association if provided and not already set
        if ($conversation && !$game->conversation_id) {
            $updateData['conversation_id'] = $conversation->id;
        }

        // Merge metadata
        $existingMetadata = $game->metadata ?? [];
        $newMetadata = array_merge($existingMetadata, $gameInfo['metadata'] ?? []);
        $updateData['metadata'] = $newMetadata;

        if (!empty($updateData)) {
            $game->update($updateData);
        }
    }

    /**
     * Determine if server should be restarted based on the exception.
     *
     * @param Exception $e
     * @return bool
     */
    private function shouldRestartServer(Exception $e): bool
    {
        $restartableErrors = [
            'Connection refused',
            'Connection timeout',
            'Server not responding',
            'cURL error 7', // Couldn't connect to server
            'cURL error 28', // Operation timeout
        ];

        foreach ($restartableErrors as $error) {
            if (str_contains($e->getMessage(), $error)) {
                return true;
            }
        }

        return false;
    }
}
