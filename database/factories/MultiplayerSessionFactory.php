<?php

namespace Database\Factories;

use App\Models\MultiplayerSession;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MultiplayerSession>
 */
class MultiplayerSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MultiplayerSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxPlayers = $this->faker->numberBetween(2, 16);
        
        return [
            'id' => Str::uuid()->toString(),
            'workspace_id' => Workspace::factory(),
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/' . Str::random(32),
            'ngrok_url' => 'https://' . Str::random(8) . '.ngrok.io',
            'session_url' => 'https://' . Str::random(8) . '.ngrok.io',
            'status' => $this->faker->randomElement(['starting', 'active', 'stopping', 'stopped']),
            'max_players' => $maxPlayers,
            'current_players' => $this->faker->numberBetween(0, $maxPlayers), // Ensure current <= max
            'expires_at' => Carbon::now()->addMinutes($this->faker->numberBetween(10, 60)),
        ];
    }

    /**
     * Indicate that the session is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes($this->faker->numberBetween(10, 60)),
        ]);
    }

    /**
     * Indicate that the session is stopped.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'stopped',
        ]);
    }

    /**
     * Indicate that the session is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes($this->faker->numberBetween(1, 60)),
        ]);
    }

    /**
     * Indicate that the session is starting.
     */
    public function starting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'starting',
            'fargate_task_arn' => null,
            'ngrok_url' => null,
            'session_url' => null,
        ]);
    }

    /**
     * Indicate that the session is stopping.
     */
    public function stopping(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'stopping',
        ]);
    }

    /**
     * Set specific max players.
     */
    public function withMaxPlayers(int $maxPlayers): static
    {
        return $this->state(fn (array $attributes) => [
            'max_players' => $maxPlayers,
            'current_players' => $this->faker->numberBetween(0, $maxPlayers),
        ]);
    }

    /**
     * Set specific current players.
     */
    public function withCurrentPlayers(int $currentPlayers): static
    {
        return $this->state(fn (array $attributes) => [
            'current_players' => $currentPlayers,
        ]);
    }

    /**
     * Set specific expiration time.
     */
    public function expiresIn(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->addMinutes($minutes),
        ]);
    }

    /**
     * Set specific expiration time in the past.
     */
    public function expiredMinutesAgo(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subMinutes($minutes),
        ]);
    }

    /**
     * Set a specific workspace.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set session as full (current_players = max_players).
     */
    public function full(): static
    {
        return $this->state(function (array $attributes) {
            $maxPlayers = $attributes['max_players'] ?? 8;
            return [
                'current_players' => $maxPlayers,
            ];
        });
    }

    /**
     * Set session as empty (current_players = 0).
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_players' => 0,
        ]);
    }
}