<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Services\GameStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameStorageService $gameStorageService
    ) {}

    /**
     * Get games for a workspace.
     */
    public function getWorkspaceGames(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $games = $this->gameStorageService->getWorkspaceGames($workspace);
            $games->load('conversation');

            $gamesData = $games->map(function ($game) {
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

                return $gameData;
            });

            return response()->json([
                'success' => true,
                'games' => $gamesData,
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
     * Get recent games across all workspaces.
     */
    public function getRecentGames(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $limit = $request->query('limit', 10);
            $games = $this->gameStorageService->getRecentGames($company->id, $limit);

            $gamesData = $games->map(function ($game) {
                $gameData = [
                    'id' => $game->id,
                    'title' => $game->title,
                    'description' => $game->description,
                    'preview_url' => $game->preview_url,
                    'published_url' => $game->published_url,
                    'thumbnail_url' => $game->thumbnail_url,
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                    'engine_type' => $game->getEngineType(),
                    'is_published' => $game->isPublished(),
                    'has_preview' => $game->hasPreview(),
                    'has_thumbnail' => $game->hasThumbnail(),
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
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
