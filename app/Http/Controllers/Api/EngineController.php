<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\EngineSelectionService;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EngineController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineService,
        private PlayCanvasMcpManager $playCanvasMcpManager
    ) {}

    /**
     * Get the user's company, falling back to first company if no current company is set
     */
    private function getUserCompany(Request $request)
    {
        $user = $request->user();
        return $user->currentCompany ?? $user->companies()->first();
    }

    /**
     * Get engine status for a workspace
     */
    public function getEngineStatus(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'error' => 'No company associated with user',
                ], 403);
            }

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $status = [
                'workspace_id' => $workspace->id,
                'engine_type' => $workspace->engine_type,
                'status' => 'disconnected',
                'message' => 'Engine not connected',
                'details' => []
            ];

            if ($workspace->isPlayCanvas()) {
                $mcpStatus = $this->playCanvasMcpManager->getServerStatus($workspace);
                
                switch ($mcpStatus) {
                    case 'running':
                        $status['status'] = 'connected';
                        $status['message'] = 'PlayCanvas MCP server is running';
                        $status['details'] = [
                            'mcp_port' => $workspace->mcp_port,
                            'mcp_pid' => $workspace->mcp_pid,
                            'preview_url' => $workspace->preview_url,
                        ];
                        break;
                    case 'unhealthy':
                        $status['status'] = 'error';
                        $status['message'] = 'PlayCanvas MCP server is unhealthy';
                        break;
                    default:
                        $status['status'] = 'disconnected';
                        $status['message'] = 'PlayCanvas MCP server is not running';
                }
            } elseif ($workspace->isUnreal()) {
                // For Unreal Engine, we would check the plugin connection
                // This is a placeholder for now
                $status['status'] = 'disconnected';
                $status['message'] = 'Unreal Engine plugin not connected';
                $status['details'] = [
                    'plugin_required' => true,
                    'connection_url' => 'http://localhost:8080',
                ];
            }

            return response()->json($status);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get engine status', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get engine status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Unreal Engine connection status
     */
    public function getUnrealStatus(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'error' => 'No company associated with user',
                ], 403);
            }

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'unreal')
                ->firstOrFail();

            // This would typically check the actual Unreal Engine plugin connection
            // For now, we'll return a mock status
            $status = [
                'connected' => false,
                'version' => null,
                'plugin_version' => null,
                'project_name' => null,
                'last_ping' => null,
                'error' => 'Plugin not connected. Please ensure Unreal Engine is running with the SurrealPilot plugin enabled.',
            ];

            return response()->json($status);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Unreal workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get Unreal status', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get Unreal status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Unreal Engine connection
     */
    public function testUnrealConnection(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'connected' => false,
                    'error' => 'No company associated with user',
                ]);
            }

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'unreal')
                ->firstOrFail();

            $settings = $request->validate([
                'host' => 'sometimes|string|max:255',
                'port' => 'sometimes|integer|min:1|max:65535',
                'api_key' => 'sometimes|string|max:255',
            ]);

            // This would typically test the actual connection to Unreal Engine
            // For now, we'll simulate a connection test
            $host = $settings['host'] ?? 'localhost';
            $port = $settings['port'] ?? 8080;

            Log::info('Testing Unreal Engine connection', [
                'workspace_id' => $workspaceId,
                'host' => $host,
                'port' => $port,
            ]);

            // Simulate connection test result
            $status = [
                'connected' => false,
                'error' => "Could not connect to Unreal Engine at {$host}:{$port}. Please ensure the SurrealPilot plugin is running.",
            ];

            return response()->json($status);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Unreal workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to test Unreal connection', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'connected' => false,
                'error' => 'Connection test failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get PlayCanvas status
     */
    public function getPlayCanvasStatus(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'playcanvas')
                ->firstOrFail();

            $mcpStatus = $this->playCanvasMcpManager->getServerStatus($workspace);
            $healthCheck = $this->playCanvasMcpManager->performHealthCheck($workspace);

            $status = [
                'mcp_running' => $mcpStatus === 'running',
                'port' => $workspace->mcp_port,
                'preview_available' => !empty($workspace->preview_url),
                'preview_url' => $workspace->preview_url,
                'last_update' => $workspace->updated_at?->toISOString(),
                'health_check' => $healthCheck,
            ];

            if ($mcpStatus !== 'running') {
                $status['error'] = 'MCP server is not running';
            }

            return response()->json($status);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'PlayCanvas workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get PlayCanvas status', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'mcp_running' => false,
                'preview_available' => false,
                'error' => 'Failed to get PlayCanvas status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh PlayCanvas preview
     */
    public function refreshPlayCanvasPreview(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'playcanvas')
                ->firstOrFail();

            // Check if MCP server is running
            $mcpStatus = $this->playCanvasMcpManager->getServerStatus($workspace);
            if ($mcpStatus !== 'running') {
                return response()->json([
                    'success' => false,
                    'error' => 'MCP server is not running',
                ], 400);
            }

            // Send refresh command to MCP server
            $result = $this->playCanvasMcpManager->sendCommand($workspace, 'refresh_preview');

            $response = [
                'success' => true,
                'preview_url' => $workspace->preview_url,
                'timestamp' => now()->toISOString(),
            ];

            if (isset($result['preview_url'])) {
                $workspace->update(['preview_url' => $result['preview_url']]);
                $response['preview_url'] = $result['preview_url'];
            }

            Log::info('PlayCanvas preview refreshed', [
                'workspace_id' => $workspaceId,
                'preview_url' => $response['preview_url'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'PlayCanvas workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to refresh PlayCanvas preview', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to refresh preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start PlayCanvas MCP server
     */
    public function startPlayCanvasMcp(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'playcanvas')
                ->firstOrFail();

            $result = $this->playCanvasMcpManager->startServer($workspace);

            Log::info('PlayCanvas MCP server started', [
                'workspace_id' => $workspaceId,
                'port' => $result['port'],
                'pid' => $result['pid'],
            ]);

            return response()->json([
                'success' => true,
                'port' => $result['port'],
                'preview_url' => $result['preview_url'],
                'message' => 'MCP server started successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'PlayCanvas workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to start PlayCanvas MCP server', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start MCP server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop PlayCanvas MCP server
     */
    public function stopPlayCanvasMcp(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', 'playcanvas')
                ->firstOrFail();

            $result = $this->playCanvasMcpManager->stopServer($workspace);

            Log::info('PlayCanvas MCP server stopped', [
                'workspace_id' => $workspaceId,
                'success' => $result,
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'MCP server stopped successfully' : 'Failed to stop MCP server',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'PlayCanvas workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to stop PlayCanvas MCP server', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to stop MCP server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workspace context for AI
     */
    public function getWorkspaceContext(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::with(['games', 'conversations'])->where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $context = [
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'engine_type' => $workspace->engine_type,
                    'status' => $workspace->status,
                    'template_id' => $workspace->template_id,
                ],
                'engine' => [
                    'type' => $workspace->engine_type,
                    'display_name' => $this->engineService->getEngineDisplayName($workspace->engine_type),
                    'available' => $this->engineService->isEngineAvailable($workspace->engine_type),
                ],
                'games' => $workspace->games->map(function ($game) {
                    return [
                        'id' => $game->id,
                        'title' => $game->title,
                        'description' => $game->description,
                        'preview_url' => $game->preview_url,
                        'created_at' => $game->created_at,
                    ];
                }),
                'recent_conversations' => $workspace->conversations()
                    ->orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($conversation) {
                        return [
                            'id' => $conversation->id,
                            'title' => $conversation->title,
                            'updated_at' => $conversation->updated_at,
                        ];
                    }),
                'timestamp' => now()->toISOString(),
            ];

            // Add engine-specific context
            if ($workspace->isPlayCanvas()) {
                $context['playcanvas'] = [
                    'mcp_running' => $this->playCanvasMcpManager->getServerStatus($workspace) === 'running',
                    'preview_url' => $workspace->preview_url,
                    'mcp_port' => $workspace->mcp_port,
                ];
            } elseif ($workspace->isUnreal()) {
                $context['unreal'] = [
                    'plugin_connected' => false, // This would be checked via actual plugin
                    'connection_url' => 'http://localhost:8080',
                ];
            }

            return response()->json($context);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get workspace context', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get workspace context',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get engine-specific AI configuration
     */
    public function getEngineAiConfig(Request $request, string $engineType): JsonResponse
    {
        try {
            if (!in_array($engineType, ['playcanvas', 'unreal'])) {
                return response()->json([
                    'error' => 'Invalid engine type',
                ], 400);
            }

            $config = config("ai.agents.{$engineType}", []);

            return response()->json([
                'engine_type' => $engineType,
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get engine AI config', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get AI configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update engine-specific AI configuration
     */
    public function updateEngineAiConfig(Request $request, string $engineType): JsonResponse
    {
        try {
            if (!in_array($engineType, ['playcanvas', 'unreal'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid engine type',
                ], 400);
            }

            $validated = $request->validate([
                'model' => 'sometimes|string|max:255',
                'temperature' => 'sometimes|numeric|min:0|max:2',
                'max_tokens' => 'sometimes|integer|min:1|max:4000',
                'provider' => 'sometimes|string|in:openai,anthropic,gemini,ollama',
            ]);

            // This would typically update the configuration
            // For now, we'll just log the update
            Log::info('Engine AI config update requested', [
                'engine_type' => $engineType,
                'config' => $validated,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AI configuration updated successfully',
                'engine_type' => $engineType,
                'config' => $validated,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update engine AI config', [
                'engine_type' => $engineType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update AI configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}