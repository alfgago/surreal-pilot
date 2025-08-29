<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => \App\Models\Workspace::factory(),
            'conversation_id' => \App\Models\ChatConversation::factory(),
            'title' => 'Test Game ' . rand(1000, 9999),
            'description' => 'A test game description',
            'preview_url' => 'https://example.com/preview/' . rand(100, 999),
            'published_url' => null,
            'thumbnail_url' => 'https://example.com/thumb/' . rand(100, 999) . '.jpg',
            'metadata' => [
                'engine_type' => 'playcanvas',
                'interaction_count' => 0,
                'thinking_history' => [],
                'game_mechanics' => [],
                'template_id' => 'basic-template'
            ],
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['action', 'adventure'],
            'play_count' => rand(0, 1000),
            'last_played_at' => null,
            'published_at' => now(),
            'is_public' => true,
            'share_token' => null,
            'sharing_settings' => [
                'allow_embedding' => true,
                'show_controls' => true,
                'show_info' => true,
            ],
            'build_status' => 'success',
            'build_log' => null,
            'last_build_at' => now(),
            'deployment_config' => [
                'minify' => true,
                'optimize_assets' => true
            ],
        ];
    }
}
