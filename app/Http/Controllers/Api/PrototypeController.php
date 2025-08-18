<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\Workspace;
use App\Services\PublishService;
use App\Services\TemplateRegistry;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class PrototypeController extends Controller
{
    private TemplateRegistry $templateRegistry;
    private WorkspaceService $workspaceService;
    private PublishService $publishService;

    public function __construct(
        TemplateRegistry $templateRegistry,
        WorkspaceService $workspaceService,
        PublishService $publishService
    ) {
        $this->templateRegistry = $templateRegistry;
        $this->workspaceService = $workspaceService;
        $this->publishService = $publishService;
    }

    /**
     * Get available PlayCanvas demo templates.
     *
     * @return JsonResponse
     */
    public function getDemos(): JsonResponse
    {
        try {
            // Validate that this endpoint is only used for PlayCanvas templates
            $this->validateEngineTypeSupport('playcanvas');
            
            $templates = $this->templateRegistry->getPlayCanvasTemplates();

            $formattedTemplates = $templates->map(function (DemoTemplate $template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'preview_image' => $template->getPreviewImageUrl(),
                    'tags' => $template->tags ?? [],
                    'difficulty_level' => $template->difficulty_level,
                    'estimated_setup_time' => $template->estimated_setup_time,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $formattedTemplates,
                    'total_count' => $templates->count(),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch demo templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch demo templates',
                'message' => 'An error occurred while retrieving available templates.'
            ], 500);
        }
    }

    /**
     * Create a new prototype from a demo template.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPrototype(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Validate that this endpoint only supports PlayCanvas
            $this->validateEngineTypeSupport('playcanvas');
            
            // Validate request
            $validator = Validator::make($request->all(), [
                'demo_id' => 'required|string|exists:demo_templates,id',
                'company_id' => 'required|integer|exists:companies,id',
                'name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $demoId = $request->input('demo_id');
            $companyId = $request->input('company_id');
            $name = $request->input('name');

            // Get company
            $company = Company::findOrFail($companyId);

            // Validate template is PlayCanvas and active with enhanced validation
            $template = DemoTemplate::where('id', $demoId)
                ->where('engine_type', 'playcanvas')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid template',
                    'message' => 'The specified template is not available or not a PlayCanvas template.',
                    'engine_validation' => 'This endpoint only supports PlayCanvas templates'
                ], 404);
            }

            // Additional engine type validation
            $this->validateTemplateEngineType($template, 'playcanvas');



            // Create workspace with timeout handling
            $workspace = DB::transaction(function () use ($company, $demoId, $name) {
                return $this->workspaceService->createFromTemplate(
                    $company,
                    $demoId,
                    'playcanvas',
                    $name
                );
            }, 3); // 3 attempts

            // Check if creation took too long (15 second requirement)
            $elapsedTime = microtime(true) - $startTime;
            if ($elapsedTime > 15) {
                Log::warning('Prototype creation exceeded 15-second timeout', [
                    'workspace_id' => $workspace->id,
                    'elapsed_time' => $elapsedTime,
                    'demo_id' => $demoId,
                    'company_id' => $companyId
                ]);
            }

            Log::info('Prototype created successfully', [
                'workspace_id' => $workspace->id,
                'demo_id' => $demoId,
                'company_id' => $companyId,
                'elapsed_time' => $elapsedTime
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'workspace_id' => $workspace->id,
                    'preview_url' => $workspace->getPreviewUrl(),
                    'name' => $workspace->name,
                    'status' => $workspace->status,
                    'engine_type' => $workspace->engine_type,
                    'engine_display_name' => $this->getEngineDisplayName($workspace->engine_type),
                    'engine_compatibility' => [
                        'isolated' => true,
                        'cross_engine_commands' => false,
                        'supported_operations' => $this->getSupportedOperations($workspace->engine_type)
                    ],
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'engine_type' => $template->engine_type,
                    ],
                    'creation_time' => round($elapsedTime, 2)
                ]
            ], 201);

        } catch (Exception $e) {
            $elapsedTime = microtime(true) - $startTime;

            Log::error('Failed to create prototype', [
                'demo_id' => $request->input('demo_id'),
                'company_id' => $request->input('company_id'),
                'elapsed_time' => $elapsedTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return appropriate error response based on exception type
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'error' => 'Resource not found',
                    'message' => 'The specified company or template was not found.'
                ], 404);
            }

            if (str_contains($e->getMessage(), 'timeout') || $elapsedTime > 15) {
                return response()->json([
                    'success' => false,
                    'error' => 'Creation timeout',
                    'message' => 'Prototype creation took too long. Please try again.',
                    'elapsed_time' => round($elapsedTime, 2)
                ], 408);
            }

            return response()->json([
                'success' => false,
                'error' => 'Creation failed',
                'message' => 'An error occurred while creating the prototype. Please try again.',
                'details' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get workspace status for polling.
     *
     * @param int $workspaceId
     * @return JsonResponse
     */
    public function getWorkspaceStatus(int $workspaceId): JsonResponse
    {
        try {
            $workspace = Workspace::findOrFail($workspaceId);

            // Get additional status information for PlayCanvas workspaces
            $statusData = [
                'workspace_id' => $workspace->id,
                'name' => $workspace->name,
                'status' => $workspace->status,
                'engine_type' => $workspace->engine_type,
                'engine_display_name' => $this->getEngineDisplayName($workspace->engine_type),
                'engine_compatibility' => [
                    'isolated' => true,
                    'cross_engine_commands' => false,
                    'supported_operations' => $this->getSupportedOperations($workspace->engine_type)
                ],
                'preview_url' => $workspace->getPreviewUrl(),
                'published_url' => $workspace->published_url,
                'created_at' => $workspace->created_at->toISOString(),
                'updated_at' => $workspace->updated_at->toISOString(),
            ];

            // Add PlayCanvas-specific status information
            if ($workspace->isPlayCanvas()) {
                $statusData['mcp_server'] = [
                    'port' => $workspace->mcp_port,
                    'running' => $workspace->mcp_pid !== null,
                    'server_url' => $workspace->getMcpServerUrl(),
                ];

                // Add health check information if server is running
                if ($workspace->mcp_pid) {
                    try {
                        $mcpManager = app(\App\Services\PlayCanvasMcpManager::class);
                        $serverStatus = $mcpManager->getServerStatus($workspace);
                        $statusData['mcp_server']['health_status'] = $serverStatus;
                    } catch (Exception $e) {
                        $statusData['mcp_server']['health_status'] = 'unknown';
                        $statusData['mcp_server']['health_error'] = $e->getMessage();
                    }
                }
            }

            // Add metadata if available
            if ($workspace->metadata) {
                $statusData['metadata'] = $workspace->metadata;
            }

            return response()->json([
                'success' => true,
                'data' => $statusData
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Workspace not found',
                'message' => 'The specified workspace does not exist.'
            ], 404);

        } catch (Exception $e) {
            Log::error('Failed to get workspace status', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Status check failed',
                'message' => 'An error occurred while checking workspace status.'
            ], 500);
        }
    }

    /**
     * Get workspace statistics for a company.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWorkspaceStats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $company = Company::findOrFail($request->input('company_id'));
            $stats = $this->workspaceService->getWorkspaceStats($company);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get workspace statistics', [
                'company_id' => $request->input('company_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Stats retrieval failed',
                'message' => 'An error occurred while retrieving workspace statistics.'
            ], 500);
        }
    }

    /**
     * List workspaces for a company with filtering options.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listWorkspaces(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|integer|exists:companies,id',
                'engine_type' => 'nullable|string|in:playcanvas,unreal',
                'status' => 'nullable|string|in:initializing,ready,building,published,error',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $company = Company::findOrFail($request->input('company_id'));
            $engineType = $request->input('engine_type');
            $status = $request->input('status');
            $limit = $request->input('limit', 20);
            $offset = $request->input('offset', 0);

            $query = $company->workspaces();

            if ($engineType) {
                $query->byEngine($engineType);
            }

            if ($status) {
                $query->byStatus($status);
            }

            $total = $query->count();
            $workspaces = $query->orderBy('created_at', 'desc')
                               ->skip($offset)
                               ->take($limit)
                               ->get();

            $formattedWorkspaces = $workspaces->map(function (Workspace $workspace) {
                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'engine_type' => $workspace->engine_type,
                    'engine_display_name' => $this->getEngineDisplayName($workspace->engine_type),
                    'engine_compatibility' => [
                        'isolated' => true,
                        'cross_engine_commands' => false,
                        'supported_operations' => $this->getSupportedOperations($workspace->engine_type)
                    ],
                    'status' => $workspace->status,
                    'template_id' => $workspace->template_id,
                    'preview_url' => $workspace->getPreviewUrl(),
                    'published_url' => $workspace->published_url,
                    'created_at' => $workspace->created_at->toISOString(),
                    'updated_at' => $workspace->updated_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'workspaces' => $formattedWorkspaces,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total,
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to list workspaces', [
                'company_id' => $request->input('company_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Listing failed',
                'message' => 'An error occurred while retrieving workspaces.'
            ], 500);
        }
    }

    /**
     * Publish workspace to PlayCanvas cloud.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function publishToPlayCanvasCloud(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $validator = Validator::make($request->all(), [
                'workspace_id' => 'required|integer|exists:workspaces,id',
                'playcanvas_api_key' => 'required|string',
                'playcanvas_project_id' => 'required|string',
                'save_credentials' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $workspace = Workspace::findOrFail($request->input('workspace_id'));

            // Validate engine type support and workspace compatibility
            $this->validateEngineTypeSupport('playcanvas');
            $this->validateWorkspaceEngineType($workspace, 'playcanvas');

            // Validate workspace is PlayCanvas (redundant check for extra safety)
            if (!$workspace->isPlayCanvas()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid workspace type',
                    'message' => 'Only PlayCanvas workspaces can be published to PlayCanvas cloud.',
                    'engine_validation' => 'Cross-engine operations are not permitted'
                ], 400);
            }

            // Validate workspace is ready for publishing
            if (!in_array($workspace->status, ['ready', 'published'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Workspace not ready',
                    'message' => 'Workspace must be in ready or published status to be published.',
                    'current_status' => $workspace->status
                ], 400);
            }

            $credentials = [
                'api_key' => $request->input('playcanvas_api_key'),
                'project_id' => $request->input('playcanvas_project_id'),
            ];

            // Save credentials to company if requested
            if ($request->input('save_credentials', false)) {
                $workspace->company->update([
                    'playcanvas_api_key' => $credentials['api_key'],
                    'playcanvas_project_id' => $credentials['project_id'],
                ]);
            }

            Log::info('Starting PlayCanvas cloud publish', [
                'workspace_id' => $workspace->id,
                'company_id' => $workspace->company_id,
                'project_id' => $credentials['project_id'],
                'current_status' => $workspace->status
            ]);

            // Publish to PlayCanvas cloud
            $launchUrl = $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);

            $elapsedTime = microtime(true) - $startTime;

            Log::info('Workspace published to PlayCanvas cloud successfully', [
                'workspace_id' => $workspace->id,
                'launch_url' => $launchUrl,
                'elapsed_time' => $elapsedTime
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'workspace_id' => $workspace->id,
                    'launch_url' => $launchUrl,
                    'status' => 'published',
                    'publish_time' => round($elapsedTime, 2),
                    'platform' => 'playcanvas_cloud',
                    'credentials_saved' => $request->input('save_credentials', false)
                ]
            ]);

        } catch (Exception $e) {
            $elapsedTime = microtime(true) - $startTime;

            Log::error('Failed to publish workspace to PlayCanvas cloud', [
                'workspace_id' => $request->input('workspace_id'),
                'elapsed_time' => $elapsedTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return appropriate error response
            if (str_contains($e->getMessage(), 'PlayCanvas API error')) {
                return response()->json([
                    'success' => false,
                    'error' => 'PlayCanvas API error',
                    'message' => 'Failed to publish to PlayCanvas cloud. Please check your API credentials.',
                    'details' => app()->environment('local') ? $e->getMessage() : null
                ], 422);
            }

            if (str_contains($e->getMessage(), 'Build process failed')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Build failed',
                    'message' => 'The project build process failed. Please check your project configuration.',
                    'details' => app()->environment('local') ? $e->getMessage() : null
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error' => 'Publish failed',
                'message' => 'An error occurred while publishing to PlayCanvas cloud. Please try again.',
                'details' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Validate that the endpoint supports the specified engine type.
     *
     * @param string $requiredEngineType
     * @throws Exception
     */
    private function validateEngineTypeSupport(string $requiredEngineType): void
    {
        $supportedEngines = ['playcanvas']; // This controller only supports PlayCanvas
        
        if (!in_array($requiredEngineType, $supportedEngines)) {
            throw new Exception("Engine type '{$requiredEngineType}' is not supported by this endpoint. Supported engines: " . implode(', ', $supportedEngines));
        }
    }

    /**
     * Validate that a template matches the required engine type.
     *
     * @param DemoTemplate $template
     * @param string $requiredEngineType
     * @throws Exception
     */
    private function validateTemplateEngineType(DemoTemplate $template, string $requiredEngineType): void
    {
        if ($template->engine_type !== $requiredEngineType) {
            throw new Exception("Template engine type mismatch. Expected '{$requiredEngineType}', got '{$template->engine_type}'");
        }
    }

    /**
     * Validate that a workspace matches the required engine type.
     *
     * @param Workspace $workspace
     * @param string $requiredEngineType
     * @throws Exception
     */
    private function validateWorkspaceEngineType(Workspace $workspace, string $requiredEngineType): void
    {
        if ($workspace->engine_type !== $requiredEngineType) {
            throw new Exception("Workspace engine type mismatch. Expected '{$requiredEngineType}', got '{$workspace->engine_type}'. This operation is not allowed for cross-engine compatibility.");
        }
    }

    /**
     * Get human-readable engine display name.
     *
     * @param string $engineType
     * @return string
     */
    private function getEngineDisplayName(string $engineType): string
    {
        return match($engineType) {
            'playcanvas' => 'PlayCanvas',
            'unreal' => 'Unreal Engine',
            default => ucfirst($engineType)
        };
    }

    /**
     * Get supported operations for an engine type.
     *
     * @param string $engineType
     * @return array
     */
    private function getSupportedOperations(string $engineType): array
    {
        return match($engineType) {
            'playcanvas' => [
                'scene_manipulation',
                'entity_management',
                'component_systems',
                'script_editing',
                'asset_management',
                'mobile_optimization',
                'static_publishing',
                'cloud_publishing',
                'multiplayer_testing'
            ],
            'unreal' => [
                'blueprint_editing',
                'cpp_development',
                'actor_management',
                'component_systems',
                'build_management',
                'packaging',
                'debugging'
            ],
            default => []
        };
    }

    /**
     * Publish workspace to static hosting.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function publishWorkspace(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $validator = Validator::make($request->all(), [
                'workspace_id' => 'required|integer|exists:workspaces,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $workspace = Workspace::findOrFail($request->input('workspace_id'));

            // Validate engine type support and workspace compatibility
            $this->validateEngineTypeSupport('playcanvas');
            $this->validateWorkspaceEngineType($workspace, 'playcanvas');

            // Validate workspace is PlayCanvas (redundant check for extra safety)
            if (!$workspace->isPlayCanvas()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid workspace type',
                    'message' => 'Only PlayCanvas workspaces can be published using this endpoint.',
                    'engine_validation' => 'Cross-engine operations are not permitted'
                ], 400);
            }

            // Validate workspace is ready for publishing
            if (!in_array($workspace->status, ['ready', 'published'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Workspace not ready',
                    'message' => 'Workspace must be in ready or published status to be published.',
                    'current_status' => $workspace->status
                ], 400);
            }

            Log::info('Starting workspace publish', [
                'workspace_id' => $workspace->id,
                'company_id' => $workspace->company_id,
                'current_status' => $workspace->status
            ]);

            // Publish the workspace
            $publishedUrl = $this->publishService->publishToStatic($workspace);

            $elapsedTime = microtime(true) - $startTime;

            Log::info('Workspace published successfully', [
                'workspace_id' => $workspace->id,
                'published_url' => $publishedUrl,
                'elapsed_time' => $elapsedTime
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'workspace_id' => $workspace->id,
                    'published_url' => $publishedUrl,
                    'status' => 'published',
                    'publish_time' => round($elapsedTime, 2),
                    'mobile_optimized' => true,
                    'compression_enabled' => config('services.publishing.compression', true)
                ]
            ]);

        } catch (Exception $e) {
            $elapsedTime = microtime(true) - $startTime;

            Log::error('Failed to publish workspace', [
                'workspace_id' => $request->input('workspace_id'),
                'elapsed_time' => $elapsedTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return appropriate error response
            if (str_contains($e->getMessage(), 'Build process failed')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Build failed',
                    'message' => 'The project build process failed. Please check your project configuration.',
                    'details' => app()->environment('local') ? $e->getMessage() : null
                ], 422);
            }

            if (str_contains($e->getMessage(), 'package.json not found')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid project structure',
                    'message' => 'The workspace does not contain a valid PlayCanvas project structure.',
                    'details' => app()->environment('local') ? $e->getMessage() : null
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error' => 'Publish failed',
                'message' => 'An error occurred while publishing the workspace. Please try again.',
                'details' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}