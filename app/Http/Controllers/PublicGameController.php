<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicGameController extends Controller
{
    /**
     * Show a shared game by its share token.
     */
    public function showSharedGame(string $shareToken): Response
    {
        $game = Game::where('share_token', $shareToken)
            ->where('is_public', true)
            ->where('status', 'published')
            ->firstOrFail();

        // Increment play count
        $game->incrementPlayCount();

        return Inertia::render('Games/Shared', [
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'description' => $game->description,
                'engine' => $game->getEngineType(),
                'status' => $game->status,
                'thumbnail_url' => $game->thumbnail_url,
                'published_url' => $game->published_url,
                'preview_url' => $game->preview_url,
                'created_at' => $game->created_at->toISOString(),
                'updated_at' => $game->updated_at->toISOString(),
                'metadata' => [
                    'version' => $game->version ?? '1.0.0',
                    'tags' => $game->tags ?? [],
                    'play_count' => $game->play_count ?? 0,
                    'last_played' => $game->last_played_at?->toISOString(),
                ],
                'sharing_settings' => $game->sharing_settings ?? [
                    'allow_embedding' => true,
                    'show_controls' => true,
                    'show_info' => true,
                ],
            ],
            'workspace' => [
                'name' => $game->workspace->name,
                'engine_type' => $game->workspace->engine_type,
            ],
        ]);
    }

    /**
     * Show an embedded game by its share token.
     */
    public function embedGame(string $shareToken): Response
    {
        $game = Game::where('share_token', $shareToken)
            ->where('is_public', true)
            ->where('status', 'published')
            ->firstOrFail();

        // Check if embedding is allowed
        $sharingSettings = $game->sharing_settings ?? [];
        if (!($sharingSettings['allow_embedding'] ?? true)) {
            abort(403, 'Embedding is not allowed for this game');
        }

        // Increment play count
        $game->incrementPlayCount();

        return Inertia::render('Games/Embed', [
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'description' => $game->description,
                'engine' => $game->getEngineType(),
                'published_url' => $game->published_url,
                'preview_url' => $game->preview_url,
                'metadata' => [
                    'version' => $game->version ?? '1.0.0',
                    'tags' => $game->tags ?? [],
                ],
                'sharing_settings' => $sharingSettings,
            ],
        ]);
    }
}