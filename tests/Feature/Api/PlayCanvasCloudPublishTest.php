<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayCanvasCloudPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['credits' => 10.0]);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'developer']);
        
        Sanctum::actingAs($this->user);
    }

    public function test_publish_to_playcanvas_cloud_success()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API response
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'url' => 'https://playcanv.as/test-project/',
                'status' => 'success'
            ], 200)
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id',
            'save_credentials' => true
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'workspace_id' => $workspace->id,
                        'launch_url' => 'https://playcanv.as/test-project/',
                        'status' => 'published',
                        'platform' => 'playcanvas_cloud',
                        'credentials_saved' => true
                    ]
                ]);

        // Verify workspace was updated
        $workspace->refresh();
        $this->assertEquals('published', $workspace->status);
        $this->assertEquals('https://playcanv.as/test-project/', $workspace->published_url);

        // Verify credentials were saved
        $this->company->refresh();
        $this->assertEquals('test-api-key', $this->company->playcanvas_api_key);
        $this->assertEquals('test-project-id', $this->company->playcanvas_project_id);

        // Verify API call was made
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://playcanvas.com/api/apps/test-project-id/publish' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }

    public function test_publish_to_playcanvas_cloud_without_saving_credentials()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API response
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'url' => 'https://playcanv.as/test-project/',
                'status' => 'success'
            ], 200)
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id',
            'save_credentials' => false
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'credentials_saved' => false
                    ]
                ]);

        // Verify credentials were NOT saved
        $this->company->refresh();
        $this->assertNull($this->company->playcanvas_api_key);
        $this->assertNull($this->company->playcanvas_project_id);
    }

    public function test_publish_to_playcanvas_cloud_validation_errors()
    {
        // Test missing workspace_id
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['workspace_id']);

        // Test missing API key
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_project_id' => 'test-project-id'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['playcanvas_api_key']);

        // Test missing project ID
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['playcanvas_project_id']);
    }

    public function test_publish_to_playcanvas_cloud_invalid_workspace_type()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal', // Wrong engine type
            'status' => 'ready'
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Invalid workspace type',
                    'message' => 'Only PlayCanvas workspaces can be published to PlayCanvas cloud.'
                ]);
    }

    public function test_publish_to_playcanvas_cloud_workspace_not_ready()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing' // Not ready
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
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

    public function test_publish_to_playcanvas_cloud_api_error()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $this->mockWorkspaceFileSystem($workspace);

        // Mock PlayCanvas API error
        Http::fake([
            'playcanvas.com/api/apps/*/publish' => Http::response([
                'error' => 'Invalid API key'
            ], 401)
        ]);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'invalid-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'PlayCanvas API error',
                    'message' => 'Failed to publish to PlayCanvas cloud. Please check your API credentials.'
                ]);
    }

    public function test_publish_to_playcanvas_cloud_insufficient_credits()
    {
        // Arrange
        $this->company->update(['credits' => 0.5]); // Not enough credits
        
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $this->mockWorkspaceFileSystem($workspace);

        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => $workspace->id,
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'error' => 'Publish failed'
                ]);
    }

    public function test_publish_to_playcanvas_cloud_workspace_not_found()
    {
        // Act
        $response = $this->postJson('/api/workspace/publish-playcanvas-cloud', [
            'workspace_id' => 99999, // Non-existent workspace
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['workspace_id']);
    }



    private function mockWorkspaceFileSystem(Workspace $workspace): void
    {
        $workspacePath = storage_path("workspaces/{$workspace->company_id}/{$workspace->id}");
        
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