<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $engineTypes = ['playcanvas', 'unreal'];
        $statuses = ['initializing', 'ready', 'building', 'published', 'error'];
        
        $engineType = $this->faker->randomElement($engineTypes);
        $status = $this->faker->randomElement($statuses);
        
        return [
            'company_id' => Company::factory(),
            'created_by' => \App\Models\User::factory(),
            'name' => $this->faker->words(2, true) . ' Workspace',
            'engine_type' => $engineType,
            'template_id' => $this->faker->slug(2),
            'mcp_port' => $engineType === 'playcanvas' ? $this->faker->numberBetween(3001, 4000) : null,
            'mcp_pid' => $engineType === 'playcanvas' ? $this->faker->numberBetween(1000, 99999) : null,
            'preview_url' => $this->generatePreviewUrl($engineType, $status),
            'published_url' => $status === 'published' ? $this->generatePublishedUrl() : null,
            'status' => $status,
            'metadata' => $this->generateMetadata($engineType, $status),
        ];
    }

    /**
     * Indicate that the workspace is for PlayCanvas.
     */
    public function playcanvas(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine_type' => 'playcanvas',
            'mcp_port' => $this->faker->numberBetween(3001, 4000),
            'mcp_pid' => $this->faker->numberBetween(1000, 99999),
            'preview_url' => $this->generatePreviewUrl('playcanvas', $attributes['status'] ?? 'ready'),
            'metadata' => $this->generateMetadata('playcanvas', $attributes['status'] ?? 'ready'),
        ]);
    }

    /**
     * Indicate that the workspace is for Unreal Engine.
     */
    public function unreal(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine_type' => 'unreal',
            'mcp_port' => null,
            'mcp_pid' => null,
            'preview_url' => $this->generatePreviewUrl('unreal', $attributes['status'] ?? 'ready'),
            'metadata' => $this->generateMetadata('unreal', $attributes['status'] ?? 'ready'),
        ]);
    }

    /**
     * Indicate that the workspace is initializing.
     */
    public function initializing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'initializing',
            'preview_url' => null,
            'published_url' => null,
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', 'initializing'),
                ['setup_started_at' => now()->subMinutes(2)->toISOString()]
            ),
        ]);
    }

    /**
     * Indicate that the workspace is ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready',
            'preview_url' => $this->generatePreviewUrl($attributes['engine_type'] ?? 'playcanvas', 'ready'),
            'published_url' => null,
        ]);
    }

    /**
     * Indicate that the workspace is building.
     */
    public function building(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'building',
            'preview_url' => $this->generatePreviewUrl($attributes['engine_type'] ?? 'playcanvas', 'building'),
            'published_url' => null,
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', 'building'),
                ['build_started_at' => now()->subMinutes(1)->toISOString()]
            ),
        ]);
    }

    /**
     * Indicate that the workspace is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'preview_url' => $this->generatePreviewUrl($attributes['engine_type'] ?? 'playcanvas', 'published'),
            'published_url' => $this->generatePublishedUrl(),
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', 'published'),
                [
                    'published_at' => now()->subHours(1)->toISOString(),
                    'build_version' => 'v' . $this->faker->numberBetween(1, 10)
                ]
            ),
        ]);
    }

    /**
     * Indicate that the workspace has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'preview_url' => null,
            'published_url' => null,
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', 'error'),
                [
                    'error_message' => $this->faker->sentence(),
                    'error_occurred_at' => now()->subMinutes(5)->toISOString()
                ]
            ),
        ]);
    }

    /**
     * Indicate that the workspace has an active MCP server.
     */
    public function withActiveMcpServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'engine_type' => 'playcanvas',
            'mcp_port' => $this->faker->numberBetween(3001, 4000),
            'mcp_pid' => $this->faker->numberBetween(1000, 99999),
            'status' => 'ready',
        ]);
    }

    /**
     * Indicate that the workspace has no MCP server.
     */
    public function withoutMcpServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'mcp_port' => null,
            'mcp_pid' => null,
        ]);
    }

    /**
     * Create a workspace with a specific template.
     */
    public function withTemplate(string $templateId): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => $templateId,
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', $attributes['status'] ?? 'ready'),
                ['template_name' => ucwords(str_replace('-', ' ', $templateId))]
            ),
        ]);
    }

    /**
     * Create a workspace for a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Create a workspace with custom metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge(
                $this->generateMetadata($attributes['engine_type'] ?? 'playcanvas', $attributes['status'] ?? 'ready'),
                $metadata
            ),
        ]);
    }

    /**
     * Generate a preview URL based on engine type and status.
     */
    private function generatePreviewUrl(string $engineType, string $status): ?string
    {
        if (in_array($status, ['initializing', 'error'])) {
            return null;
        }

        if ($engineType === 'playcanvas') {
            $port = $this->faker->numberBetween(3001, 4000);
            $workspaceId = $this->faker->numberBetween(1, 1000);
            return "http://localhost:{$port}/preview/{$workspaceId}";
        }

        // Unreal Engine preview URL format
        $workspaceId = $this->faker->numberBetween(1, 1000);
        return "http://localhost:8080/unreal/preview/{$workspaceId}";
    }

    /**
     * Generate a published URL.
     */
    private function generatePublishedUrl(): string
    {
        $subdomain = $this->faker->slug(2);
        $domain = $this->faker->randomElement(['surrealpilot.com', 'gameprototypes.io', 'playcanvas-builds.com']);
        
        return "https://{$subdomain}.{$domain}";
    }

    /**
     * Generate metadata based on engine type and status.
     */
    private function generateMetadata(string $engineType, string $status): array
    {
        $baseMetadata = [
            'created_from_template' => true,
            'setup_started_at' => now()->subMinutes(10)->toISOString(),
            'setup_completed_at' => now()->subMinutes(8)->toISOString(),
        ];

        $engineMetadata = [
            'playcanvas' => [
                'node_version' => '18.17.0',
                'playcanvas_version' => '1.65.0',
                'build_tool' => 'webpack',
            ],
            'unreal' => [
                'unreal_version' => '5.3.0',
                'build_configuration' => 'Development',
                'target_platform' => 'Win64',
            ]
        ];

        $statusMetadata = [
            'initializing' => [],
            'ready' => [
                'last_health_check' => now()->subMinutes(1)->toISOString(),
                'server_startup_time' => $this->faker->numberBetween(5, 30),
            ],
            'building' => [
                'build_started_at' => now()->subMinutes(2)->toISOString(),
                'build_progress' => $this->faker->numberBetween(10, 90),
            ],
            'published' => [
                'published_at' => now()->subHours(1)->toISOString(),
                'build_size_mb' => $this->faker->numberBetween(50, 500),
                'cdn_cache_status' => 'cached',
            ],
            'error' => [
                'error_message' => $this->faker->sentence(),
                'error_code' => 'E' . $this->faker->numberBetween(1000, 9999),
                'error_occurred_at' => now()->subMinutes(5)->toISOString(),
            ]
        ];

        return array_merge(
            $baseMetadata,
            $engineMetadata[$engineType] ?? [],
            $statusMetadata[$status] ?? []
        );
    }
}