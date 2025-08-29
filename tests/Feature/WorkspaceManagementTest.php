<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\DemoTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user with engine selection
        $this->testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'selected_engine_type' => 'playcanvas',
        ]);

        // Create a company for the test user
        $this->testCompany = Company::factory()->create([
            'name' => 'Test Company',
            'user_id' => $this->testUser->id,
            'credits' => 1000,
            'plan' => 'starter',
            'monthly_credit_limit' => 1000,
            'personal_company' => true,
        ]);

        $this->testUser->update(['current_company_id' => $this->testCompany->id]);
        $this->testCompany->users()->attach($this->testUser->id, ['role' => 'owner']);
    }

    public function test_engine_selection_page_can_be_rendered(): void
    {
        // Clear engine selection to test the page
        $this->testUser->update(['selected_engine_type' => null]);
        
        $response = $this->actingAs($this->testUser)->get('/engine-selection');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('EngineSelection')
                ->has('engines')
        );
    }

    public function test_user_can_select_engine(): void
    {
        // Clear engine selection first
        $this->testUser->update(['selected_engine_type' => null]);
        
        $response = $this->actingAs($this->testUser)->post('/engine-selection', [
            'engine_type' => 'playcanvas',
        ]);

        $response->assertRedirect('/workspace-selection');
        
        // Verify engine was saved
        $this->testUser->refresh();
        $this->assertEquals('playcanvas', $this->testUser->selected_engine_type);
    }

    public function test_workspace_selection_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->testUser)->get('/workspace-selection');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('WorkspaceSelection')
                ->has('engineInfo')
                ->has('workspaces')
                ->has('engineType')
        );
    }

    public function test_user_can_create_workspace(): void
    {
        $response = $this->actingAs($this->testUser)->post('/workspace-selection/create', [
            'name' => 'My Test Workspace',
        ]);

        $response->assertRedirect('/chat');
        
        // Verify workspace was created
        $this->assertDatabaseHas('workspaces', [
            'name' => 'My Test Workspace',
            'company_id' => $this->testCompany->id,
            'engine_type' => 'playcanvas',
        ]);
        
        // Verify workspace is selected in session
        $workspace = Workspace::where('name', 'My Test Workspace')->first();
        $this->assertEquals($workspace->id, session('selected_workspace_id'));
    }

    public function test_user_can_select_existing_workspace(): void
    {
        // Create a workspace first
        $workspace = Workspace::factory()->create([
            'company_id' => $this->testCompany->id,
            'engine_type' => 'playcanvas',
            'name' => 'Existing Workspace',
        ]);

        $response = $this->actingAs($this->testUser)->post('/workspace-selection', [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertRedirect('/chat');
        
        // Verify workspace is selected in session
        $this->assertEquals($workspace->id, session('selected_workspace_id'));
    }

    public function test_workspace_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->testUser)->post('/workspace-selection/create', [
            'name' => '', // Empty name should fail validation
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_user_cannot_select_workspace_from_different_company(): void
    {
        // Create another company and workspace
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'playcanvas',
        ]);

        $response = $this->actingAs($this->testUser)->post('/workspace-selection', [
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_templates_endpoint_returns_correct_data(): void
    {
        // Create a template for the current engine
        $template = DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
            'is_active' => true,
            'name' => 'Test Template',
        ]);

        $response = $this->actingAs($this->testUser)->get('/workspace-selection/templates');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'templates' => [
                [
                    'id' => $template->id,
                    'name' => 'Test Template',
                ]
            ],
        ]);
    }

    public function test_user_without_engine_selection_redirected_to_engine_selection(): void
    {
        // Clear engine selection
        $this->testUser->update(['selected_engine_type' => null]);
        
        $response = $this->actingAs($this->testUser)->get('/workspace-selection');

        $response->assertRedirect('/engine-selection');
    }

    public function test_user_with_engine_but_no_workspace_redirected_to_workspace_selection(): void
    {
        // Clear any workspace session
        session()->forget('selected_workspace_id');
        
        $response = $this->actingAs($this->testUser)->get('/');

        $response->assertRedirect('/workspace-selection');
    }
}