<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Workspace;
use App\Services\CreditManager;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Exception;

class PublishServicePlayCanvasCloudTest extends TestCase
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

    public function test_publish_to_playcanvas_cloud_success()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(true);

        $this->creditManager
            ->expects($this->once())
            ->method('deductCredits')
            ->with(
                $this->equalTo($workspace->company),
                1.0,
                'PlayCanvas cloud publishing',
                $this->callback(function ($metadata) use ($workspace) {
                    return $metadata['workspace_id'] === $workspace->id &&
                           $metadata['engine_type'] === 'playcanvas' &&
                           $metadata['publish_type'] === 'cloud' &&
                           $metadata['mcp_surcharge'] === 0;
                })
            )
            ->willReturn(true);

        // Mock file system
        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API response
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'url' => 'https://playcanv.as/test-project-id/',
                'status' => 'success'
            ], 200)
        ]);

        // Act
        $result = $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);

        // Assert
        $this->assertEquals('https://playcanv.as/test-project-id/', $result);
        
        $workspace->refresh();
        $this->assertEquals('published', $workspace->status);
        $this->assertEquals('https://playcanv.as/test-project-id/', $workspace->published_url);

        // Verify API call was made correctly
        Http::assertSent(function (Request $request) use ($credentials) {
            return $request->url() === "https://playcanvas.com/api/apps/{$credentials['project_id']}/publish" &&
                   $request->hasHeader('Authorization', 'Bearer ' . $credentials['api_key']) &&
                   $request->hasFile('archive');
        });
    }

    public function test_publish_to_playcanvas_cloud_insufficient_credits()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 0.5]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager to return false for affordability
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(false);

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient credits for PlayCanvas cloud publishing');

        $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);
    }

    public function test_publish_to_playcanvas_cloud_invalid_workspace_type()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'unreal', // Wrong engine type
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('PlayCanvas cloud publishing only supports PlayCanvas workspaces');

        $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);
    }

    public function test_publish_to_playcanvas_cloud_missing_credentials()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => '', // Missing API key
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(true);

        // Mock file system
        $this->mockWorkspaceFileSystem($workspace);

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('PlayCanvas API key and Project ID are required');

        $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);
    }

    public function test_publish_to_playcanvas_cloud_api_error()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(true);

        // Mock file system
        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API error response
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'error' => 'Invalid API key'
            ], 401)
        ]);

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('PlayCanvas API error: Invalid API key');

        $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);

        // Verify workspace status was updated to error
        $workspace->refresh();
        $this->assertEquals('error', $workspace->status);
    }

    public function test_publish_to_playcanvas_cloud_build_failure()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(true);

        // Don't mock file system to simulate build failure - workspace directory won't exist

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Build process failed');

        $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);

        // Verify workspace status was updated to error
        $workspace->refresh();
        $this->assertEquals('error', $workspace->status);
    }

    public function test_publish_to_playcanvas_cloud_fallback_url()
    {
        // Arrange
        $company = Company::factory()->create(['credits' => 10.0]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $credentials = [
            'api_key' => 'test-api-key',
            'project_id' => 'test-project-id'
        ];

        // Mock credit manager
        $this->creditManager
            ->expects($this->once())
            ->method('canAffordRequest')
            ->with($this->equalTo($workspace->company), 1.0)
            ->willReturn(true);

        $this->creditManager
            ->expects($this->once())
            ->method('deductCredits')
            ->with($this->equalTo($workspace->company))
            ->willReturn(true);

        // Mock file system
        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API response without URL
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'status' => 'success'
                // No 'url' field
            ], 200)
        ]);

        // Act
        $result = $this->publishService->publishToPlayCanvasCloud($workspace, $credentials);

        // Assert - should use fallback URL
        $this->assertEquals('https://playcanv.as/test-project-id/', $result);
    }

    private function mockWorkspaceFileSystem(Workspace $workspace): void
    {
        $workspacePath = storage_path("workspaces/{$workspace->company_id}/{$workspace->id}");
        
        // Mock package.json
        Storage::fake('local');
        Storage::disk('local')->put(
            "workspaces/{$workspace->company_id}/{$workspace->id}/package.json",
            json_encode([
                'name' => 'test-project',
                'scripts' => ['build' => 'echo "build complete"']
            ])
        );

        // Mock build directory
        Storage::disk('local')->put(
            "workspaces/{$workspace->company_id}/{$workspace->id}/dist/index.html",
            '<html><body>Test Game</body></html>'
        );

        // Create actual directories for the test
        if (!is_dir($workspacePath)) {
            mkdir($workspacePath, 0755, true);
        }
        
        if (!is_dir($workspacePath . '/dist')) {
            mkdir($workspacePath . '/dist', 0755, true);
        }

        file_put_contents($workspacePath . '/package.json', json_encode([
            'name' => 'test-project',
            'scripts' => ['build' => 'echo "build complete"']
        ]));

        file_put_contents($workspacePath . '/dist/index.html', '<html><body>Test Game</body></html>');
    }
}