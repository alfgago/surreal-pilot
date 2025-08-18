<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Workspace;
use App\Services\CreditManager;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishServiceStorageTest extends TestCase
{
    use RefreshDatabase;

    private PublishService $publishService;
    private CreditManager $creditManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->creditManager = $this->createMock(CreditManager::class);
        $this->publishService = new PublishService($this->creditManager);
    }

    public function test_stores_build_artifacts_in_configured_storage()
    {
        // Arrange
        Storage::fake('local');
        Storage::fake('s3');
        
        // Configure to use S3 for builds
        Config::set('workspace.builds_disk', 's3');
        
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        // Create a mock build directory
        $buildPath = storage_path('test_build_' . uniqid());
        mkdir($buildPath, 0755, true);
        file_put_contents($buildPath . '/index.html', '<html><body>Test Game</body></html>');
        file_put_contents($buildPath . '/game.js', 'console.log("test game");');

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->publishService);
        $method = $reflection->getMethod('storeBuildArtifacts');
        $method->setAccessible(true);

        // Act
        $method->invoke($this->publishService, $workspace, $buildPath);

        // Assert
        $workspace->refresh();
        $metadata = $workspace->metadata;
        
        $this->assertArrayHasKey('latest_build_path', $metadata);
        $this->assertArrayHasKey('build_timestamp', $metadata);
        $this->assertEquals('s3', $metadata['build_storage_disk']);
        
        // Verify files were stored in S3
        $buildStoragePath = $metadata['latest_build_path'];
        Storage::disk('s3')->assertExists($buildStoragePath . '/index.html');
        Storage::disk('s3')->assertExists($buildStoragePath . '/game.js');
        
        // Verify content
        $this->assertEquals(
            '<html><body>Test Game</body></html>',
            Storage::disk('s3')->get($buildStoragePath . '/index.html')
        );

        // Cleanup
        $this->deleteDirectory($buildPath);
    }

    public function test_retrieves_build_artifacts_from_storage()
    {
        // Arrange
        Storage::fake('local');
        
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'metadata' => [
                'latest_build_path' => 'builds/test/path',
                'build_timestamp' => now()->toISOString(),
                'build_storage_disk' => 'local'
            ]
        ]);

        // Create mock build files in storage
        Storage::disk('local')->put('builds/test/path/index.html', '<html><body>Stored Game</body></html>');
        Storage::disk('local')->put('builds/test/path/game.js', 'console.log("stored game");');

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->publishService);
        $method = $reflection->getMethod('getBuildArtifactsPath');
        $method->setAccessible(true);

        // Act
        $buildPath = $method->invoke($this->publishService, $workspace);

        // Assert
        $this->assertTrue(is_dir($buildPath));
        $this->assertFileExists($buildPath . '/index.html');
        $this->assertFileExists($buildPath . '/game.js');
        
        $this->assertEquals(
            '<html><body>Stored Game</body></html>',
            file_get_contents($buildPath . '/index.html')
        );
    }

    public function test_workspace_metadata_tracks_build_information()
    {
        // Arrange
        Storage::fake('local');
        
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        // Create a mock build directory
        $buildPath = storage_path('test_build_' . uniqid());
        mkdir($buildPath, 0755, true);
        file_put_contents($buildPath . '/index.html', '<html><body>Test Game</body></html>');

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->publishService);
        $method = $reflection->getMethod('storeBuildArtifacts');
        $method->setAccessible(true);

        // Act
        $method->invoke($this->publishService, $workspace, $buildPath);

        // Assert
        $workspace->refresh();
        $metadata = $workspace->metadata;
        
        $this->assertArrayHasKey('latest_build_path', $metadata);
        $this->assertArrayHasKey('build_timestamp', $metadata);
        $this->assertArrayHasKey('build_storage_disk', $metadata);
        
        $this->assertStringStartsWith('builds/', $metadata['latest_build_path']);
        $this->assertStringContainsString((string)$workspace->company_id, $metadata['latest_build_path']);
        $this->assertStringContainsString((string)$workspace->id, $metadata['latest_build_path']);

        // Cleanup
        $this->deleteDirectory($buildPath);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}