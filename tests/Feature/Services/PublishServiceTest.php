<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Workspace;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishServiceTest extends TestCase
{
    use DatabaseMigrations;

    private PublishService $publishService;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishService = new PublishService();
        $this->company = Company::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        // Mock storage
        Storage::fake('s3');
        Storage::fake('local');
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

    public function test_publish_to_static_with_s3_method()
    {
        // Arrange
        Config::set('services.publishing.method', 's3');
        Config::set('services.aws.cloudfront_domain', 'cdn.example.com');
        
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        $this->mockS3Upload();
        $this->mockCloudFrontInvalidation();

        // Act
        $publishedUrl = $this->publishService->publishToStatic($this->workspace);

        // Assert
        $this->assertStringContainsString('https://cdn.example.com', $publishedUrl);
        $this->workspace->refresh();
        $this->assertEquals('published', $this->workspace->status);
        $this->assertNotEmpty($this->workspace->published_url);
    }

    public function test_publish_to_static_with_github_method()
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
        $publishedUrl = $this->publishService->publishToStatic($this->workspace);

        // Assert
        $this->assertStringContainsString('testuser.github.io/test-repo', $publishedUrl);
        $this->workspace->refresh();
        $this->assertEquals('published', $this->workspace->status);
    }

    public function test_build_project_success()
    {
        // Arrange
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();

        // Act
        $result = $this->publishService->buildProject($this->workspace);

        // Assert
        $this->assertTrue($result);
    }

    public function test_build_project_fails_for_non_playcanvas_workspace()
    {
        // Arrange
        $this->workspace->update(['engine_type' => 'unreal']);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Build process only supports PlayCanvas workspaces');
        
        $this->publishService->buildProject($this->workspace);
    }

    public function test_build_project_fails_when_package_json_missing()
    {
        // Arrange
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}");
        
        // Clean up existing directory if it exists
        if (File::exists($workspacePath)) {
            File::deleteDirectory($workspacePath);
        }
        
        File::makeDirectory($workspacePath, 0755, true);
        // Don't create package.json

        // Act
        $result = $this->publishService->buildProject($this->workspace);

        // Assert
        $this->assertFalse($result);
    }

    public function test_build_project_handles_npm_install_failure()
    {
        // Arrange
        $this->mockWorkspaceFiles();
        
        Process::fake([
            'npm install' => Process::result(
                output: '',
                errorOutput: 'npm install failed',
                exitCode: 1
            )
        ]);

        // Act
        $result = $this->publishService->buildProject($this->workspace);

        // Assert
        $this->assertFalse($result);
    }

    public function test_build_project_handles_npm_build_failure()
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
        $result = $this->publishService->buildProject($this->workspace);

        // Assert
        $this->assertFalse($result);
    }

    public function test_invalidate_cloudfront_success()
    {
        // Arrange
        Config::set('services.aws.cloudfront_distribution_id', 'test-distribution');
        
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['status' => 'success'], 200)
        ]);

        // Act
        $result = $this->publishService->invalidateCloudFront('test-path');

        // Assert
        $this->assertTrue($result);
    }

    public function test_invalidate_cloudfront_skips_when_no_distribution_id()
    {
        // Arrange
        Config::set('services.aws.cloudfront_distribution_id', null);

        // Act
        $result = $this->publishService->invalidateCloudFront('test-path');

        // Assert
        $this->assertTrue($result); // Should return true but skip invalidation
    }

    public function test_invalidate_cloudfront_handles_api_failure()
    {
        // Arrange
        Config::set('services.aws.cloudfront_distribution_id', 'test-distribution');
        
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['error' => 'API Error'], 400)
        ]);

        // Act
        $result = $this->publishService->invalidateCloudFront('test-path');

        // Assert
        $this->assertFalse($result);
    }

    public function test_publish_handles_build_failure()
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

        // Act & Assert
        $this->expectException(\Exception::class);
        
        try {
            $this->publishService->publishToStatic($this->workspace);
        } finally {
            $this->workspace->refresh();
            $this->assertEquals('error', $this->workspace->status);
        }
    }

    public function test_mobile_optimization_adds_viewport_meta()
    {
        // Arrange
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        
        // Create HTML file in build directory
        $buildPath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}/dist");
        File::makeDirectory($buildPath, 0755, true);
        File::put($buildPath . '/index.html', '<html><head></head><body></body></html>');

        // Act
        $this->publishService->buildProject($this->workspace);

        // Assert
        $htmlContent = File::get($buildPath . '/index.html');
        $this->assertStringContains('viewport', $htmlContent);
        $this->assertStringContains('mobile-web-app-capable', $htmlContent);
        $this->assertStringContains('apple-mobile-web-app-capable', $htmlContent);
    }

    public function test_gzip_compression_creates_compressed_files()
    {
        // Arrange
        $this->mockWorkspaceFiles();
        $this->mockBuildProcess();
        
        // Create files to compress
        $buildPath = storage_path("workspaces/{$this->company->id}/{$this->workspace->id}/dist");
        File::makeDirectory($buildPath, 0755, true);
        File::put($buildPath . '/test.js', 'console.log("test");');
        File::put($buildPath . '/test.css', 'body { margin: 0; }');

        // Act
        $this->publishService->buildProject($this->workspace);

        // Assert
        $this->assertFileExists($buildPath . '/test.js.gz');
        $this->assertFileExists($buildPath . '/test.css.gz');
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
        // S3 upload is mocked by Storage::fake('s3')
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