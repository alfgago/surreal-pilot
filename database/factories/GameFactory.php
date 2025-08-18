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
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'preview_url' => $this->faker->optional()->url(),
            'published_url' => $this->faker->optional()->url(),
            'thumbnail_url' => $this->faker->optional()->imageUrl(400, 300, 'games'),
            'metadata' => $this->faker->optional()->randomElement([
                ['engine' => 'playcanvas', 'version' => '1.0.0'],
                ['created_by' => 'ai', 'template' => 'platformer'],
                null
            ]),
        ];
    }
}
