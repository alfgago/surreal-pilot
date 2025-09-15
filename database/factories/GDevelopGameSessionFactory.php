<?php

namespace Database\Factories;

use App\Models\GDevelopGameSession;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GDevelopGameSession>
 */
class GDevelopGameSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GDevelopGameSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'session_id' => Str::uuid()->toString(),
            'game_title' => $this->faker->words(3, true) . ' Game',
            'game_json' => $this->getBasicGameJson(),
            'assets_manifest' => $this->getBasicAssetsManifest(),
            'version' => 1,
            'last_modified' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'preview_url' => null,
            'export_url' => null,
            'status' => 'active',
            'error_log' => null,
        ];
    }

    /**
     * Indicate that the session is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'error_log' => null,
        ]);
    }

    /**
     * Indicate that the session is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'last_modified' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
        ]);
    }

    /**
     * Indicate that the session has an error.
     */
    public function withError(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_log' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the session has a preview URL.
     */
    public function withPreview(): static
    {
        return $this->state(fn (array $attributes) => [
            'preview_url' => 'http://localhost:3000/preview/' . $attributes['session_id'],
        ]);
    }

    /**
     * Indicate that the session has an export URL.
     */
    public function withExport(): static
    {
        return $this->state(fn (array $attributes) => [
            'export_url' => 'http://localhost:3000/export/' . $attributes['session_id'] . '.zip',
        ]);
    }

    /**
     * Create a session that's old enough to be archived.
     */
    public function oldEnoughToArchive(int $days = 8): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'last_modified' => now()->subDays($days),
        ]);
    }

    /**
     * Create a session that's old enough to be cleaned up.
     */
    public function oldEnoughToCleanup(int $days = 31): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'last_modified' => now()->subDays($days),
        ]);
    }

    /**
     * Get a basic GDevelop game JSON structure.
     */
    private function getBasicGameJson(): array
    {
        return [
            'properties' => [
                'name' => $this->faker->words(2, true),
                'description' => $this->faker->sentence(),
                'author' => $this->faker->name(),
                'version' => '1.0.0',
                'orientation' => 'default',
                'sizeOnStartupMode' => 'adaptWidth',
                'adaptGameResolutionAtRuntime' => true,
                'antialiasingMode' => 'MSAA',
                'pixelsRounding' => true,
                'projectUuid' => Str::uuid()->toString(),
            ],
            'resources' => [],
            'objects' => [],
            'objectsGroups' => [],
            'variables' => [],
            'layouts' => [
                [
                    'name' => 'Scene',
                    'mangledName' => 'Scene',
                    'r' => 209,
                    'v' => 209,
                    'b' => 209,
                    'associatedLayout' => '',
                    'standardSortMethod' => true,
                    'stopSoundsOnStartup' => true,
                    'title' => '',
                    'behaviorsSharedData' => [],
                    'objects' => [],
                    'layers' => [
                        [
                            'ambientLightColorB' => 200,
                            'ambientLightColorG' => 200,
                            'ambientLightColorR' => 200,
                            'camera3DFarPlaneDistance' => 10000,
                            'camera3DFieldOfView' => 45,
                            'camera3DNearPlaneDistance' => 3,
                            'cameraType' => '',
                            'followBaseLayerCamera' => false,
                            'isLightingLayer' => false,
                            'isLocked' => false,
                            'name' => '',
                            'renderingType' => '',
                            'visibility' => true,
                            'cameras' => [],
                            'effects' => [],
                        ],
                    ],
                    'events' => [],
                ],
            ],
            'externalEvents' => [],
            'eventsFunctionsExtensions' => [],
            'externalLayouts' => [],
            'externalSourceFiles' => [],
        ];
    }

    /**
     * Get a basic assets manifest.
     */
    private function getBasicAssetsManifest(): array
    {
        return [
            'sprites' => [],
            'sounds' => [],
            'fonts' => [],
            'textures' => [],
            'total_size' => 0,
            'last_updated' => now()->toISOString(),
        ];
    }
}
