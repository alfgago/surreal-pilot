<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameBuild>
 */
class GameBuildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        $completedAt = $this->faker->optional(0.8)->dateTimeBetween($startedAt, 'now');
        $buildDuration = $completedAt ? $startedAt->diff($completedAt)->s + ($startedAt->diff($completedAt)->i * 60) : null;

        return [
            'game_id' => \App\Models\Game::factory(),
            'version' => $this->faker->semver(),
            'status' => $this->faker->randomElement(['building', 'success', 'failed']),
            'build_log' => $this->faker->optional()->text(),
            'build_url' => $this->faker->optional()->url(),
            'commit_hash' => $this->faker->optional()->sha1(),
            'build_config' => $this->faker->optional()->randomElement([
                ['minify' => true, 'optimize_assets' => true, 'include_debug' => false],
                ['minify' => false, 'optimize_assets' => false, 'include_debug' => true],
                null
            ]),
            'assets_manifest' => $this->faker->optional()->randomElement([
                [
                    ['path' => 'main.js', 'size' => 15420, 'type' => 'script', 'hash' => 'abc123'],
                    ['path' => 'style.css', 'size' => 8340, 'type' => 'asset', 'hash' => 'def456'],
                ],
                [
                    ['path' => 'game.js', 'size' => 25600, 'type' => 'script', 'hash' => 'ghi789'],
                ],
                null
            ]),
            'file_count' => $this->faker->numberBetween(1, 50),
            'total_size' => $this->faker->numberBetween(10000, 5000000), // 10KB to 5MB
            'build_duration' => $buildDuration,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ];
    }

    /**
     * Indicate that the build is currently in progress.
     */
    public function building(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'building',
            'completed_at' => null,
            'build_duration' => null,
            'build_url' => null,
        ]);
    }

    /**
     * Indicate that the build was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'build_url' => $this->faker->url(),
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
        ]);
    }

    /**
     * Indicate that the build failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'build_log' => 'Build failed: ' . $this->faker->sentence(),
            'build_url' => null,
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
        ]);
    }
}