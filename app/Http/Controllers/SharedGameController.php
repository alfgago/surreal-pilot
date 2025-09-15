<?php

namespace App\Http\Controllers;

use App\Services\GameSharingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SharedGameController extends Controller
{
    public function __construct(
        private GameSharingService $gameSharingService
    ) {}

    /**
     * Display a shared game by token (public access, no authentication required).
     */
    public function show(string $shareToken)
    {
        try {
            $gameContent = $this->gameSharingService->getSharedGameContent($shareToken);

            if (!$gameContent) {
                return response('<html><body><h1>404 - Game Not Found</h1><p>Game not found or share link has expired</p></body></html>', 404)
                    ->header('Content-Type', 'text/html');
            }

            $game = $gameContent['game'];
            $content = $gameContent['content'];
            $assets = $gameContent['assets'];

            // Return the game content directly as HTML
            return response($content)
                ->header('Content-Type', 'text/html')
                ->header('X-Frame-Options', 'SAMEORIGIN') // Allow embedding
                ->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
        } catch (\Exception $e) {
            Log::error('Failed to display shared game', [
                'share_token' => $shareToken,
                'error' => $e->getMessage(),
            ]);

            return response('<html><body><h1>500 - Server Error</h1><p>Failed to load game</p></body></html>', 500)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * Display a shared game in embed mode (for iframes).
     */
    public function embed(string $shareToken)
    {
        try {
            $gameContent = $this->gameSharingService->getSharedGameContent($shareToken);

            if (!$gameContent) {
                return response()->json([
                    'error' => 'Game not found or share link has expired'
                ], 404);
            }

            $game = $gameContent['game'];
            $sharingSettings = $game['sharing_settings'] ?? [];

            // Check if embedding is allowed
            if (!($sharingSettings['allowEmbedding'] ?? true)) {
                return response()->json([
                    'error' => 'Embedding is not allowed for this game'
                ], 403);
            }

            $content = $gameContent['content'];

            // Modify content for embedding (remove controls if needed)
            if (!($sharingSettings['showControls'] ?? true)) {
                $content = $this->removeControlsFromContent($content);
            }

            return response($content)
                ->header('Content-Type', 'text/html')
                ->header('X-Frame-Options', 'ALLOWALL') // Allow embedding in any frame
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            Log::error('Failed to display embedded game', [
                'share_token' => $shareToken,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load game'
            ], 500);
        }
    }

    /**
     * Get game metadata for a shared game (JSON API).
     */
    public function metadata(string $shareToken)
    {
        try {
            $game = $this->gameSharingService->getSharedGame($shareToken);

            if (!$game) {
                return response()->json([
                    'error' => 'Game not found or share link has expired'
                ], 404);
            }

            $sharingSettings = $game->sharing_settings ?? [];

            $metadata = [
                'title' => $game->title,
                'description' => $game->description,
                'engine_type' => $game->getEngineType(),
                'play_count' => $game->play_count ?? 0,
                'last_played' => $game->last_played_at?->toISOString(),
                'sharing_settings' => $sharingSettings,
            ];

            // Only include info if allowed by sharing settings
            if (!($sharingSettings['showInfo'] ?? true)) {
                $metadata = [
                    'title' => $game->title,
                    'engine_type' => $game->getEngineType(),
                ];
            }

            return response()->json([
                'success' => true,
                'game' => $metadata,
            ])->header('Cache-Control', 'public, max-age=1800'); // Cache for 30 minutes
        } catch (\Exception $e) {
            Log::error('Failed to get shared game metadata', [
                'share_token' => $shareToken,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load game metadata'
            ], 500);
        }
    }

    /**
     * Get assets for a shared game.
     */
    public function assets(string $shareToken, string $assetPath)
    {
        try {
            $game = $this->gameSharingService->getSharedGame($shareToken);

            if (!$game) {
                return response()->json([
                    'error' => 'Game not found or share link has expired'
                ], 404);
            }

            // Get the asset content
            $snapshotPath = $game->metadata['latest_snapshot'] ?? null;
            $fullAssetPath = $snapshotPath 
                ? $snapshotPath . '/assets/' . $assetPath
                : "workspaces/{$game->workspace_id}/games/{$game->id}/assets/" . $assetPath;

            if (!\Illuminate\Support\Facades\Storage::exists($fullAssetPath)) {
                return response()->json([
                    'error' => 'Asset not found'
                ], 404);
            }

            $content = \Illuminate\Support\Facades\Storage::get($fullAssetPath);
            $mimeType = $this->getMimeType($assetPath);

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400'); // Cache for 24 hours
        } catch (\Exception $e) {
            Log::error('Failed to serve shared game asset', [
                'share_token' => $shareToken,
                'asset_path' => $assetPath,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load asset'
            ], 500);
        }
    }

    /**
     * Remove controls from game content for embedding.
     */
    private function removeControlsFromContent(string $content): string
    {
        // Remove common control elements (this is a basic implementation)
        $patterns = [
            '/<div[^>]*class="[^"]*controls[^"]*"[^>]*>.*?<\/div>/is',
            '/<button[^>]*class="[^"]*control[^"]*"[^>]*>.*?<\/button>/is',
            '/<nav[^>]*>.*?<\/nav>/is',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        return $content;
    }

    /**
     * Get MIME type for an asset file.
     */
    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match($extension) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html', 'htm' => 'text/html',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => 'application/octet-stream',
        };
    }
}