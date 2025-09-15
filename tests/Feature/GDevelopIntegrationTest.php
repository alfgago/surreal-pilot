<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditManager;
use App\Services\EngineSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GDevelopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'credits' => 1000,
            'plan' => 'pro'
        ]);
        
        $this->user->companies()->attach($this->company, ['role' => 'owner']);
        $this->user->update(['current_company_id' => $this->company->id]);

        // Create GDevelop workspace
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'name' => 'Test GDevelop Workspace',
            'engine_type' => 'gdevelop',
            'status' => 'ready'
        ]);
    }

    public function test_gdevelop_engine_is_available_in_engine_selection()
    {
        $engineService = app(EngineSelectionService::class);
        $engines = $engineService->getAvailableEngines();

        $this->assertArrayHasKey('gdevelop', $engines);
        $this->assertEquals('GDevelop', $engines['gdevelop']['name']);
        $this->assertStringContainsString('No-code game development', $engines['gdevelop']['description']);
    }

    public function test_gdevelop_workspace_can_be_created()
    {
        $this->actingAs($this->user);

        $response = $this->post('/workspaces', [
            'name' => 'New GDevelop Game',
            'engine_type' => 'gdevelop',
            'template_id' => 'platformer'
        ]);

        $response->assertRedirect('/chat');
        
        $this->assertDatabaseHas('workspaces', [
            'name' => 'New GDevelop Game',
            'engine_type' => 'gdevelop',
            'company_id' => $this->company->id
        ]);
    }

    public function test_gdevelop_credit_calculation_includes_surcharge()
    {
        $creditManager = app(CreditManager::class);
        
        // Test GDevelop surcharge calculation
        $surcharge = $creditManager->calculateMcpSurcharge('gdevelop', 1);
        $this->assertEquals(0.05, $surcharge);
        
        // Test multiple actions (use delta for floating point comparison)
        $surcharge = $creditManager->calculateMcpSurcharge('gdevelop', 3);
        $this->assertEqualsWithDelta(0.15, $surcharge, 0.001);
    }

    public function test_gdevelop_chat_endpoint_deducts_credits()
    {
        $this->actingAs($this->user);

        $initialCredits = $this->company->credits;

        $response = $this->postJson('/api/gdevelop/chat', [
            'message' => 'Create a simple platformer game',
            'workspace_id' => $this->workspace->id,
            'session_id' => Str::uuid()->toString()
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'session_id',
            'game_data',
            'preview_url',
            'credits_used',
            'credits_remaining'
        ]);

        // Verify credits were deducted
        $this->company->refresh();
        $this->assertLessThan($initialCredits, $this->company->credits);
        
        // Verify credit transaction was recorded
        $this->assertDatabaseHas('credit_transactions', [
            'company_id' => $this->company->id,
            'type' => 'debit',
            'description' => 'GDevelop Game Generation'
        ]);
    }

    public function test_gdevelop_engine_analytics_include_gdevelop_breakdown()
    {
        $creditManager = app(CreditManager::class);
        
        // Create some test transactions
        $this->company->creditTransactions()->create([
            'amount' => 10,
            'type' => 'debit',
            'description' => 'GDevelop Game Generation',
            'metadata' => [
                'engine_type' => 'gdevelop',
                'mcp_surcharge' => 0.05,
                'session_id' => 'test-session'
            ]
        ]);

        $analytics = $creditManager->getEngineUsageAnalytics(
            $this->company,
            now()->subDays(7),
            now()
        );

        $this->assertArrayHasKey('gdevelop', $analytics['engine_breakdown']);
        $this->assertEquals(10, $analytics['engine_breakdown']['gdevelop']['usage']);
        $this->assertEquals(1, $analytics['engine_breakdown']['gdevelop']['transactions']);
        $this->assertEquals(0.05, $analytics['engine_breakdown']['gdevelop']['mcp_surcharge']);
    }

    public function test_workspace_index_displays_gdevelop_workspaces()
    {
        $this->actingAs($this->user);

        $response = $this->get('/workspaces');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Workspaces/Index')
                ->has('workspaces.gdevelop.0')
                ->where('workspaces.gdevelop.0.name', 'Test GDevelop Workspace')
                ->where('workspaces.gdevelop.0.engine_type', 'gdevelop')
        );
    }

    public function test_gdevelop_workspace_selection_works()
    {
        $this->actingAs($this->user);

        $response = $this->post('/workspaces/select', [
            'workspace_id' => $this->workspace->id
        ]);

        $response->assertRedirect('/chat');
        $this->assertEquals($this->workspace->id, session('selected_workspace_id'));
    }

    public function test_user_can_set_gdevelop_engine_preference()
    {
        $engineService = app(EngineSelectionService::class);
        
        $engineService->setUserEnginePreference($this->user, 'gdevelop');
        
        $this->assertEquals('gdevelop', $this->user->fresh()->getSelectedEngineType());
    }

    public function test_gdevelop_templates_are_available()
    {
        $this->actingAs($this->user);

        $response = $this->get('/workspaces/templates?engine_type=gdevelop');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
        
        $templates = $response->json('templates');
        $this->assertNotEmpty($templates);
        
        // Check that expected templates exist
        $templateIds = collect($templates)->pluck('id')->toArray();
        $this->assertContains('platformer', $templateIds);
        $this->assertContains('tower_defense', $templateIds);
        $this->assertContains('puzzle', $templateIds);
        $this->assertContains('arcade', $templateIds);
    }

    public function test_insufficient_credits_prevents_gdevelop_operation()
    {
        $this->actingAs($this->user);
        
        // Set company credits to very low amount
        $this->company->update(['credits' => 1]);

        $response = $this->postJson('/api/gdevelop/chat', [
            'message' => 'Create a complex tower defense game with multiple levels',
            'workspace_id' => $this->workspace->id,
            'session_id' => Str::uuid()->toString()
        ]);

        $response->assertStatus(402);
        $response->assertJson([
            'success' => false,
            'error' => 'Insufficient credits for this operation'
        ]);
    }
}