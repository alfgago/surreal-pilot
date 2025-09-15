<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GDevelopChatControllerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        
        // Attach user to company via pivot table
        $this->user->companies()->attach($this->company);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop'
        ]);

        // Ensure storage directories exist
        Storage::makeDirectory('gdevelop/sessions');
        Storage::makeDirectory('gdevelop/exports');
        Storage::makeDirectory('gdevelop/templates');
    }

    public function test_chat_endpoint_integration_with_real_services(): void
    {
        // This test verifies the controller integrates properly with real services
        // but doesn't actually execute GDevelop CLI commands
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a simple platformer game with a player that can jump',
                'workspace_id' => $this->workspace->id,
                'options' => [
                    'game_type' => 'platformer',
                    'mobile_optimized' => false
                ]
            ]);



        // The response should be successful even if the underlying services
        // might fail due to missing GDevelop CLI in test environment
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'game_data',
                'preview_url',
                'message',
                'actions' => [
                    'preview' => ['available', 'url'],
                    'export' => ['available', 'url']
                ]
            ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertNotEmpty($responseData['session_id']);
        $this->assertIsArray($responseData['game_data']);
        $this->assertStringContainsString('/api/gdevelop/preview/', $responseData['preview_url']);
    }

    public function test_get_session_endpoint(): void
    {
        // First create a game session via chat
        $chatResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a tower defense game',
                'workspace_id' => $this->workspace->id
            ]);

        $chatResponse->assertStatus(200);
        $sessionId = $chatResponse->json('session_id');

        // Now get the session information
        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/session/{$sessionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'game_data',
                'preview_url',
                'actions'
            ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals($sessionId, $responseData['session_id']);
    }

    public function test_delete_session_endpoint(): void
    {
        // First create a game session via chat
        $chatResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a puzzle game',
                'workspace_id' => $this->workspace->id
            ]);

        $chatResponse->assertStatus(200);
        $sessionId = $chatResponse->json('session_id');

        // Delete the session
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/gdevelop/session/{$sessionId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'session_id' => $sessionId
            ]);

        // Verify the session is deleted by trying to get it
        $getResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/session/{$sessionId}");

        $getResponse->assertStatus(404);
    }

    public function test_authentication_required(): void
    {
        // Test that all endpoints require authentication
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';

        // Chat endpoint
        $response = $this->postJson('/api/gdevelop/chat', [
            'message' => 'Make a game'
        ]);
        $response->assertStatus(401);

        // Preview endpoint
        $response = $this->getJson("/api/gdevelop/preview/{$sessionId}");
        $response->assertStatus(401);

        // Export endpoint
        $response = $this->postJson("/api/gdevelop/export/{$sessionId}");
        $response->assertStatus(401);

        // Get session endpoint
        $response = $this->getJson("/api/gdevelop/session/{$sessionId}");
        $response->assertStatus(401);

        // Delete session endpoint
        $response = $this->deleteJson("/api/gdevelop/session/{$sessionId}");
        $response->assertStatus(401);
    }

    public function test_invalid_session_id_format(): void
    {
        $invalidSessionId = 'not-a-valid-uuid';

        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a game',
                'session_id' => $invalidSessionId
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_missing_message_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id
                // Missing required 'message' field
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_invalid_template_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a game',
                'template' => [
                    'name' => 'invalid-template-name'
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template.name']);
    }
}