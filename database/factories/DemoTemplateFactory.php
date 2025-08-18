<?php

namespace Database\Factories;

use App\Models\DemoTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DemoTemplate>
 */
class DemoTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DemoTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $engineTypes = ['playcanvas', 'unreal'];
        $difficultyLevels = ['beginner', 'intermediate', 'advanced'];
        
        $engineType = $this->faker->randomElement($engineTypes);
        
        return [
            'id' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(10),
            'engine_type' => $engineType,
            'repository_url' => $this->generateRepositoryUrl($engineType),
            'preview_image' => $this->faker->optional(0.7)->imageUrl(400, 300, 'games'),
            'tags' => $this->generateTags($engineType),
            'difficulty_level' => $this->faker->randomElement($difficultyLevels),
            'estimated_setup_time' => $this->faker->numberBetween(60, 1800), // 1 minute to 30 minutes
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
        ];
    }

    /**
     * Indicate that the template is for PlayCanvas.
     */
    public function playcanvas(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine_type' => 'playcanvas',
            'repository_url' => $this->generateRepositoryUrl('playcanvas'),
            'tags' => $this->generateTags('playcanvas'),
        ]);
    }

    /**
     * Indicate that the template is for Unreal Engine.
     */
    public function unreal(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine_type' => 'unreal',
            'repository_url' => $this->generateRepositoryUrl('unreal'),
            'tags' => $this->generateTags('unreal'),
        ]);
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is for beginners.
     */
    public function beginner(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'beginner',
            'estimated_setup_time' => $this->faker->numberBetween(60, 300), // 1-5 minutes
        ]);
    }

    /**
     * Indicate that the template is for intermediate users.
     */
    public function intermediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'intermediate',
            'estimated_setup_time' => $this->faker->numberBetween(300, 900), // 5-15 minutes
        ]);
    }

    /**
     * Indicate that the template is for advanced users.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'advanced',
            'estimated_setup_time' => $this->faker->numberBetween(900, 1800), // 15-30 minutes
        ]);
    }

    /**
     * Create a specific FPS template.
     */
    public function fps(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'FPS Starter Template',
            'description' => 'A complete first-person shooter template with basic mechanics, weapons, and AI enemies.',
            'tags' => ['fps', 'shooter', 'weapons', 'ai'],
            'difficulty_level' => 'intermediate',
        ]);
    }

    /**
     * Create a specific platformer template.
     */
    public function platformer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '2D Platformer Template',
            'description' => 'A classic 2D platformer with jumping mechanics, collectibles, and level progression.',
            'tags' => ['2d', 'platformer', 'jumping', 'collectibles'],
            'difficulty_level' => 'beginner',
        ]);
    }

    /**
     * Create a specific racing template.
     */
    public function racing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Racing Game Template',
            'description' => 'A complete racing game with multiple cars, tracks, and physics-based driving.',
            'tags' => ['racing', 'cars', 'physics', 'tracks'],
            'difficulty_level' => 'advanced',
        ]);
    }

    /**
     * Create a template with a preview image.
     */
    public function withPreviewImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'preview_image' => 'templates/' . $this->faker->uuid . '.jpg',
        ]);
    }

    /**
     * Create a template without a preview image.
     */
    public function withoutPreviewImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'preview_image' => null,
        ]);
    }

    /**
     * Generate a realistic repository URL based on engine type.
     */
    private function generateRepositoryUrl(string $engineType): string
    {
        $providers = ['github.com', 'gitlab.com', 'bitbucket.org'];
        $provider = $this->faker->randomElement($providers);
        
        $username = $this->faker->userName;
        $repoName = $engineType . '-' . $this->faker->word . '-template';
        
        return "https://{$provider}/{$username}/{$repoName}.git";
    }

    /**
     * Generate appropriate tags based on engine type.
     */
    private function generateTags(string $engineType): array
    {
        $commonTags = ['template', 'starter', 'demo'];
        
        $engineSpecificTags = [
            'playcanvas' => ['webgl', 'html5', 'javascript', 'mobile', 'browser'],
            'unreal' => ['blueprint', 'c++', 'ue5', 'ue4', 'desktop', 'console']
        ];
        
        $gameTypeTags = ['fps', '2d', '3d', 'platformer', 'racing', 'puzzle', 'rpg', 'strategy'];
        
        $tags = array_merge(
            $commonTags,
            $this->faker->randomElements($engineSpecificTags[$engineType] ?? [], 2),
            $this->faker->randomElements($gameTypeTags, 2)
        );
        
        return array_unique($tags);
    }
}