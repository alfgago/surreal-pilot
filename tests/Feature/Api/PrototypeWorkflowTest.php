<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlayCanvasMcpManager;
use App\Services\TemplateRegistry;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PrototypeWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;
    private DemoTemplate $playcanvasTemplate;
    private DemoTemplate $unrealTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);
        
        // Attach user to company with developer role
        $this->user->companies()->attach($this->company->id, ['role' => 'developer']);

        // Create test templates
        $this->playcanvasTemplate = DemoTemplate::factory()->create([
            'id' => 'playcanvas-fps',
            'name' => 'FPS Starter',
            'engine_type' => 'playcanvas',
            'is_active' => true,
            'repository_url' => 'https://github.com/test/playcanvas-fps.git',
            'difficulty_level' => 'beginner',
            'estimated_setup_time' => 300,
        ]);

        $this->unrealTemplate = DemoTemplate::factory()->create([
            'id' => 'unreal-fps',
            'name' => 'Unreal FPS',
            'engine_type' => 'unreal',
            'is_active' => true,
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_get_available_playcanvas_demos()
    {
        // Create additional PlayCanvas templates
        DemoTemplate::factory()->create([
            'id' => 'playcanvas-platformer',
            'name' => '2D Platformer',
            'engine_type' => 'playcanvas',
            'is_active' => true,
            'difficulty_level' => 'intermediate',
        ]);

        DemoTemplate::factory()->create([
            'id' => 'playcanvas-inactive',
            'name' => 'Inactive Template',
            'engine_type' => 'playcanvas',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/demos');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'templates' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'description',
                                 'preview_image',
                                 'tags',
                                 'difficulty_level',
                                 'estimated_setup_time',
                             ]
                         ],
                         'total_count'
                     ]
                 ]);

        $data = $response->json('data');
        
        // Should only return active PlayCanvas templates
        $this->assertEquals(2, $data['total_count']);
        
        $templateIds = collect($data['templates'])->pluck('id')->toArray();
        $this->assertContains('playcanvas-fps', $templateIds);
        $this->assertContains('playcanvas-platformer', $templateIds);
        $this->assertNotContains('unreal-fps', $templateIds);
        $this->assertNotContains('playcanvas-inactive', $templateIds);
    }

    /** @test */
    public function it_can_create_prototype_from_demo_template()
    {
        // Mock the services to avoid actual file operations
        $this->mockTemplateRegistry();
        $this->mockWorkspaceService();
        $this->mockPlayCanvasMcpManager();

        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->playcanvasTemplate->id,
            'company_id' => $this->company->id,
            'name' => 'My Test Game'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'workspace_id',
                         'preview_url',
                         'name',
                         'status',
                         'template' => [
                             'id',
                             'name'
                         ],
                         'creation_time'
                     ]
                 ]);

        $data = $response->json('data');
        $this->assertTrue($response->json('success'));
        $this->assertEquals('My Test Game', $data['name']);
        $this->assertEquals('ready', $data['status']);
        $this->assertEquals($this->playcanvasTemplate->id, $data['template']['id']);
        $this->assertIsNumeric($data['creation_time']);

        // Verify workspace was created in database
        $this->assertDatabaseHas('workspaces', [
            'company_id' => $this->company->id,
            'name' => 'My Test Game',
            'engine_type' => 'playcanvas',
            'template_id' => $this->playcanvasTemplate->id,
        ]);
    }

    /** @test */
    public function it_validates_prototype_creation_request()
    {
        $testCases = [
            // Missing demo_id
            [
                'data' => ['company_id' => $this->company->id],
                'expectedErrors' => ['demo_id']
            ],
            // Missing company_id
            [
                'data' => ['demo_id' => $this->playcanvasTemplate->id],
                'expectedErrors' => ['company_id']
            ],
            // Invalid demo_id
            [
                'data' => [
                    'demo_id' => 'non-existent',
                    'company_id' => $this->company->id
                ],
                'expectedErrors' => ['demo_id']
            ],
            // Invalid company_id
            [
                'data' => [
                    'demo_id' => $this->playcanvasTemplate->id,
                    'company_id' => 99999
                ],
                'expectedErrors' => ['company_id']
            ],
            // Name too long
            [
                'data' => [
                    'demo_id' => $this->playcanvasTemplate->id,
                    'company_id' => $this->company->id,
                    'name' => str_repeat('a', 256)
                ],
                'expectedErrors' => ['name']
            ]
        ];

        foreach ($testCases as $testCase) {
            $response = $this->postJson('/api/prototype', $testCase['data']);
            
            $response->assertStatus(422)
                     ->assertJsonValidationErrors($testCase['expectedErrors']);
        }
    }

    /** @test */
    public function it_rejects_non_playcanvas_templates_for_prototype_creation()
    {
        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->unrealTemplate->id,
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'error' => 'Invalid template',
                     'message' => 'The specified template is not available or not a PlayCanvas template.'
                 ]);
    }

    /** @test */
    public function it_rejects_inactive_templates_for_prototype_creation()
    {
        $inactiveTemplate = DemoTemplate::factory()->create([
            'id' => 'inactive-template',
            'engine_type' => 'playcanvas',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/prototype', [
            'demo_id' => $inactiveTemplate->id,
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'error' => 'Invalid template'
                 ]);
    }

    /** @test */
    public function it_handles_prototype_creation_timeout()
    {
        // Mock workspace service to simulate slow creation
        $mockWorkspaceService = Mockery::mock(WorkspaceService::class);
        $mockWorkspaceService->shouldReceive('createFromTemplate')
                            ->andReturnUsing(function () {
                                // Simulate slow operation
                                sleep(2);
                                throw new \Exception('timeout');
                            });

        $this->app->instance(WorkspaceService::class, $mockWorkspaceService);

        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->playcanvasTemplate->id,
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(408)
                 ->assertJson([
                     'success' => false,
                     'error' => 'Creation timeout'
                 ]);
    }

    /** @test */
    public function it_can_get_workspace_status()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview/1',
            'metadata' => ['test' => 'data']
        ]);

        // Mock MCP manager for health check
        $this->mockPlayCanvasMcpManager();

        $response = $this->getJson("/api/workspace/{$workspace->id}/status");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'workspace_id',
                         'name',
                         'status',
                         'engine_type',
                         'preview_url',
                         'published_url',
                         'created_at',
                         'updated_at',
                         'mcp_server' => [
                             'port',
                             'running',
                             'server_url',
                             'health_status'
                         ],
                         'metadata'
                     ]
                 ]);

        $data = $response->json('data');
        $this->assertEquals($workspace->id, $data['workspace_id']);
        $this->assertEquals('playcanvas', $data['engine_type']);
        $this->assertEquals('ready', $data['status']);
        $this->assertEquals(3001, $data['mcp_server']['port']);
        $this->assertTrue($data['mcp_server']['running']);
        $this->assertEquals('running', $data['mcp_server']['health_status']);
    }

    /** @test */
    public function it_handles_non_existent_workspace_status_request()
    {
        $response = $this->getJson('/api/workspace/99999/status');

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'error' => 'Workspace not found'
                 ]);
    }

    /** @test */
    public function it_can_get_workspace_statistics()
    {
        // Create test workspaces
        Workspace::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        Workspace::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'published'
        ]);

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'error'
        ]);

        $response = $this->getJson('/api/workspaces/stats?company_id=' . $this->company->id);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total',
                         'by_engine' => [
                             'playcanvas',
                             'unreal'
                         ],
                         'by_status' => [
                             'initializing',
                             'ready',
                             'building',
                             'published',
                             'error'
                         ],
                         'active_mcp_servers'
                     ]
                 ]);

        $data = $response->json('data');
        $this->assertEquals(6, $data['total']);
        $this->assertEquals(4, $data['by_engine']['playcanvas']);
        $this->assertEquals(2, $data['by_engine']['unreal']);
        $this->assertEquals(3, $data['by_status']['ready']);
        $this->assertEquals(2, $data['by_status']['published']);
        $this->assertEquals(1, $data['by_status']['error']);
    }

    /** @test */
    public function it_can_list_workspaces_with_filtering()
    {
        // Create test workspaces
        $playcanvasWorkspaces = Workspace::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $unrealWorkspaces = Workspace::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'published'
        ]);

        // Test filtering by engine type
        $response = $this->getJson('/api/workspaces?' . http_build_query([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'limit' => 10
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(3, count($data['workspaces']));
        $this->assertEquals(3, $data['pagination']['total']);
        
        foreach ($data['workspaces'] as $workspace) {
            $this->assertEquals('playcanvas', $workspace['engine_type']);
        }

        // Test filtering by status
        $response = $this->getJson('/api/workspaces?' . http_build_query([
            'company_id' => $this->company->id,
            'status' => 'published',
            'limit' => 10
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(2, count($data['workspaces']));
        foreach ($data['workspaces'] as $workspace) {
            $this->assertEquals('published', $workspace['status']);
        }

        // Test pagination
        $response = $this->getJson('/api/workspaces?' . http_build_query([
            'company_id' => $this->company->id,
            'limit' => 2,
            'offset' => 0
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(2, count($data['workspaces']));
        $this->assertEquals(5, $data['pagination']['total']);
        $this->assertTrue($data['pagination']['has_more']);
    }

    /** @test */
    public function it_validates_workspace_listing_parameters()
    {
        $testCases = [
            // Missing company_id
            [
                'params' => [],
                'expectedErrors' => ['company_id']
            ],
            // Invalid engine_type
            [
                'params' => [
                    'company_id' => $this->company->id,
                    'engine_type' => 'invalid'
                ],
                'expectedErrors' => ['engine_type']
            ],
            // Invalid status
            [
                'params' => [
                    'company_id' => $this->company->id,
                    'status' => 'invalid'
                ],
                'expectedErrors' => ['status']
            ],
            // Invalid limit
            [
                'params' => [
                    'company_id' => $this->company->id,
                    'limit' => 101
                ],
                'expectedErrors' => ['limit']
            ]
        ];

        foreach ($testCases as $testCase) {
            $response = $this->getJson('/api/workspaces?' . http_build_query($testCase['params']));
            
            $response->assertStatus(422)
                     ->assertJsonValidationErrors($testCase['expectedErrors']);
        }
    }

    /** @test */
    public function it_uses_database_transactions_for_prototype_creation()
    {
        // Mock workspace service to throw exception after partial creation
        $mockWorkspaceService = Mockery::mock(WorkspaceService::class);
        $mockWorkspaceService->shouldReceive('createFromTemplate')
                            ->andThrow(new \Exception('Simulated failure'));

        $this->app->instance(WorkspaceService::class, $mockWorkspaceService);

        $initialWorkspaceCount = Workspace::count();

        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->playcanvasTemplate->id,
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(500);

        // Verify no workspace was created due to transaction rollback
        $this->assertEquals($initialWorkspaceCount, Workspace::count());
    }

    /** @test */
    public function it_logs_prototype_creation_performance()
    {
        Log::shouldReceive('info')
           ->with('Prototype created successfully', Mockery::type('array'))
           ->once();

        Log::shouldReceive('warning')
           ->with('Prototype creation exceeded 15-second timeout', Mockery::type('array'))
           ->never(); // Should not warn for fast creation

        $this->mockTemplateRegistry();
        $this->mockWorkspaceService();
        $this->mockPlayCanvasMcpManager();

        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->playcanvasTemplate->id,
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(201);
    }



    /**
     * Mock the TemplateRegistry service.
     */
    private function mockTemplateRegistry(): void
    {
        $mockTemplateRegistry = Mockery::mock(TemplateRegistry::class);
        $mockTemplateRegistry->shouldReceive('getPlayCanvasTemplates')
                            ->andReturn(collect([$this->playcanvasTemplate]));
        $mockTemplateRegistry->shouldReceive('cloneTemplate')
                            ->andReturn(true);

        $this->app->instance(TemplateRegistry::class, $mockTemplateRegistry);
    }

    /**
     * Mock the WorkspaceService.
     */
    private function mockWorkspaceService(): void
    {
        $mockWorkspaceService = Mockery::mock(WorkspaceService::class);
        $mockWorkspaceService->shouldReceive('createFromTemplate')
                            ->andReturnUsing(function ($company, $templateId, $engineType, $name) {
                                return Workspace::factory()->create([
                                    'company_id' => $company->id,
                                    'name' => $name ?: 'Test Workspace',
                                    'engine_type' => $engineType,
                                    'template_id' => $templateId,
                                    'status' => 'ready',
                                    'preview_url' => 'http://localhost:3001/preview/test',
                                ]);
                            });

        $mockWorkspaceService->shouldReceive('getWorkspaceStats')
                            ->andReturn([
                                'total' => 0,
                                'by_engine' => ['playcanvas' => 0, 'unreal' => 0],
                                'by_status' => [
                                    'initializing' => 0,
                                    'ready' => 0,
                                    'building' => 0,
                                    'published' => 0,
                                    'error' => 0
                                ],
                                'active_mcp_servers' => 0
                            ]);

        $this->app->instance(WorkspaceService::class, $mockWorkspaceService);
    }

    /**
     * Mock the PlayCanvasMcpManager.
     */
    private function mockPlayCanvasMcpManager(): void
    {
        $mockMcpManager = Mockery::mock(PlayCanvasMcpManager::class);
        $mockMcpManager->shouldReceive('getServerStatus')
                      ->andReturn('running');

        $this->app->instance(PlayCanvasMcpManager::class, $mockMcpManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}