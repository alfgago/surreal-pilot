<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GDevelop\GDevelopCliException;
use App\Exceptions\GDevelop\GameJsonValidationException;
use App\Exceptions\GDevelop\GDevelopPreviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GDevelopChatRequest;
use App\Services\GDevelopGameService;
use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopSessionManager;
use App\Services\GDevelopPreviewService;
use App\Services\GDevelopErrorRecoveryService;
use App\Services\CreditManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class GDevelopChatController extends Controller
{
    public function __construct(
        private GDevelopGameService $gameService,
        private GDevelopRuntimeService $runtimeService,
        private GDevelopSessionManager $sessionManager,
        private GDevelopPreviewService $previewService,
        private GDevelopErrorRecoveryService $errorRecovery,
        private CreditManager $creditManager
    ) {
        // Middleware is applied in routes
    }

    /**
     * Process chat request and generate/modify GDevelop game
     */
    public function chat(GDevelopChatRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $sessionId = $validated['session_id'] ?? Str::uuid()->toString();
            $userRequest = $validated['message'];
            $workspaceId = $validated['workspace_id'] ?? null;

            $user = auth()->user();
            $company = $user->currentCompany;

            // Estimate credit cost for GDevelop operation
            $estimatedCost = $this->estimateGDevelopCost($userRequest);

            // Check if company has sufficient credits
            if (!$this->creditManager->canAffordRequest($company, $estimatedCost)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient credits for this operation',
                    'credits_required' => $estimatedCost,
                    'credits_available' => $company->credits
                ], 402);
            }

            Log::info('Processing GDevelop chat request', [
                'session_id' => $sessionId,
                'workspace_id' => $workspaceId,
                'message_length' => strlen($userRequest),
                'estimated_cost' => $estimatedCost,
                'user_id' => $user->id,
                'company_id' => $company->id
            ]);

            // Check if this is a new game creation or modification
            $existingGame = $this->gameService->getGameData($sessionId);
            
            if ($existingGame && !empty($existingGame['game_json'])) {
                // Modify existing game
                $gameData = $this->gameService->modifyGame(
                    $sessionId,
                    $userRequest,
                    $validated['options'] ?? []
                );
            } else {
                // Create new game - ensure session exists with workspace and user info
                $session = $this->sessionManager->getOrCreateSession(
                    $sessionId, 
                    $workspaceId, 
                    auth()->id()
                );
                
                $template = null;
                if (isset($validated['template'])) {
                    $template = $validated['template'];
                }
                
                $gameData = $this->gameService->createGame(
                    $sessionId,
                    $userRequest,
                    $template,
                    $validated['options'] ?? []
                );
            }

            // Deduct credits for successful operation
            $actualCost = $this->calculateActualGDevelopCost($userRequest, $gameData);
            $this->creditManager->deductCreditsWithMcpSurcharge(
                $company,
                $actualCost,
                'gdevelop',
                'GDevelop Game Generation',
                [
                    'session_id' => $sessionId,
                    'workspace_id' => $workspaceId,
                    'operation_type' => $existingGame ? 'modify' : 'create',
                    'message_length' => strlen($userRequest),
                    'user_id' => $user->id
                ]
            );

            // Generate preview URL
            $previewUrl = $this->generatePreviewUrl($sessionId);

            $response = [
                'success' => true,
                'session_id' => $sessionId,
                'game_data' => $gameData,
                'preview_url' => $previewUrl,
                'message' => $existingGame ? 'Game modified successfully' : 'Game created successfully',
                'credits_used' => $actualCost + $this->creditManager->calculateMcpSurcharge('gdevelop'),
                'credits_remaining' => $company->fresh()->credits,
                'actions' => [
                    'preview' => [
                        'available' => true,
                        'url' => $previewUrl
                    ],
                    'export' => [
                        'available' => true,
                        'url' => route('api.gdevelop.export', ['sessionId' => $sessionId])
                    ]
                ]
            ];

            Log::info('GDevelop chat request processed successfully', [
                'session_id' => $sessionId,
                'game_name' => $gameData['game_json']['properties']['name'] ?? 'Unknown',
                'credits_used' => $actualCost + $this->creditManager->calculateMcpSurcharge('gdevelop'),
                'credits_remaining' => $company->fresh()->credits
            ]);

            return response()->json($response);

        } catch (GDevelopCliException $e) {
            $errorInfo = $this->errorRecovery->handleCliError($e, $sessionId);
            
            return response()->json([
                'success' => false,
                'error_type' => 'cli_error',
                'error' => $errorInfo['user_message'],
                'debug_info' => $errorInfo['debug_info'],
                'suggested_action' => $errorInfo['suggested_action'],
                'is_retryable' => $errorInfo['is_retryable'],
                'session_id' => $sessionId ?? null,
                'system_health' => $this->errorRecovery->getSystemHealthStatus()
            ], 422);
        } catch (GameJsonValidationException $e) {
            $errorInfo = $this->errorRecovery->handleValidationError($e, $sessionId);
            
            return response()->json([
                'success' => false,
                'error_type' => 'validation_error',
                'error' => $errorInfo['user_message'],
                'validation_errors' => $errorInfo['validation_errors'],
                'critical_error' => $errorInfo['critical_error'],
                'is_recoverable' => $errorInfo['is_recoverable'],
                'session_id' => $sessionId ?? null,
                'fallback_suggestions' => $this->errorRecovery->shouldSuggestFallback($sessionId, 'validation_error') 
                    ? $this->errorRecovery->getFallbackSuggestions($sessionId, 'validation_error') 
                    : []
            ], 422);
        } catch (GDevelopPreviewException $e) {
            $errorInfo = $this->errorRecovery->handlePreviewError($e, $sessionId);
            
            return response()->json([
                'success' => false,
                'error_type' => 'preview_error',
                'error' => $errorInfo['user_message'],
                'debug_info' => $errorInfo['debug_info'],
                'suggested_action' => $errorInfo['suggested_action'],
                'is_retryable' => $errorInfo['is_retryable'],
                'session_id' => $sessionId ?? null
            ], 422);
        } catch (Exception $e) {
            Log::error('GDevelop chat request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $sessionId ?? null
            ]);

            return response()->json([
                'success' => false,
                'error_type' => 'system_error',
                'error' => 'An unexpected error occurred. Please try again or contact support.',
                'debug_info' => [
                    'error_message' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ],
                'session_id' => $sessionId ?? null,
                'system_health' => $this->errorRecovery->getSystemHealthStatus()
            ], 500);
        }
    }

    /**
     * Generate HTML5 preview for GDevelop game
     */
    public function preview(string $sessionId): JsonResponse
    {
        try {
            Log::info('Generating GDevelop preview', [
                'session_id' => $sessionId
            ]);

            // Get game data
            $gameData = $this->gameService->getGameData($sessionId);
            
            if (!$gameData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Game session not found'
                ], 404);
            }

            if (empty($gameData['game_json'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No game data found for session'
                ], 400);
            }

            // Generate preview using the dedicated preview service
            $previewResult = $this->previewService->generatePreview($sessionId, $gameData['game_json']);

            if (!$previewResult->success) {
                Log::error('Preview generation failed', [
                    'session_id' => $sessionId,
                    'error' => $previewResult->error
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Preview generation failed: ' . $previewResult->error
                ], 500);
            }

            $response = [
                'success' => true,
                'session_id' => $sessionId,
                'preview_url' => $previewResult->previewUrl,
                'preview_path' => $previewResult->previewPath,
                'index_path' => $previewResult->indexPath,
                'build_time' => $previewResult->buildTime,
                'cached' => $previewResult->cached,
                'message' => 'Preview generated successfully'
            ];

            Log::info('GDevelop preview generated successfully', [
                'session_id' => $sessionId,
                'preview_url' => $previewResult->previewUrl
            ]);

            return response()->json($response);

        } catch (Exception $e) {
            Log::error('GDevelop preview generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Preview generation failed: ' . $e->getMessage(),
                'session_id' => $sessionId
            ], 500);
        }
    }



    /**
     * Get game session information
     */
    public function getSession(string $sessionId): JsonResponse
    {
        try {
            $gameData = $this->gameService->getGameData($sessionId);
            
            if (!$gameData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Game session not found'
                ], 404);
            }

            $response = [
                'success' => true,
                'session_id' => $sessionId,
                'game_data' => $gameData,
                'preview_url' => $this->generatePreviewUrl($sessionId),
                'actions' => [
                    'preview' => [
                        'available' => !empty($gameData['game_json']),
                        'url' => $this->generatePreviewUrl($sessionId)
                    ],
                    'export' => [
                        'available' => !empty($gameData['game_json']),
                        'url' => route('api.gdevelop.export', ['sessionId' => $sessionId])
                    ]
                ]
            ];

            return response()->json($response);

        } catch (Exception $e) {
            Log::error('Failed to get GDevelop session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get session: ' . $e->getMessage(),
                'session_id' => $sessionId
            ], 500);
        }
    }

    /**
     * Delete game session
     */
    public function deleteSession(string $sessionId): JsonResponse
    {
        try {
            Log::info('Deleting GDevelop session', [
                'session_id' => $sessionId
            ]);

            $deleted = $this->sessionManager->deleteSession($sessionId);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'error' => 'Game session not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Game session deleted successfully',
                'session_id' => $sessionId
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete GDevelop session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete session: ' . $e->getMessage(),
                'session_id' => $sessionId
            ], 500);
        }
    }

    /**
     * Generate preview URL for a session
     */
    private function generatePreviewUrl(string $sessionId): string
    {
        try {
            return route('gdevelop.preview.serve', ['sessionId' => $sessionId]);
        } catch (Exception $e) {
            // Fallback for testing or when routes are not defined
            return "/gdevelop/preview/{$sessionId}/serve";
        }
    }

    /**
     * Save game JSON to temporary file for preview generation
     */
    private function saveGameJsonForPreview(string $sessionId, array $gameJson): string
    {
        $sessionsPath = storage_path('gdevelop/sessions');
        $sessionPath = $sessionsPath . DIRECTORY_SEPARATOR . $sessionId;
        
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0755, true);
        }

        $gameJsonPath = $sessionPath . DIRECTORY_SEPARATOR . 'game.json';
        file_put_contents($gameJsonPath, json_encode($gameJson, JSON_PRETTY_PRINT));

        return $gameJsonPath;
    }

    /**
     * Save game JSON to temporary file for export generation
     */
    private function saveGameJsonForExport(string $sessionId, array $gameJson): string
    {
        $exportsPath = storage_path('gdevelop/exports');
        $sessionPath = $exportsPath . DIRECTORY_SEPARATOR . $sessionId;
        
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0755, true);
        }

        $gameJsonPath = $sessionPath . DIRECTORY_SEPARATOR . 'game.json';
        file_put_contents($gameJsonPath, json_encode($gameJson, JSON_PRETTY_PRINT));

        return $gameJsonPath;
    }

    /**
     * Estimate credit cost for GDevelop operation
     */
    private function estimateGDevelopCost(string $userRequest): int
    {
        // Base cost for GDevelop operations
        $baseCost = 5;
        
        // Additional cost based on request complexity
        $messageLength = strlen($userRequest);
        $complexityCost = min(10, floor($messageLength / 100)); // 1 credit per 100 characters, max 10
        
        // Check for complex operations
        $complexOperations = ['tower defense', 'platformer', 'rpg', 'multiplayer', 'physics'];
        $complexityBonus = 0;
        
        foreach ($complexOperations as $operation) {
            if (stripos($userRequest, $operation) !== false) {
                $complexityBonus += 2;
            }
        }
        
        return $baseCost + $complexityCost + $complexityBonus;
    }

    /**
     * Calculate actual credit cost based on operation result
     */
    private function calculateActualGDevelopCost(string $userRequest, array $gameData): int
    {
        // Start with estimated cost
        $cost = $this->estimateGDevelopCost($userRequest);
        
        // Adjust based on actual game complexity
        $gameJson = $gameData['game_json'] ?? [];
        
        if (isset($gameJson['layouts'])) {
            $layoutCount = count($gameJson['layouts']);
            $cost += min(5, $layoutCount); // 1 credit per layout, max 5
        }
        
        if (isset($gameJson['objects'])) {
            $objectCount = count($gameJson['objects']);
            $cost += min(10, floor($objectCount / 5)); // 1 credit per 5 objects, max 10
        }
        
        return max(1, $cost); // Minimum 1 credit
    }
}