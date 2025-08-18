<?php

namespace Tests\Feature\PlayCanvas;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\Workspace;
use App\Services\TemplateRegistry;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TemplateCloneTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private TemplateRegistry $templateRegistry;
    private WorkspaceService $workspaceService;
    private DemoTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->templateRegistry = app(TemplateRegistry::class);
        $this->workspaceService = app(WorkspaceService::class);
        
        // Create test template
        $this->template = DemoTemplate::factory()->create([
            'id' => 'fps-starter',
            'name' => 'FPS Starter',
            'engine_type' => 'playcanvas',
            'repository_url' => 'https://github.com/playcanvas/fps-starter.git',
            'is_active' => true,
        ]);
    }

    public function test_can_clone_playcanvas_template_successfully()
    {
        // Mock git clone process
        Process::fake([
            'git clone * *' => Process::result(output: 'Cloning into workspace...'),
        ]);
        
        // Mock file operations
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        
        $workspace = $this->workspaceService->createFromTemplate(
            $this->company,
            'fps-starter',
            'playcanvas'
        );
        
        $this->assertInstanceOf(Workspace::class, $workspace);
        $this->assertEquals('playcanvas', $workspace->engine_type);
        $this->assertEquals('fps-starter', $workspace->template_id);
        $this->assertEquals($this->company->id, $workspace->company_id);
        $this->assertEquals('initializing', $workspace->status);
    }

    public function test_template_clone_validates_playcanvas_project_structure()
    {
        $targetPath = storage_path('workspaces/test');
        
        // Mock successful git clone
        Process::fake([
            'git clone * *' => Process::result(output: 'Cloning complete'),
        ]);
        
        // Mock PlayCanvas project files
        File::shouldReceive('exists')
            ->with($targetPath . '/package.json')
            ->andReturn(true);
        File::shouldReceive('exists')
            ->with($targetPath . '/src')
            ->andReturn(true);
        File::shouldReceive('get')
            ->with($targetPath . '/package.json')
            ->andReturn(json_encode([
                'dependencies' => ['playcanvas' => '^1.0.0']
            ]));
        
        $result = $this->templateRegistry->validateTemplate('fps-starter');
        
        $this->assertTrue($result);
    }

    public function test_template_clone_fails_with_invalid_repository()
    {
        $invalidTemplate = DemoTemplate::factory()->create([
            'id' => 'invalid-template',
            'engine_type' => 'playcanvas',
            'repository_url' => 'https://github.com/invalid/repo.git',
            'is_active' => true,
        ]);
        
        // Mock failed git clone
        Process::fake([
            'git clone * *' => Process::result(
                exitCode: 1,
                errorOutput: 'Repository not found'
            ),
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to clone template repository');
        
        $this->workspaceService->createFromTemplate(
            $this->company,
            'invalid-template',
            'playcanvas'
        );
    }

    public function test_template_clone_creates_proper_directory_structure()
    {
        $workspacePath = storage_path("workspaces/{$this->company->id}");
        
        Process::fake([
            'git clone * *' => Process::result(output: 'Cloning complete'),
        ]);
        
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')
            ->with($workspacePath, 0755, true)
            ->once()
            ->andReturn(true);
        
        $workspace = $this->workspaceService->createFromTemplate(
            $this->company,
            'fps-starter',
            'playcanvas'
        );
        
        $this->assertEquals($workspacePath, $workspace->metadata['workspace_path']);
    }

    public function test_can_filter_templates_by_engine_type()
    {
        // Create templates for different engines
        DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);
        DemoTemplate::factory()->create([
            'engine_type' => 'unreal',
            'is_active' => true,
        ]);
        
        $playcanvasTemplates = $this->templateRegistry->getAvailableTemplates('playcanvas');
        $unrealTemplates = $this->templateRegistry->getAvailableTemplates('unreal');
        
        $this->assertCount(2, $playcanvasTemplates); // Including the one from setUp
        $this->assertCount(1, $unrealTemplates);
        
        foreach ($playcanvasTemplates as $template) {
            $this->assertEquals('playcanvas', $template->engine_type);
        }
    }

    public function test_template_clone_handles_concurrent_requests()
    {
        Process::fake([
            'git clone * *' => Process::result(output: 'Cloning complete'),
        ]);
        
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        
        // Create multiple workspaces concurrently
        $workspaces = collect(range(1, 3))->map(function ($i) {
            return $this->workspaceService->createFromTemplate(
                $this->company,
                'fps-starter',
                'playcanvas'
            );
        });
        
        $this->assertCount(3, $workspaces);
        
        // Ensure each workspace has unique name
        $names = $workspaces->pluck('name')->unique();
        $this->assertCount(3, $names);
    }
}