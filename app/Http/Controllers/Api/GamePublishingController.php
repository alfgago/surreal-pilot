<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GamePublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GamePublishingController extends Controller
{
    public function __construct(
        private GamePublishingService $publishingService
    ) {}

    /**
     * Start a build for the game.
     */
    public function startBuild(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate build configuration
        $validator = Validator::make($request->all(), [
            'minify' => 'boolean',
            'optimize_assets' => 'boolean',
            'include_debug' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $build = $this->publishingService->startBuild($game, $request->all());

            return response()->json([
                'message' => 'Build started successfully',
                'build' => [
                    'id' => $build->id,
                    'version' => $build->version,
                    'status' => $build->status,
                    'started_at' => $build->started_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to start build: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get build status for the game.
     */
    public function getBuildStatus(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $latestBuild = $game->latestBuild();

        return response()->json([
            'build_status' => $game->build_status,
            'last_build_at' => $game->last_build_at?->toISOString(),
            'latest_build' => $latestBuild ? [
                'id' => $latestBuild->id,
                'version' => $latestBuild->version,
                'status' => $latestBuild->status,
                'build_duration' => $latestBuild->getBuildDurationFormatted(),
                'total_size' => $latestBuild->getTotalSizeFormatted(),
                'file_count' => $latestBuild->file_count,
                'created_at' => $latestBuild->created_at->toISOString(),
                'completed_at' => $latestBuild->completed_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * Get build history for the game.
     */
    public function getBuildHistory(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $history = $this->publishingService->getBuildHistory($game);

        return response()->json([
            'builds' => $history,
        ]);
    }

    /**
     * Publish the game.
     */
    public function publishGame(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate publishing options
        $validator = Validator::make($request->all(), [
            'is_public' => 'boolean',
            'sharing_settings.allow_embedding' => 'boolean',
            'sharing_settings.show_controls' => 'boolean',
            'sharing_settings.show_info' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $success = $this->publishingService->publishGame($game, $request->all());

            if ($success) {
                return response()->json([
                    'message' => 'Game published successfully',
                    'game' => [
                        'id' => $game->id,
                        'status' => $game->fresh()->status,
                        'published_at' => $game->fresh()->published_at?->toISOString(),
                        'share_url' => $game->fresh()->getShareUrl(),
                        'embed_url' => $game->fresh()->getEmbedUrl(),
                    ],
                ]);
            } else {
                return response()->json(['error' => 'Failed to publish game'], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to publish game: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unpublish the game.
     */
    public function unpublishGame(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $success = $this->publishingService->unpublishGame($game);

            if ($success) {
                return response()->json([
                    'message' => 'Game unpublished successfully',
                    'game' => [
                        'id' => $game->id,
                        'status' => $game->fresh()->status,
                    ],
                ]);
            } else {
                return response()->json(['error' => 'Failed to unpublish game'], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to unpublish game: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate or regenerate share token for the game.
     */
    public function generateShareToken(Request $request, Game $game): JsonResponse
    {
        // Check if user has access to this game
        $user = $request->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $token = $game->generateShareToken();

            return response()->json([
                'message' => 'Share token generated successfully',
                'share_token' => $token,
                'share_url' => $game->getShareUrl(),
                'embed_url' => $game->getEmbedUrl(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate share token: ' . $e->getMessage()
            ], 500);
        }
    }
}