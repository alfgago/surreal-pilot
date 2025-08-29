<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Workspace;
use App\Models\ChatConversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameStorageService
{
    /**
     * Create a new game with details.
     */
    public function createGameWithDetails(
        Workspace $workspace,
        string $title,
        string $description = '',
        string $engine = 'playcanvas',
        ?string $templateId = null
    ): Game {
        $game = Game::create([
            'workspace_id' => $workspace->id,
            'title' => $title,
            'description' => $description,
            'engine_type' => $engine,
            'template_id' => $templateId,
            'status' => 'draft',
        ]);

        // Create game directory
        $this->createGameDirectory($game);

        Log::info('Game created successfully', [
            'game_id' => $game->id,
            'workspace_id' => $workspace->id,
            'title' => $title,
            'engine' => $engine,
        ]);

        return $game;
    }

    /**
     * Create a game from a chat conversation.
     */
    public function createGame(
        Workspace $workspace, 
        string $title, 
        ?ChatConversation $conversation = null
    ): Game {
        $game = Game::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation?->id,
            'title' => $title,
            'status' => 'draft',
            'engine_type' => $workspace->engine_type ?? 'playcanvas',
        ]);

        // Create game directory
        $this->createGameDirectory($game);

        return $game;
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
     * Get paginated games for a workspace.
     */
    public function getPaginatedWorkspaceGames(Workspace $workspace, int $perPage = 15): array
    {
        $games = $workspace->games()
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return [
            'games' => collect($games->items()),
            'pagination' => [
                'current_page' => $games->currentPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
                'last_page' => $games->lastPage(),
                'has_more_pages' => $games->hasMorePages(),
            ]
        ];
    }

    /**
     * Get game statistics.
     */
    public function getGameStats(Game $game): array
    {
        return [
            'file_count' => 0, // Placeholder
            'total_size' => 0, // Placeholder
            'last_modified' => $game->updated_at,
            'build_count' => 0, // Placeholder
        ];
    }

    /**
     * Update game metadata.
     */
    public function updateGameMetadata(Game $game, array $data): Game
    {
        $game->update($data);
        return $game->fresh();
    }

    /**
     * Get paginated recent games for a company.
     */
    public function getPaginatedRecentGames($companyId, int $page = 1, int $limit = 15, ?string $search = null): array
    {
        $query = Game::whereHas('workspace', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $games = $query->orderBy('updated_at', 'desc')
                      ->paginate($limit, ['*'], 'page', $page);

        return [
            'games' => collect($games->items()),
            'pagination' => [
                'current_page' => $games->currentPage(),
                'per_page' => $games->perPage(),
                'total' => $games->total(),
                'last_page' => $games->lastPage(),
                'has_more_pages' => $games->hasMorePages(),
            ]
        ];
    }

    /**
     * Get available templates for an engine type.
     */
    public function getAvailableTemplates(string $engineType): array
    {
        // Return basic templates based on engine type
        return match($engineType) {
            'playcanvas' => [
                ['id' => 'blank', 'name' => 'Blank Project', 'description' => 'Start from scratch'],
                ['id' => 'platformer', 'name' => '2D Platformer', 'description' => 'Basic platformer template'],
                ['id' => 'fps', 'name' => 'First Person', 'description' => 'FPS template with controls'],
            ],
            'unreal' => [
                ['id' => 'blank', 'name' => 'Blank Project', 'description' => 'Empty Unreal project'],
                ['id' => 'third_person', 'name' => 'Third Person', 'description' => 'Third person template'],
                ['id' => 'first_person', 'name' => 'First Person', 'description' => 'First person template'],
            ],
            default => []
        };
    }

    /**
     * Get game files for a game.
     */
    public function getGameFiles(Game $game): array
    {
        $gameDir = $this->getGameDirectory($game);
        
        if (!Storage::exists($gameDir)) {
            return [];
        }

        $files = Storage::allFiles($gameDir);
        
        return collect($files)->map(function ($file) use ($gameDir) {
            $relativePath = str_replace($gameDir . '/', '', $file);
            return [
                'name' => basename($file),
                'path' => $relativePath,
                'size' => Storage::size($file),
                'modified' => Storage::lastModified($file),
                'url' => Storage::url($file),
            ];
        })->toArray();
    }

    /**
     * Store game content (HTML, assets, etc.).
     */
    public function storeGameContent(Game $game, string $content, string $filename = 'index.html'): string
    {
        $gameDir = $this->getGameDirectory($game);
        $filePath = $gameDir . '/' . $filename;
        
        Storage::put($filePath, $content);
        
        // Update game preview URL
        $previewUrl = Storage::url($filePath);
        $game->update(['preview_url' => $previewUrl]);
        
        Log::info('Game content stored', [
            'game_id' => $game->id,
            'filename' => $filename,
            'size' => strlen($content),
        ]);
        
        return $previewUrl;
    }

    /**
     * Delete a game and its associated files.
     */
    public function deleteGame(Game $game): bool
    {
        try {
            // Delete game files
            $gameDir = $this->getGameDirectory($game);
            if (Storage::exists($gameDir)) {
                Storage::deleteDirectory($gameDir);
            }

            // Delete the game record
            $game->delete();

            Log::info('Game deleted successfully', [
                'game_id' => $game->id,
                'workspace_id' => $game->workspace_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete game', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Publish a game (make it publicly accessible).
     */
    public function publishGame(Game $game): bool
    {
        try {
            // Generate share token if not exists
            if (!$game->share_token) {
                $game->update(['share_token' => Str::random(32)]);
            }

            // Update published URL
            $publishedUrl = url("/games/shared/{$game->share_token}");
            $game->update([
                'published_url' => $publishedUrl,
                'is_public' => true,
                'published_at' => now(),
                'status' => 'published',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish game', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create game directory structure.
     */
    private function createGameDirectory(Game $game): void
    {
        $gameDir = $this->getGameDirectory($game);
        
        if (!Storage::exists($gameDir)) {
            Storage::makeDirectory($gameDir);
            Storage::makeDirectory($gameDir . '/assets');
            Storage::makeDirectory($gameDir . '/builds');
        }
    }

    /**
     * Get game file size in bytes.
     */
    public function getGameFileSize(Game $game): int
    {
        $gameDir = $this->getGameDirectory($game);
        
        if (!Storage::exists($gameDir)) {
            return 0;
        }

        $files = Storage::allFiles($gameDir);
        $totalSize = 0;
        
        foreach ($files as $file) {
            $totalSize += Storage::size($file);
        }
        
        return $totalSize;
    }

    /**
     * Get game asset count.
     */
    public function getGameAssetCount(Game $game): int
    {
        $gameDir = $this->getGameDirectory($game);
        
        if (!Storage::exists($gameDir)) {
            return 0;
        }

        $files = Storage::allFiles($gameDir);
        return count($files);
    }

    /**
     * Get the storage directory path for a game.
     */
    private function getGameDirectory(Game $game): string
    {
        return "workspaces/{$game->workspace_id}/games/{$game->id}";
    }
}