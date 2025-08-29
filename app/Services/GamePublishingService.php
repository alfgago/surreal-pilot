<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameBuild;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GamePublishingService
{
    public function __construct(
        private GameStorageService $gameStorageService
    ) {}

    /**
     * Start a build process for the game.
     */
    public function startBuild(Game $game, array $buildConfig = []): GameBuild
    {
        // Update game build status
        $game->update([
            'build_status' => 'building',
            'last_build_at' => now(),
        ]);

        // Create build record
        $build = GameBuild::create([
            'game_id' => $game->id,
            'version' => $this->getNextVersion($game),
            'status' => 'building',
            'build_config' => $buildConfig,
            'started_at' => now(),
        ]);

        // Start the build process asynchronously
        $this->processBuild($build);

        return $build;
    }

    /**
     * Process the build for a game.
     */
    private function processBuild(GameBuild $build): void
    {
        try {
            $game = $build->game;
            
            Log::info("Starting build for game {$game->id}, build {$build->id}");

            // Get game files
            $files = $this->gameStorageService->getGameFiles($game);
            
            // Build configuration
            $config = array_merge([
                'minify' => true,
                'optimize_assets' => true,
                'include_debug' => false,
            ], $build->build_config ?? []);

            // For PlayCanvas games, we need to bundle and optimize
            if ($game->getEngineType() === 'playcanvas') {
                $buildResult = $this->buildPlayCanvasGame($game, $files, $config);
            } else {
                // For Unreal Engine, we might have different build process
                $buildResult = $this->buildUnrealGame($game, $files, $config);
            }

            // Update build with results
            $build->update([
                'status' => 'success',
                'build_url' => $buildResult['url'],
                'assets_manifest' => $buildResult['manifest'],
                'file_count' => $buildResult['file_count'],
                'total_size' => $buildResult['total_size'],
                'build_duration' => now()->diffInSeconds($build->started_at),
                'completed_at' => now(),
            ]);

            // Update game status
            $game->update([
                'build_status' => 'success',
                'published_url' => $buildResult['url'],
            ]);

            Log::info("Build completed successfully for game {$game->id}");

        } catch (\Exception $e) {
            Log::error("Build failed for game {$build->game_id}: " . $e->getMessage());

            $build->update([
                'status' => 'failed',
                'build_log' => $e->getMessage(),
                'build_duration' => now()->diffInSeconds($build->started_at),
                'completed_at' => now(),
            ]);

            $build->game->update([
                'build_status' => 'failed',
                'build_log' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a PlayCanvas game.
     */
    private function buildPlayCanvasGame(Game $game, array $files, array $config): array
    {
        $buildDir = "builds/games/{$game->id}/" . time();
        $manifest = [];
        $totalSize = 0;

        // Process each file
        foreach ($files as $file) {
            $content = $file['content'] ?? '';
            
            // Minify JavaScript files if requested
            if ($config['minify'] && $file['type'] === 'script') {
                $content = $this->minifyJavaScript($content);
            }

            // Store the processed file
            $filePath = "{$buildDir}/{$file['path']}";
            Storage::put($filePath, $content);
            
            $fileSize = strlen($content);
            $totalSize += $fileSize;
            
            $manifest[] = [
                'path' => $file['path'],
                'size' => $fileSize,
                'type' => $file['type'],
                'hash' => md5($content),
            ];
        }

        // Create index.html for the game
        $indexHtml = $this->generatePlayCanvasIndex($game, $manifest, $config);
        Storage::put("{$buildDir}/index.html", $indexHtml);
        $totalSize += strlen($indexHtml);

        // Generate the public URL
        $buildUrl = Storage::url("{$buildDir}/index.html");

        return [
            'url' => $buildUrl,
            'manifest' => $manifest,
            'file_count' => count($files) + 1, // +1 for index.html
            'total_size' => $totalSize,
        ];
    }

    /**
     * Build an Unreal Engine game (placeholder).
     */
    private function buildUnrealGame(Game $game, array $files, array $config): array
    {
        // For Unreal Engine, the build process would be different
        // This is a placeholder implementation
        return [
            'url' => $game->preview_url,
            'manifest' => [],
            'file_count' => count($files),
            'total_size' => 0,
        ];
    }

    /**
     * Generate HTML index file for PlayCanvas game.
     */
    private function generatePlayCanvasIndex(Game $game, array $manifest, array $config): string
    {
        $scripts = array_filter($manifest, fn($file) => $file['type'] === 'script');
        $assets = array_filter($manifest, fn($file) => $file['type'] === 'asset');

        $scriptTags = '';
        foreach ($scripts as $script) {
            $scriptTags .= "<script src=\"{$script['path']}\"></script>\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$game->title}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000;
            overflow: hidden;
        }
        #application-canvas {
            width: 100%;
            height: 100vh;
            display: block;
        }
    </style>
</head>
<body>
    <canvas id="application-canvas"></canvas>
    {$scriptTags}
    <script>
        // Initialize PlayCanvas application
        const canvas = document.getElementById('application-canvas');
        const app = new pc.Application(canvas, {
            mouse: new pc.Mouse(canvas),
            touch: new pc.TouchDevice(canvas),
            keyboard: new pc.Keyboard(window),
        });
        
        app.start();
        
        // Resize handler
        window.addEventListener('resize', () => {
            app.resizeCanvas();
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Minify JavaScript content (basic implementation).
     */
    private function minifyJavaScript(string $content): string
    {
        // Basic minification - remove comments and extra whitespace
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        $content = preg_replace('/\/\/.*$/m', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    /**
     * Get the next version number for the game.
     */
    private function getNextVersion(Game $game): string
    {
        $latestBuild = $game->builds()->where('status', 'success')->first();
        
        if (!$latestBuild) {
            return '1.0.0';
        }

        // Simple version increment (patch version)
        $version = $latestBuild->version;
        $parts = explode('.', $version);
        $parts[2] = (int)$parts[2] + 1;
        
        return implode('.', $parts);
    }

    /**
     * Publish a game (make it publicly accessible).
     */
    public function publishGame(Game $game, array $publishingOptions = []): bool
    {
        try {
            // Ensure the game has a successful build
            if (!$game->hasSuccessfulBuild()) {
                throw new \Exception('Game must have a successful build before publishing');
            }

            // Generate share token if not exists
            if (!$game->share_token) {
                $game->generateShareToken();
            }

            // Update game status and publishing settings
            $game->update([
                'status' => 'published',
                'published_at' => now(),
                'is_public' => $publishingOptions['is_public'] ?? false,
                'sharing_settings' => array_merge([
                    'allow_embedding' => true,
                    'show_controls' => true,
                    'show_info' => true,
                ], $publishingOptions['sharing_settings'] ?? []),
            ]);

            Log::info("Game {$game->id} published successfully");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to publish game {$game->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unpublish a game.
     */
    public function unpublishGame(Game $game): bool
    {
        try {
            $game->update([
                'status' => 'draft',
                'is_public' => false,
                'published_at' => null,
            ]);

            Log::info("Game {$game->id} unpublished successfully");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to unpublish game {$game->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get build history for a game.
     */
    public function getBuildHistory(Game $game, int $limit = 10): array
    {
        return $game->builds()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (GameBuild $build) {
                return [
                    'id' => $build->id,
                    'version' => $build->version,
                    'status' => $build->status,
                    'build_duration' => $build->getBuildDurationFormatted(),
                    'total_size' => $build->getTotalSizeFormatted(),
                    'file_count' => $build->file_count,
                    'created_at' => $build->created_at->toISOString(),
                    'completed_at' => $build->completed_at?->toISOString(),
                ];
            })
            ->toArray();
    }
}