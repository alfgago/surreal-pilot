<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GameSharingService
{
    public function __construct(
        private GameStorageService $gameStorageService
    ) {}

    /**
     * Create a shareable link for a game with configurable options.
     */
    public function createShareableLink(Game $game, array $options = []): array
    {
        try {
            // Default sharing options
            $defaultOptions = [
                'allowEmbedding' => true,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ];

            $sharingOptions = array_merge($defaultOptions, $options);

            // Generate share token if not exists
            if (!$game->share_token) {
                $shareToken = $this->generateShareToken();
                $game->update(['share_token' => $shareToken]);
            }

            // Create game snapshot for sharing
            $snapshotPath = $this->createGameSnapshot($game);

            // Update sharing settings
            $game->update([
                'sharing_settings' => $sharingOptions,
                'is_public' => true,
            ]);

            // Generate URLs
            $shareUrl = url("/games/shared/{$game->share_token}");
            $embedUrl = $sharingOptions['allowEmbedding'] 
                ? url("/games/embed/{$game->share_token}") 
                : null;

            $result = [
                'success' => true,
                'share_token' => $game->share_token,
                'share_url' => $shareUrl,
                'embed_url' => $embedUrl,
                'expires_at' => $sharingOptions['expirationDays'] 
                    ? Carbon::now()->addDays($sharingOptions['expirationDays'])->toISOString()
                    : null,
                'options' => $sharingOptions,
                'snapshot_path' => $snapshotPath,
                'created_at' => now()->toISOString(),
            ];

            Log::info('Shareable link created', [
                'game_id' => $game->id,
                'share_token' => $game->share_token,
                'options' => $sharingOptions,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create shareable link', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create shareable link',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate and retrieve a shared game by token.
     */
    public function getSharedGame(string $shareToken): ?Game
    {
        try {
            $game = Game::where('share_token', $shareToken)
                ->where('is_public', true)
                ->first();

            if (!$game) {
                return null;
            }

            // Check if share link has expired
            if ($this->isShareLinkExpired($game)) {
                Log::info('Share link expired', [
                    'game_id' => $game->id,
                    'share_token' => $shareToken,
                ]);
                return null;
            }

            // Increment play count
            $game->incrementPlayCount();

            return $game;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve shared game', [
                'share_token' => $shareToken,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a snapshot of the game for sharing.
     */
    public function createGameSnapshot(Game $game): string
    {
        try {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $snapshotDir = "shared-games/{$game->share_token}/snapshots/{$timestamp}";
            $gameDir = "workspaces/{$game->workspace_id}/games/{$game->id}";

            // Copy game files to snapshot directory
            if (Storage::exists($gameDir)) {
                $files = Storage::allFiles($gameDir);
                
                foreach ($files as $file) {
                    $relativePath = str_replace($gameDir . '/', '', $file);
                    $snapshotFile = $snapshotDir . '/' . $relativePath;
                    
                    Storage::copy($file, $snapshotFile);
                }
            }

            // Create snapshot metadata
            $metadata = [
                'game_id' => $game->id,
                'game_title' => $game->title,
                'game_description' => $game->description,
                'created_at' => now()->toISOString(),
                'original_game_metadata' => $game->metadata,
                'sharing_settings' => $game->sharing_settings,
                'snapshot_version' => '1.0',
            ];

            Storage::put($snapshotDir . '/snapshot.json', json_encode($metadata, JSON_PRETTY_PRINT));

            // Update game with latest snapshot path
            $game->update([
                'metadata' => array_merge($game->metadata ?? [], [
                    'latest_snapshot' => $snapshotDir,
                    'snapshot_created_at' => now()->toISOString(),
                ])
            ]);

            Log::info('Game snapshot created', [
                'game_id' => $game->id,
                'snapshot_path' => $snapshotDir,
            ]);

            return $snapshotDir;
        } catch (\Exception $e) {
            Log::error('Failed to create game snapshot', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get shared game content for public access.
     */
    public function getSharedGameContent(string $shareToken): ?array
    {
        $game = $this->getSharedGame($shareToken);
        
        if (!$game) {
            return null;
        }

        try {
            // Get the latest snapshot or fallback to original game files
            $snapshotPath = $game->metadata['latest_snapshot'] ?? null;
            $contentPath = $snapshotPath 
                ? $snapshotPath . '/index.html'
                : "workspaces/{$game->workspace_id}/games/{$game->id}/index.html";

            $content = Storage::exists($contentPath) 
                ? Storage::get($contentPath) 
                : $this->generateDefaultGameContent($game);

            return [
                'content' => $content,
                'game' => [
                    'id' => $game->id,
                    'title' => $game->title,
                    'description' => $game->description,
                    'engine_type' => $game->getEngineType(),
                    'sharing_settings' => $game->sharing_settings ?? [],
                ],
                'assets' => $this->getSharedGameAssets($game, $snapshotPath),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get shared game content', [
                'game_id' => $game->id,
                'share_token' => $shareToken,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get assets for a shared game.
     */
    private function getSharedGameAssets(Game $game, ?string $snapshotPath = null): array
    {
        try {
            $assetsDir = $snapshotPath 
                ? $snapshotPath . '/assets'
                : "workspaces/{$game->workspace_id}/games/{$game->id}/assets";

            if (!Storage::exists($assetsDir)) {
                return [];
            }

            $files = Storage::allFiles($assetsDir);
            
            return collect($files)->map(function ($file) use ($assetsDir) {
                $relativePath = str_replace($assetsDir . '/', '', $file);
                return [
                    'name' => basename($file),
                    'path' => $relativePath,
                    'url' => Storage::url($file),
                    'size' => Storage::size($file),
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get shared game assets', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if a share link has expired.
     */
    private function isShareLinkExpired(Game $game): bool
    {
        $sharingSettings = $game->sharing_settings ?? [];
        $expirationDays = $sharingSettings['expirationDays'] ?? null;

        if (!$expirationDays) {
            return false; // No expiration set
        }

        $createdAt = $game->updated_at; // Use last update as creation time for sharing
        $expiresAt = $createdAt->addDays($expirationDays);

        return now()->isAfter($expiresAt);
    }

    /**
     * Generate a unique share token.
     */
    private function generateShareToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Game::where('share_token', $token)->exists());

        return $token;
    }

    /**
     * Generate default game content if no content exists.
     */
    private function generateDefaultGameContent(Game $game): string
    {
        $title = htmlspecialchars($game->title);
        $description = htmlspecialchars($game->description ?? '');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .game-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        h1 {
            margin-bottom: 20px;
            font-size: 2.5em;
        }
        p {
            font-size: 1.2em;
            margin-bottom: 30px;
        }
        .status {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 5px;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>{$title}</h1>
        <p>{$description}</p>
        <div class="status">
            Game is being prepared for sharing...
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Revoke a share link.
     */
    public function revokeShareLink(Game $game): bool
    {
        try {
            $game->update([
                'share_token' => null,
                'is_public' => false,
                'sharing_settings' => null,
            ]);

            Log::info('Share link revoked', [
                'game_id' => $game->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to revoke share link', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update sharing settings for a game.
     */
    public function updateSharingSettings(Game $game, array $settings): bool
    {
        try {
            $currentSettings = $game->sharing_settings ?? [];
            $updatedSettings = array_merge($currentSettings, $settings);

            $game->update(['sharing_settings' => $updatedSettings]);

            Log::info('Sharing settings updated', [
                'game_id' => $game->id,
                'settings' => $updatedSettings,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update sharing settings', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get sharing statistics for a game.
     */
    public function getSharingStats(Game $game): array
    {
        return [
            'total_plays' => $game->play_count ?? 0,
            'last_played' => $game->last_played_at?->toISOString(),
            'is_public' => $game->is_public ?? false,
            'has_share_token' => !empty($game->share_token),
            'sharing_settings' => $game->sharing_settings ?? [],
            'created_at' => $game->created_at->toISOString(),
            'updated_at' => $game->updated_at->toISOString(),
        ];
    }
}