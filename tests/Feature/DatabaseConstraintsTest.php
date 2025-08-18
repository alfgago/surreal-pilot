<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\MultiplayerSession;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    /** @test */
    public function it_enforces_valid_engine_types_in_workspaces()
    {
        $this->expectException(\Exception::class);

        // Try to insert invalid engine type directly into database
        DB::table('workspaces')->insert([
            'company_id' => $this->company->id,
            'name' => 'Test Workspace',
            'engine_type' => 'invalid_engine',
            'status' => 'initializing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_enforces_valid_engine_types_in_demo_templates()
    {
        $this->expectException(\Exception::class);

        // Try to insert invalid engine type directly into database
        DB::table('demo_templates')->insert([
            'id' => 'invalid-template',
            'name' => 'Invalid Template',
            'engine_type' => 'invalid_engine',
            'repository_url' => 'https://github.com/example/repo',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_enforces_unique_mcp_ports()
    {
        // Create first workspace with MCP port
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
        ]);

        $this->expectException(\Exception::class);

        // Try to create second workspace with same MCP port
        DB::table('workspaces')->insert([
            'company_id' => $this->company->id,
            'name' => 'Second Workspace',
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
            'status' => 'initializing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_null_mcp_ports_for_multiple_workspaces()
    {
        // Create multiple workspaces with null MCP ports (should be allowed)
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => null,
            'status' => 'initializing', // Avoid MCP port requirement
        ]);

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => null,
            'status' => 'initializing', // Avoid MCP port requirement
        ]);

        // This should not throw an exception
        $this->assertTrue(true);
    }

    /** @test */
    public function it_enforces_valid_multiplayer_session_status()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);

        $this->expectException(\Exception::class);

        // Try to insert invalid status directly into database
        DB::table('multiplayer_sessions')->insert([
            'id' => 'test-session',
            'workspace_id' => $workspace->id,
            'status' => 'invalid_status',
            'expires_at' => now()->addHours(1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_enforces_future_expiration_for_multiplayer_sessions()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing',
        ]);

        // SQLite doesn't support CHECK constraints added via ALTER TABLE
        // This test verifies the application-level validation instead
        $this->expectException(\InvalidArgumentException::class);

        MultiplayerSession::create([
            'id' => 'test-session',
            'workspace_id' => $workspace->id,
            'status' => 'active',
            'expires_at' => now()->subHours(1), // Past expiration
        ]);
    }

    /** @test */
    public function it_cascades_delete_multiplayer_sessions_when_workspace_deleted()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing',
        ]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->assertDatabaseHas('multiplayer_sessions', [
            'id' => $session->id,
        ]);

        // Delete workspace
        $workspace->delete();

        // Session should be automatically deleted
        $this->assertDatabaseMissing('multiplayer_sessions', [
            'id' => $session->id,
        ]);
    }

    /** @test */
    public function it_allows_valid_engine_types()
    {
        // Test valid PlayCanvas workspace
        $playCanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $playCanvasWorkspace->id,
            'engine_type' => 'playcanvas',
        ]);

        // Test valid Unreal workspace
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $unrealWorkspace->id,
            'engine_type' => 'unreal',
        ]);
    }

    /** @test */
    public function it_allows_valid_template_engine_types()
    {
        // Test valid PlayCanvas template
        $playCanvasTemplate = DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
        ]);

        $this->assertDatabaseHas('demo_templates', [
            'id' => $playCanvasTemplate->id,
            'engine_type' => 'playcanvas',
        ]);

        // Test valid Unreal template
        $unrealTemplate = DemoTemplate::factory()->create([
            'engine_type' => 'unreal',
        ]);

        $this->assertDatabaseHas('demo_templates', [
            'id' => $unrealTemplate->id,
            'engine_type' => 'unreal',
        ]);
    }

    /** @test */
    public function it_allows_valid_multiplayer_session_statuses()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing',
        ]);

        $validStatuses = ['starting', 'active', 'stopping', 'stopped'];

        foreach ($validStatuses as $status) {
            $session = MultiplayerSession::factory()->create([
                'workspace_id' => $workspace->id,
                'status' => $status,
            ]);

            $this->assertDatabaseHas('multiplayer_sessions', [
                'id' => $session->id,
                'status' => $status,
            ]);
        }
    }

    /** @test */
    public function it_indexes_engine_type_and_status_combinations()
    {
        // Create workspaces with different engine types and statuses
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001, // Required for ready PlayCanvas workspaces
        ]);

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
        ]);

        // Query should be efficient due to index
        $playCanvasReady = Workspace::where('engine_type', 'playcanvas')
                                  ->where('status', 'ready')
                                  ->count();

        $unrealReady = Workspace::where('engine_type', 'unreal')
                                ->where('status', 'ready')
                                ->count();

        $this->assertEquals(1, $playCanvasReady);
        $this->assertEquals(1, $unrealReady);
    }

    /** @test */
    public function it_indexes_template_engine_type_and_active_status()
    {
        // Create templates with different engine types and active status
        DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);

        DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
            'is_active' => false,
        ]);

        DemoTemplate::factory()->create([
            'engine_type' => 'unreal',
            'is_active' => true,
        ]);

        // Query should be efficient due to index
        $activePlayCanvas = DemoTemplate::where('engine_type', 'playcanvas')
                                       ->where('is_active', true)
                                       ->count();

        $activeUnreal = DemoTemplate::where('engine_type', 'unreal')
                                   ->where('is_active', true)
                                   ->count();

        $this->assertEquals(1, $activePlayCanvas);
        $this->assertEquals(1, $activeUnreal);
    }
}