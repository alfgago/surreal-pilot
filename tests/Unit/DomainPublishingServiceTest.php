<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Workspace;
use App\Services\DomainPublishingService;
use App\Services\GameStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class DomainPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DomainPublishingService $service;
    private GameStorageService $gameStorageService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gameStorageService = Mockery::mock(GameStorageService::class);
        $this->service = new DomainPublishingService($this->gameStorageService);
    }

    public function test_setup_custom_domain_with_valid_domain()
    {
        $workspace = Workspace::factory()->create();
        $game = Game::factory()->create(['workspace_id' => $workspace->id]);
        
        // No need to mock GameStorageService methods since we're using internal logic

        $result = $this->service->setupCustomDomain($game, 'example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('example.com', $result['domain']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('dns_instructions', $result);
        
        $game->refresh();
        $this->assertEquals('example.com', $game->custom_domain);
        $this->assertEquals('pending', $game->domain_status);
    }

    public function test_setup_custom_domain_with_invalid_domain()
    {
        $workspace = Workspace::factory()->create();
        $game = Game::factory()->create(['workspace_id' => $workspace->id]);

        $result = $this->service->setupCustomDomain($game, 'invalid-domain');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        
        $game->refresh();
        $this->assertNull($game->custom_domain);
    }

    public function test_setup_custom_domain_with_duplicate_domain()
    {
        $workspace = Workspace::factory()->create();
        $game1 = Game::factory()->create([
            'workspace_id' => $workspace->id,
            'custom_domain' => 'example.com'
        ]);
        $game2 = Game::factory()->create(['workspace_id' => $workspace->id]);

        $result = $this->service->setupCustomDomain($game2, 'example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContains('already in use', $result['error']);
    }

    public function test_generate_dns_instructions()
    {
        $instructions = $this->service->generateDNSInstructions('example.com');

        $this->assertEquals('A Record', $instructions['type']);
        $this->assertEquals('@', $instructions['name']);
        $this->assertArrayHasKey('value', $instructions);
        $this->assertArrayHasKey('instructions', $instructions);
        $this->assertIsArray($instructions['instructions']);
        $this->assertArrayHasKey('common_providers', $instructions);
    }

    public function test_verify_domain_without_custom_domain()
    {
        $workspace = Workspace::factory()->create();
        $game = Game::factory()->create(['workspace_id' => $workspace->id]);

        $result = $this->service->verifyDomain($game);

        $this->assertFalse($result['success']);
        $this->assertStringContains('No custom domain configured', $result['error']);
    }

    public function test_remove_domain()
    {
        $workspace = Workspace::factory()->create();
        $game = Game::factory()->create([
            'workspace_id' => $workspace->id,
            'custom_domain' => 'example.com',
            'domain_status' => 'active'
        ]);

        $result = $this->service->removeDomain($game);

        $this->assertTrue($result['success']);
        
        $game->refresh();
        $this->assertNull($game->custom_domain);
        $this->assertNull($game->domain_status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}