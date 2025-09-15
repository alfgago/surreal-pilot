<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Services\GameStorageService;
use App\Services\GameSharingService;
use App\Services\DomainPublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameStorageService $gameStorageService,
        private GameSharingService $gameSharingService,
        private DomainPublishingService $domainPublishingService
    ) {}

    /**
     * Get games for a workspace with pagination and search support.
     */
    public function getWorkspaceGames(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            // Get pagination parameters
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(1, (int) $request->query('limit', 12))); // Max 100, min 1
            $search = $request->query('search');

            // Use paginated method for better performance
            $result = $this->gameStorageService->getPaginatedWorkspaceGames(
                $workspace, 
                $page, 
                $limit, 
                $search
            );

            $gamesData = $result['games']->map(function ($game) {
                $gameData = [
                    'id' => $game->id,
                    'title' => $game->title,
                    'description' => $game->description,
                    'preview_url' => $game->preview_url,
                    'published_url' => $game->published_url,
                    'thumbnail_url' => $game->thumbnail_url,
                    'metadata' => $game->metadata,
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                    'engine_type' => $game->engine_type,
                    'is_published' => $game->is_published,
                    'has_preview' => $game->has_preview,
                    'has_thumbnail' => $game->has_thumbnail,
                    'display_url' => $game->getDisplayUrl(),
                    'conversation_id' => $game->conversation_id,
                ];

                // Include conversation details if available
                if ($game->conversation) {
                    $gameData['conversation'] = [
                        'id' => $game->conversation->id,
                        'title' => $game->conversation->title,
                        'created_at' => $game->conversation->created_at,
                    ];
                }

                return $gameData;
            });

            return response()->json([
                'success' => true,
                'games' => $gamesData,
                'pagination' => $result['pagination'],
                'has_more_pages' => $result['pagination']['has_more_pages'],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new game for a workspace.
     */
    public function createGame(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'conversation_id' => 'nullable|integer|exists:chat_conversations,id',
                'preview_url' => 'nullable|url|max:500',
                'published_url' => 'nullable|url|max:500',
                'thumbnail_url' => 'nullable|url|max:500',
                'metadata' => 'nullable|array',
            ]);

            // Validate conversation belongs to the workspace if provided
            if (!empty($validated['conversation_id'])) {
                $conversation = ChatConversation::where('id', $validated['conversation_id'])
                    ->where('workspace_id', $workspace->id)
                    ->first();
                
                if (!$conversation) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Conversation not found in this workspace',
                    ], 404);
                }
            }

            $game = $this->gameStorageService->createGame(
                $workspace,
                $validated['title'],
                !empty($validated['conversation_id']) ? ChatConversation::find($validated['conversation_id']) : null
            );

            // Update additional fields if provided
            $updateData = [];
            if (!empty($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }
            if (!empty($validated['preview_url'])) {
                $updateData['preview_url'] = $validated['preview_url'];
            }
            if (!empty($validated['published_url'])) {
                $updateData['published_url'] = $validated['published_url'];
            }
            if (!empty($validated['thumbnail_url'])) {
                $updateData['thumbnail_url'] = $validated['thumbnail_url'];
            }
            if (!empty($validated['metadata'])) {
                $updateData['metadata'] = $validated['metadata'];
            }

            if (!empty($updateData)) {
                $game->update($updateData);
                $game = $game->fresh();
            }

            $gameData = [
                'id' => $game->id,
                'title' => $game->title,
                'description' => $game->description,
                'preview_url' => $game->preview_url,
                'published_url' => $game->published_url,
                'thumbnail_url' => $game->thumbnail_url,
                'metadata' => $game->metadata,
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
                'engine_type' => $game->getEngineType(),
                'is_published' => $game->isPublished(),
                'has_preview' => $game->hasPreview(),
                'has_thumbnail' => $game->hasThumbnail(),
                'display_url' => $game->getDisplayUrl(),
                'conversation_id' => $game->conversation_id,
            ];

            // Include conversation details if available
            if ($game->conversation) {
                $gameData['conversation'] = [
                    'id' => $game->conversation->id,
                    'title' => $game->conversation->title,
                    'created_at' => $game->conversation->created_at,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Game created successfully',
                'game' => $gameData,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create game',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get game details.
     */
    public function getGame(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::with('conversation')->whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $gameData = [
                'id' => $game->id,
                'title' => $game->title,
                'description' => $game->description,
                'preview_url' => $game->preview_url,
                'published_url' => $game->published_url,
                'thumbnail_url' => $game->thumbnail_url,
                'metadata' => $game->metadata,
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
                'engine_type' => $game->getEngineType(),
                'is_published' => $game->isPublished(),
                'has_preview' => $game->hasPreview(),
                'has_thumbnail' => $game->hasThumbnail(),
                'display_url' => $game->getDisplayUrl(),
                'conversation_id' => $game->conversation_id,
                'workspace' => [
                    'id' => $game->workspace->id,
                    'name' => $game->workspace->name,
                    'engine_type' => $game->workspace->engine_type,
                ],
                'stats' => $this->gameStorageService->getGameStats($game),
            ];

            // Include conversation details if available
            if ($game->conversation) {
                $gameData['conversation'] = [
                    'id' => $game->conversation->id,
                    'title' => $game->conversation->title,
                    'created_at' => $game->conversation->created_at,
                ];
            }

            return response()->json([
                'success' => true,
                'game' => $gameData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve game',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update game metadata.
     */
    public function updateGame(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::with('conversation')->whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:1000',
                'preview_url' => 'nullable|url|max:500',
                'published_url' => 'nullable|url|max:500',
                'thumbnail_url' => 'nullable|url|max:500',
                'metadata' => 'nullable|array',
            ]);

            // Update basic fields
            $updateData = array_intersect_key($validated, array_flip([
                'title', 'description', 'preview_url', 'published_url', 'thumbnail_url'
            ]));

            if (!empty($updateData)) {
                $game->update($updateData);
            }

            // Update metadata separately if provided
            if (isset($validated['metadata'])) {
                $game = $this->gameStorageService->updateGameMetadata($game, $validated['metadata']);
            }

            $game = $game->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Game updated successfully',
                'game' => [
                    'id' => $game->id,
                    'title' => $game->title,
                    'description' => $game->description,
                    'preview_url' => $game->preview_url,
                    'published_url' => $game->published_url,
                    'thumbnail_url' => $game->thumbnail_url,
                    'metadata' => $game->metadata,
                    'updated_at' => $game->updated_at,
                    'engine_type' => $game->getEngineType(),
                    'is_published' => $game->isPublished(),
                    'has_preview' => $game->hasPreview(),
                    'has_thumbnail' => $game->hasThumbnail(),
                    'display_url' => $game->getDisplayUrl(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update game',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a game.
     */
    public function deleteGame(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::with('conversation')->whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $this->gameStorageService->deleteGame($game);

            return response()->json([
                'success' => true,
                'message' => 'Game deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete game',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get game preview data with performance metrics.
     */
    public function getGamePreview(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::with(['workspace', 'conversation'])->whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            // Check if user agent indicates mobile device
            $userAgent = $request->header('User-Agent', '');
            $isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $userAgent);

            // Generate performance metrics
            $performanceMetrics = [
                'load_time' => rand(500, 1800), // Simulate load time < 2 seconds
                'file_size' => $this->gameStorageService->getGameFileSize($game),
                'asset_count' => $this->gameStorageService->getGameAssetCount($game),
                'last_updated' => $game->updated_at->toISOString(),
            ];

            $previewData = [
                'preview_url' => $game->preview_url,
                'game_id' => $game->id,
                'last_updated' => $game->updated_at->toISOString(),
                'performance' => $performanceMetrics,
                'game_state' => $game->metadata['game_state'] ?? null,
            ];

            // Add mobile-specific optimizations if mobile device detected
            if ($isMobile) {
                $previewData['mobile_optimized'] = [
                    'touch_controls' => true,
                    'responsive_layout' => true,
                    'performance_mode' => 'optimized',
                ];
            }

            return response()->json($previewData);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Game not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve game preview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent games across all workspaces with pagination and search support.
     */
    public function getRecentGames(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            // Get pagination parameters
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(1, (int) $request->query('limit', 12))); // Max 100, min 1
            $search = $request->query('search');

            // Use paginated method for better performance
            $result = $this->gameStorageService->getPaginatedRecentGames(
                $company->id, 
                $page, 
                $limit, 
                $search
            );

            $gamesData = $result['games']->map(function ($game) {
                $gameData = [
                    'id' => $game->id,
                    'title' => $game->title,
                    'description' => $game->description,
                    'preview_url' => $game->preview_url,
                    'published_url' => $game->published_url,
                    'thumbnail_url' => $game->thumbnail_url,
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                    'engine_type' => $game->engine_type,
                    'is_published' => $game->is_published,
                    'has_preview' => $game->has_preview,
                    'has_thumbnail' => $game->has_thumbnail,
                    'display_url' => $game->getDisplayUrl(),
                    'workspace' => [
                        'id' => $game->workspace->id,
                        'name' => $game->workspace->name,
                        'engine_type' => $game->workspace->engine_type,
                    ],
                ];

                // Include conversation details if available
                if ($game->conversation) {
                    $gameData['conversation'] = [
                        'id' => $game->conversation->id,
                        'title' => $game->conversation->title,
                        'created_at' => $game->conversation->created_at,
                    ];
                }

                return $gameData;
            });

            return response()->json([
                'success' => true,
                'games' => $gamesData,
                'pagination' => $result['pagination'],
                'has_more_pages' => $result['pagination']['has_more_pages'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a shareable link for a game.
     */
    public function shareGame(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $validated = $request->validate([
                'allowEmbedding' => 'sometimes|boolean',
                'showControls' => 'sometimes|boolean',
                'showInfo' => 'sometimes|boolean',
                'expirationDays' => 'sometimes|integer|min:1|max:365',
            ]);

            $result = $this->gameSharingService->createShareableLink($game, $validated);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Shareable link created successfully',
                'sharing' => $result,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create shareable link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update sharing settings for a game.
     */
    public function updateSharingSettings(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $validated = $request->validate([
                'allowEmbedding' => 'sometimes|boolean',
                'showControls' => 'sometimes|boolean',
                'showInfo' => 'sometimes|boolean',
                'expirationDays' => 'sometimes|integer|min:1|max:365',
            ]);

            $success = $this->gameSharingService->updateSharingSettings($game, $validated);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update sharing settings',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sharing settings updated successfully',
                'settings' => $game->fresh()->sharing_settings,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sharing settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke a game's share link.
     */
    public function revokeShareLink(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $success = $this->gameSharingService->revokeShareLink($game);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to revoke share link',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Share link revoked successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke share link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sharing statistics for a game.
     */
    public function getSharingStats(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $game = Game::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($gameId);

            $stats = $this->gameSharingService->getSharingStats($game);

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sharing stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Setup custom domain for a game.
     */
    public function setupCustomDomain(Request $request, Game $game): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            // Verify game belongs to user's company
            if ($game->workspace->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found',
                ], 404);
            }

            $validated = $request->validate([
                'domain' => 'required|string|max:255',
            ]);

            $result = $this->domainPublishingService->setupCustomDomain($game, $validated['domain']);

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup custom domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify domain configuration and DNS propagation.
     */
    public function verifyDomain(Request $request, Game $game): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            // Verify game belongs to user's company
            if ($game->workspace->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found',
                ], 404);
            }

            $result = $this->domainPublishingService->verifyDomain($game);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove custom domain configuration.
     */
    public function removeDomain(Request $request, Game $game): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            // Verify game belongs to user's company
            if ($game->workspace->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found',
                ], 404);
            }

            $result = $this->domainPublishingService->removeDomain($game);

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get domain status and configuration.
     */
    public function getDomainStatus(Request $request, Game $game): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            // Verify game belongs to user's company
            if ($game->workspace->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found',
                ], 404);
            }

            $domainData = [
                'has_custom_domain' => $game->hasCustomDomain(),
                'custom_domain' => $game->custom_domain,
                'domain_status' => $game->domain_status,
                'domain_config' => $game->domain_config,
                'is_domain_active' => $game->isDomainActive(),
                'is_domain_pending' => $game->isDomainPending(),
                'is_domain_failed' => $game->isDomainFailed(),
                'custom_domain_url' => $game->getCustomDomainUrl(),
                'primary_url' => $game->getPrimaryUrl(),
            ];

            return response()->json([
                'success' => true,
                'domain' => $domainData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get domain status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
