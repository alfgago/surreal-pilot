<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublishWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company, ['role' => 'developer']);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // Clean up test workspace files
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}");
        if (File::exists($workspacePath)) {
            File::deleteDirectory($workspacePath);
        }

        parent::tearDown();
    }

    public function test_publish_workspace_success_with_s3()
    {
        // Arrange
        Config::set('services.publishing.method', 's3');
        Config::set('services.aws.cloudfront_domain', 'cdn.example.com');
        
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        $this->mockS3Upload();
        $this->mockCloudFrontInvalidation();

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'workspace_id' => $this->workspace->id,
                        'status' => 'published',
                        'mobile_optimized' => true,
                        'compression_enabled' => true
                    ]
                ]);

        $this->assertStringContainsString('https://cdn.example.com', $response->json('data.published_url'));
        
        $this->workspace->refresh();
        $this->assertEquals('published', $this->workspace->status);
        $this->assertNotEmpty($this->workspace->published_url);
    }

    public function test_publish_workspace_success_with_github()
    {
        // Arrange
        Config::set('services.publishing.method', 'github');
        Config::set('services.github.username', 'testuser');
        Config::set('services.github.pages_repo', 'test-repo');
        Config::set('services.github.token', 'test-token');
        
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        $this->mockGitHubPagesUpload();

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'workspace_id' => $this->workspace->id,
                        'status' => 'published'
                    ]
                ]);

        $this->assertStringContainsString('testuser.github.io/test-repo', $response->json('data.published_url'));
    }

    public function test_publish_workspace_validation_errors()
    {
        // Act & Assert - Missing workspace_id
        $response = $this->postJson('/api/workspace/publish', []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['workspace_id']);

        // Act & Assert - Invalid workspace_id
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => 99999
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['workspace_id']);
    }

    public function test_publish_workspace_rejects_non_playcanvas_workspace()
    {
        // Arrange
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready'
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $unrealWorkspace->id
        ]);

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Invalid workspace type',
                    'message' => 'Only PlayCanvas workspaces can be published using this endpoint.'
                ]);
    }

    public function test_publish_workspace_rejects_workspace_not_ready()
    {
        // Arrange
        $this->workspace->update(['status' => 'initializing']);

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Workspace not ready',
                    'message' => 'Workspace must be in ready or published status to be published.',
                    'current_status' => 'initializing'
                ]);
    }

    public function test_publish_workspace_handles_build_failure()
    {
        // Arrange
        $this->mockWorkspaceFiles();
        
        Process::fake([
            'npm install' => Process::result('Dependencies installed'),
            'npm run build' => Process::result(
                output: '',
                errorOutput: 'Build failed',
                exitCode: 1
            )
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'Build failed',
                    'message' => 'The project build process failed. Please check your project configuration.'
                ]);

        $this->workspace->refresh();
        $this->assertEquals('error', $this->workspace->status);
    }

    public function test_publish_workspace_handles_missing_package_json()
    {
        // Arrange - Create workspace directory without package.json
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}");
        File::makeDirectory($workspacePath, 0755, true);

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'Build failed',
                    'message' => 'The project build process failed. Please check your project configuration.'
                ]);
    }

    public function test_publish_workspace_requires_authentication()
    {
        // Arrange - Remove authentication
        $this->app['auth']->forgetGuards();

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_publish_workspace_allows_republishing()
    {
        // Arrange
        $this->workspace->update([
            'status' => 'published',
            'published_url' => 'https://old-url.com'
        ]);
        
        Config::set('services.publishing.method', 's3');
        Config::set('services.aws.cloudfront_domain', 'cdn.example.com');
        
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        $this->mockS3Upload();
        $this->mockCloudFrontInvalidation();

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(200);
        
        $this->workspace->refresh();
        $this->assertNotEquals('https://old-url.com', $this->workspace->published_url);
    }

    public function test_publish_workspace_includes_performance_metrics()
    {
        // Arrange
        Config::set('services.publishing.method', 's3');
        Config::set('services.aws.cloudfront_domain', 'cdn.example.com');
        
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        $this->mockS3Upload();
        $this->mockCloudFrontInvalidation();

        // Act
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $this->workspace->id
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'workspace_id',
                        'published_url',
                        'status',
                        'publish_time',
                        'mobile_optimized',
                        'compression_enabled'
                    ]
                ]);

        $this->assertIsFloat($response->json('data.publish_time'));
        $this->assertGreaterThan(0, $response->json('data.publish_time'));
    }

    private function mockWorkspaceFiles(): void
    {
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}");
        
        // Clean up existing directory if it exists
        if (File::exists($workspacePath)) {
            File::deleteDirectory($workspacePath);
        }
        
        File::makeDirectory($workspacePath, 0755, true);
        File::makeDirectory($workspacePath . '/node_modules', 0755, true);
        
        File::put($workspacePath . '/package.json', json_encode([
            'name' => 'test-project',
            'scripts' => [
                'build' => 'echo "Building..."'
            ]
        ]));
    }

    private function mockBuildProcess(): void
    {
        Process::fake([
            'npm install' => Process::result('Dependencies installed'),
            'npm run build' => Process::result('Build completed')
        ]);

        // Create build output directory
        $buildPath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}/dist");
        if (!File::exists($buildPath)) {
            File::makeDirectory($buildPath, 0755, true);
        }
        File::put($buildPath . '/index.html', '<html><head></head><body><canvas></canvas></body></html>');
    }

    private function mockS3Upload(): void
    {
        // S3 upload is mocked by Storage::fake('s3') in parent setUp
    }

    private function mockCloudFrontInvalidation(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['status' => 'success'], 200)
        ]);
    }

    private function mockGitHubPagesUpload(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['status' => 'success'], 200)
        ]);
    }
}