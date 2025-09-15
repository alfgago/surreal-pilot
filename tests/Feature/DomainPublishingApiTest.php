<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\Game;
use App\Services\DomainPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class DomainPublishingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->user->companies()->attach($this->company->id, ['role' => 'admin']);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);
        
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Tower Defense Game'
        ]);
    }

    public function test_setup_custom_domain_success()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'my-tower-defense.com'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'domain' => 'my-tower-defense.com',
                    'status' => 'pending'
                ])
                ->assertJsonStructure([
                    'success',
                    'domain',
                    'status',
                    'dns_instructions' => [
                        'type',
                        'name',
                        'value',
                        'ttl',
                        'instructions',
                        'common_providers'
                    ],
                    'verification_url',
                    'estimated_propagation_time'
                ]);

        // Verify game was updated in database
        $this->game->refresh();
        $this->assertEquals('my-tower-defense.com', $this->game->custom_domain);
        $this->assertEquals('pending', $this->game->domain_status);
        $this->assertNotNull($this->game->domain_config);
    }

    public function test_setup_custom_domain_validation_errors()
    {
        Sanctum::actingAs($this->user);

        // Test missing domain
        $response = $this->postJson("/api/games/{$this->game->id}/domain", []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['domain']);

        // Test invalid domain format
        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'invalid..domain'
        ]);
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ])
                ->assertJsonStructure([
                    'success',
                    'error',
                    'troubleshooting'
                ]);

        // Test localhost rejection
        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'localhost'
        ]);
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ]);
    }

    public function test_setup_custom_domain_already_in_use()
    {
        Sanctum::actingAs($this->user);

        // Create another game with the domain
        $existingGame = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'custom_domain' => 'existing-domain.com'
        ]);

        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'existing-domain.com'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ])
                ->assertJsonFragment([
                    'error' => 'Domain existing-domain.com is already in use by another game.'
                ]);
    }

    public function test_verify_domain_without_custom_domain()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/domain/verify");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => false,
                    'error' => 'No custom domain configured for this game'
                ]);
    }

    public function test_verify_domain_dns_not_propagated()
    {
        Sanctum::actingAs($this->user);

        // Setup game with pending domain
        $this->game->update([
            'custom_domain' => 'not-propagated.com',
            'domain_status' => 'pending',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);

        // Mock the DomainPublishingService to simulate DNS not propagated
        $this->mock(DomainPublishingService::class, function ($mock) {
            $mock->shouldReceive('verifyDomain')
                 ->once()
                 ->andReturn([
                     'success' => false,
                     'status' => 'pending',
                     'message' => 'DNS propagation in progress. Please wait and try again.',
                     'expected_ip' => '127.0.0.1',
                     'current_status' => 'DNS not resolved'
                 ]);
        });

        $response = $this->postJson("/api/games/{$this->game->id}/domain/verify");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => false,
                    'status' => 'pending',
                    'message' => 'DNS propagation in progress. Please wait and try again.'
                ]);
    }

    public function test_verify_domain_success()
    {
        Sanctum::actingAs($this->user);

        // Setup game with pending domain
        $this->game->update([
            'custom_domain' => 'working-domain.com',
            'domain_status' => 'pending',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);

        // Mock the DomainPublishingService to simulate successful verification
        $this->mock(DomainPublishingService::class, function ($mock) {
            $mock->shouldReceive('verifyDomain')
                 ->once()
                 ->andReturn([
                     'success' => true,
                     'status' => 'active',
                     'message' => 'Domain successfully configured and active',
                     'domain_url' => 'http://working-domain.com',
                     'verified_at' => now()->toISOString()
                 ]);
        });

        $response = $this->postJson("/api/games/{$this->game->id}/domain/verify");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'active',
                    'message' => 'Domain successfully configured and active'
                ])
                ->assertJsonStructure([
                    'success',
                    'status',
                    'message',
                    'domain_url',
                    'verified_at'
                ]);
    }

    public function test_remove_domain_success()
    {
        Sanctum::actingAs($this->user);

        // Setup game with active domain
        $this->game->update([
            'custom_domain' => 'to-be-removed.com',
            'domain_status' => 'active',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);

        $response = $this->deleteJson("/api/games/{$this->game->id}/domain");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Custom domain configuration removed successfully'
                ]);

        // Verify game was updated in database
        $this->game->refresh();
        $this->assertNull($this->game->custom_domain);
        $this->assertNull($this->game->domain_status);
        $this->assertNull($this->game->domain_config);
    }

    public function test_get_domain_status_without_domain()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/games/{$this->game->id}/domain/status");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'domain' => [
                        'has_custom_domain' => false,
                        'custom_domain' => null,
                        'domain_status' => null,
                        'is_domain_active' => false,
                        'is_domain_pending' => false,
                        'is_domain_failed' => false,
                        'custom_domain_url' => null
                    ]
                ]);
    }

    public function test_get_domain_status_with_active_domain()
    {
        Sanctum::actingAs($this->user);

        // Setup game with active domain
        $this->game->update([
            'custom_domain' => 'active-domain.com',
            'domain_status' => 'active',
            'domain_config' => [
                'server_ip' => '127.0.0.1',
                'ssl_enabled' => false,
                'verified_at' => now()->toISOString()
            ]
        ]);

        $response = $this->getJson("/api/games/{$this->game->id}/domain/status");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'domain' => [
                        'has_custom_domain' => true,
                        'custom_domain' => 'active-domain.com',
                        'domain_status' => 'active',
                        'is_domain_active' => true,
                        'is_domain_pending' => false,
                        'is_domain_failed' => false,
                        'custom_domain_url' => 'http://active-domain.com'
                    ]
                ])
                ->assertJsonStructure([
                    'success',
                    'domain' => [
                        'has_custom_domain',
                        'custom_domain',
                        'domain_status',
                        'domain_config',
                        'is_domain_active',
                        'is_domain_pending',
                        'is_domain_failed',
                        'custom_domain_url',
                        'primary_url'
                    ]
                ]);
    }

    public function test_unauthorized_access_denied()
    {
        // Test without authentication
        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'unauthorized.com'
        ]);
        $response->assertStatus(401);

        // Test with different company user
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['current_company_id' => $otherCompany->id]);
        $otherUser->companies()->attach($otherCompany->id, ['role' => 'admin']);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'unauthorized.com'
        ]);
        $response->assertStatus(404); // Game not found for this company
    }

    public function test_game_not_found()
    {
        Sanctum::actingAs($this->user);

        $nonExistentGameId = 99999;

        $response = $this->postJson("/api/games/{$nonExistentGameId}/domain", [
            'domain' => 'test.com'
        ]);

        $response->assertStatus(404);
    }

    public function test_domain_setup_with_protocol_removal()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'https://my-game.com/'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'domain' => 'my-game.com' // Protocol and trailing slash removed
                ]);

        $this->game->refresh();
        $this->assertEquals('my-game.com', $this->game->custom_domain);
    }

    public function test_dns_instructions_contain_server_ip()
    {
        Sanctum::actingAs($this->user);

        // Set server IP in config
        config(['app.server_ip' => '192.168.1.100']);

        $response = $this->postJson("/api/games/{$this->game->id}/domain", [
            'domain' => 'ip-test.com'
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('dns_instructions.value', '192.168.1.100')
                ->assertJsonPath('dns_instructions.type', 'A Record')
                ->assertJsonPath('dns_instructions.name', '@')
                ->assertJsonPath('dns_instructions.ttl', 300);
    }

    public function test_domain_verification_error_handling()
    {
        Sanctum::actingAs($this->user);

        // Setup game with domain
        $this->game->update([
            'custom_domain' => 'error-domain.com',
            'domain_status' => 'pending'
        ]);

        // Mock service to throw exception
        $this->mock(DomainPublishingService::class, function ($mock) {
            $mock->shouldReceive('verifyDomain')
                 ->once()
                 ->andThrow(new \Exception('DNS lookup failed'));
        });

        $response = $this->postJson("/api/games/{$this->game->id}/domain/verify");

        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to verify domain',
                    'error' => 'DNS lookup failed'
                ]);
    }

    public function test_concurrent_domain_setup_prevention()
    {
        Sanctum::actingAs($this->user);

        // Create two games
        $game1 = $this->game;
        $game2 = Game::factory()->create(['workspace_id' => $this->workspace->id]);

        // Setup domain for first game
        $response1 = $this->postJson("/api/games/{$game1->id}/domain", [
            'domain' => 'concurrent-test.com'
        ]);
        $response1->assertStatus(200);

        // Try to setup same domain for second game
        $response2 = $this->postJson("/api/games/{$game2->id}/domain", [
            'domain' => 'concurrent-test.com'
        ]);
        $response2->assertStatus(400)
                 ->assertJsonFragment([
                     'error' => 'Domain concurrent-test.com is already in use by another game.'
                 ]);
    }
}