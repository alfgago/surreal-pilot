<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Workspace;
use App\Models\ChatConversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class GameStorageService
{
    /**
     * Create a new game.
     */
    public function createGame(
        Workspace $workspace, 
        string $title, 
        ?ChatConversation $conversation = null
    ): Game {
        return Game::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation?->id,
            'title' => $title,
        ]);
    }

    /**
     * Get all games for a workspace.
     */
    public function getWorkspaceGames(Workspace $workspace): Collection
    {
        return $workspace->games()
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Update game metadata.
     */
    public function updateGameMetadata(Game $game, array $metadata): Game
    {
        $currentMetadata = $game->metadata ?? [];
        $updatedMetadata = array_merge($currentMetadata, $metadata);
        
        $game->update(['metadata' => $updatedMetadata]);
        return $game->fresh();
    }

    /**
     * Set game URLs.
     */
    public function setGameUrls(Game $game, ?string $previewUrl = null, ?string $publishedUrl = null): Game
    {
        $updateData = [];
        
        if ($previewUrl !== null) {
            $updateData['preview_url'] = $previewUrl;
        }
        
        if ($publishedUrl !== null) {
            $updateData['published_url'] = $publishedUrl;
        }
        
        if (!empty($updateData)) {
            $game->update($updateData);
        }
        
        return $game->fresh();
    }

    /**
     * Generate thumbnail for a game.
     */
    public function generateThumbnail(Game $game): ?string
    {
        // This would integrate with a thumbnail generation service
        // For now, we'll create a placeholder thumbnail URL
        
        if ($game->hasPreview()) {
            // Generate thumbnail from preview URL
            $thumbnailUrl = $this->createThumbnailFromPreview($game->preview_url);
            
            if ($thumbnailUrl) {
                $game->update(['thumbnail_url' => $thumbnailUrl]);
                return $thumbnailUrl;
            }
        }
        
        // Return existing thumbnail or null
        return $game->thumbnail_url;
    }

    /**
     * Delete a game and its associated files.
     */
    public function deleteGame(Game $game): bool
    {
        // Clean up associated files
        $this->cleanupGameFiles($game);
        
        return $game->delete();
    }

    /**
     * Get games by engine type.
     */
    public function getGamesByEngine(int $companyId, string $engineType): Collection
    {
        return Game::whereHas('workspace', function ($query) use ($companyId, $engineType) {
            $query->where('company_id', $companyId)
                  ->where('engine_type', $engineType);
        })
        ->orderBy('updated_at', 'desc')
        ->get();
    }

    /**
     * Get recent games across all workspaces for a company.
     */
    public function getRecentGames(int $companyId, int $limit = 10): Collection
    {
        return Game::whereHas('workspace', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->with(['workspace', 'conversation'])
        ->get();
    }

    /**
     * Search games by title or description.
     */
    public function searchGames(Workspace $workspace, string $query): Collection
    {
        return $workspace->games()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Get game statistics.
     */
    public function getGameStats(Game $game): array
    {
        return [
            'created_at' => $game->created_at,
            'updated_at' => $game->updated_at,
            'engine_type' => $game->getEngineType(),
            'has_preview' => $game->hasPreview(),
            'has_thumbnail' => $game->hasThumbnail(),
            'is_published' => $game->isPublished(),
            'conversation_id' => $game->conversation_id,
        ];
    }

    /**
     * Create thumbnail from preview URL.
     */
    private function createThumbnailFromPreview(string $previewUrl): ?string
    {
        // This would integrate with a screenshot/thumbnail service
        // For now, return a placeholder or the preview URL itself
        return $previewUrl . '/thumbnail';
    }

    /**
     * Clean up game files.
     */
    private function cleanupGameFiles(Game $game): void
    {
        // Clean up thumbnails, builds, and other associated files
        if ($game->thumbnail_url) {
            // Delete thumbnail file if it's stored locally
            $thumbnailPath = parse_url($game->thumbnail_url, PHP_URL_PATH);
            if ($thumbnailPath && Storage::exists($thumbnailPath)) {
                Storage::delete($thumbnailPath);
            }
        }
        
        // Additional cleanup for game builds, assets, etc.
        // This would depend on how game files are stored
    }

    /**
     * Duplicate a game.
     */
    public function duplicateGame(Game $originalGame, ?string $newTitle = null): Game
    {
        $newGame = Game::create([
            'workspace_id' => $originalGame->workspace_id,
            'conversation_id' => null, // New game doesn't belong to original conversation
            'title' => $newTitle ?? ($originalGame->title . ' (Copy)'),
            'description' => $originalGame->description,
            'metadata' => $originalGame->metadata,
        ]);

        return $newGame;
    }
}