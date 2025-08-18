<?php

namespace Tests\Feature\PlayCanvas;

use App\Models\Company;
use App\Models\Workspace;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiffAssertionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Workspace $workspace;
    private PlayCanvasMcpManager $mcpManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
        ]);
        
        $this->mcpManager = app(PlayCanvasMcpManager::class);
    }

    public function test_can_detect_script_file_changes_after_assistance()
    {
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->name}");
        
        // Mock initial file state
        $initialScript = `
            var PlayerController = pc.createScript('playerController');
            
            PlayerController.prototype.initialize = function() {
                this.jumpHeight = 5;
            };
        `;
        
        $modifiedScript = `
            var PlayerController = pc.createScript('playerController');
            
            PlayerController.prototype.initialize = function() {
                this.jumpHeight = 10; // Increased jump height
            };
        `;
        
        File::shouldReceive('exists')
            ->with($workspacePath . '/src/PlayerController.js')
            ->andReturn(true);
        
        File::shouldReceive('get')
            ->with($workspacePath . '/src/PlayerController.js')
            ->andReturnUsing(function () use (&$initialScript, &$modifiedScript) {
                static $callCount = 0;
                $callCount++;
                return $callCount === 1 ? $initialScript : $modifiedScript;
            });
        
        // Mock MCP response
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'files_modified' => ['src/PlayerController.js'],
                'changes' => ['Increased jumpHeight from 5 to 10']
            ])
        ]);
        
        // Capture initial state
        $initialFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        
        // Simulate assistance operation
        $result = $this->mcpManager->sendCommand($this->workspace, 'Double the jump height');
        
        // Capture final state
        $finalFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        
        // Assert changes were detected
        $diff = $this->mcpManager->generateDiff($initialFiles, $finalFiles);
        
        $this->assertArrayHasKey('src/PlayerController.js', $diff);
        $this->assertStringContainsString('jumpHeight = 10', $diff['src/PlayerController.js']['changes']);
        $this->assertEquals('modified', $diff['src/PlayerController.js']['type']);
    }

    public function test_can_detect_new_file_creation()
    {
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->name}");
        
        File::shouldReceive('exists')
            ->with($workspacePath . '/src/EnemyController.js')
            ->andReturnUsing(function () {
                static $callCount = 0;
                $callCount++;
                return $callCount > 1; // File doesn't exist initially, then exists
            });
        
        File::shouldReceive('get')
            ->with($workspacePath . '/src/EnemyController.js')
            ->andReturn(`
                var EnemyController = pc.createScript('enemyController');
                
                EnemyController.prototype.initialize = function() {
                    this.speed = 2;
                    this.health = 100;
                };
            `);
        
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'files_created' => ['src/EnemyController.js'],
                'changes' => ['Created new enemy controller script']
            ])
        ]);
        
        $initialFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        $this->mcpManager->sendCommand($this->workspace, 'Create an enemy controller');
        $finalFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        
        $diff = $this->mcpManager->generateDiff($initialFiles, $finalFiles);
        
        $this->assertArrayHasKey('src/EnemyController.js', $diff);
        $this->assertEquals('created', $diff['src/EnemyController.js']['type']);
        $this->assertStringContainsString('EnemyController', $diff['src/EnemyController.js']['content']);
    }

    public function test_can_detect_file_deletion()
    {
        $workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->name}");
        
        File::shouldReceive('exists')
            ->with($workspacePath . '/src/OldScript.js')
            ->andReturnUsing(function () {
                static $callCount = 0;
                $callCount++;
                return $callCount === 1; // File exists initially, then deleted
            });
        
        File::shouldReceive('get')
            ->with($workspacePath . '/src/OldScript.js')
            ->andReturn('var OldScript = pc.createScript("oldScript");');
        
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'files_deleted' => ['src/OldScript.js'],
                'changes' => ['Removed unused script file']
            ])
        ]);
        
        $initialFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        $this->mcpManager->sendCommand($this->workspace, 'Remove the old unused script');
        $finalFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        
        $diff = $this->mcpManager->generateDiff($initialFiles, $finalFiles);
        
        $this->assertArrayHasKey('src/OldScript.js', $diff);
        $this->assertEquals('deleted', $diff['src/OldScript.js']['type']);
    }

    public function test_can_generate_comprehensive_change_summary()
    {
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'files_modified' => ['src/Player.js', 'src/Enemy.js'],
                'files_created' => ['src/PowerUp.js'],
                'files_deleted' => ['src/Unused.js'],
                'scene_modified' => true,
                'changes' => [
                    'Enhanced player movement',
                    'Added enemy AI',
                    'Created power-up system',
                    'Removed unused code'
                ]
            ])
        ]);
        
        // Mock multiple file changes
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('mock file content');
        
        $initialFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        $this->mcpManager->sendCommand($this->workspace, 'Implement complete game mechanics');
        $finalFiles = $this->mcpManager->captureWorkspaceState($this->workspace);
        
        $diff = $this->mcpManager->generateDiff($initialFiles, $finalFiles);
        $summary = $this->mcpManager->generateChangeSummary($diff);
        
        $this->assertArrayHasKey('files_modified', $summary);
        $this->assertArrayHasKey('files_created', $summary);
        $this->assertArrayHasKey('files_deleted', $summary);
        $this->assertArrayHasKey('total_changes', $summary);
        
        $this->assertEquals(2, $summary['files_modified']);
        $this->assertEquals(1, $summary['files_created']);
        $this->assertEquals(1, $summary['files_deleted']);
        $this->assertEquals(4, $summary['total_changes']);
    }
}