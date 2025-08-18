<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => \App\Models\ChatConversation::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant', 'system']),
            'content' => $this->faker->paragraph(),
            'metadata' => $this->faker->optional()->randomElement([
                ['provider' => 'anthropic', 'model' => 'claude-3-sonnet'],
                ['tokens_used' => $this->faker->numberBetween(10, 100)],
                null
            ]),
        ];
    }
}
