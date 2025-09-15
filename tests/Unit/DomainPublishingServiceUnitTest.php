<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DomainPublishingService;
use App\Services\GameStorageService;
use App\Models\Game;
use App\Models\Workspace;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class DomainPublishingServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private DomainPublishingService $domainPublishingService;
    private GameStorageService $gameStorageService;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gameStorageService = $this->createMock(GameStorageService::class);
        $this->domainPublishingService = new DomainPublishingService($this->gameStorageService);
        
        // Create test data
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);
        $this->game = Game::factory()->create(['workspace_id' => $workspace->id]);
    }

    public function test_setup_custom_domain_success()
    {
        $domain = 'test-game.com';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $domain);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($domain, $result['domain']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('dns_instructions', $result);
        $this->assertArrayHasKey('vhost_config', $result);
        
        // Verify game was updated
        $this->game->refresh();
        $this->assertEquals($domain, $this->game->custom_domain);
        $this->assertEquals('pending', $this->game->domain_status);
    }

    public function test_setup_custom_domain_with_invalid_format()
    {
        $invalidDomain = 'invalid..domain';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $invalidDomain);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid domain format', $result['error']);
        $this->assertArrayHasKey('troubleshooting', $result);
    }

    public function test_setup_custom_domain_with_localhost()
    {
        $localhostDomain = 'localhost';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $localhostDomain);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('Localhost and IP addresses are not allowed', $result['error']);
    }

    public function test_setup_custom_domain_already_in_use()
    {
        $domain = 'existing-game.com';
        
        // Create another game with the same domain
        $existingGame = Game::factory()->create([
            'workspace_id' => $this->game->workspace_id,
            'custom_domain' => $domain
        ]);
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $domain);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('already in use', $result['error']);
    }

    public function test_generate_dns_instructions()
    {
        $domain = 'test-game.com';
        
        $instructions = $this->domainPublishingService->generateDNSInstructions($domain);
        
        $this->assertEquals('A Record', $instructions['type']);
        $this->assertEquals('@', $instructions['name']);
        $this->assertEquals(300, $instructions['ttl']);
        $this->assertIsArray($instructions['instructions']);
        $this->assertArrayHasKey('common_providers', $instructions);
        
        // Check that instructions contain the server IP
        $serverIp = env('SERVER_IP', '127.0.0.1');
        $this->assertEquals($serverIp, $instructions['value']);
    }

    public function test_verify_domain_without_custom_domain()
    {
        $result = $this->domainPublishingService->verifyDomain($this->game);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('No custom domain configured', $result['error']);
    }

    public function test_verify_domain_dns_not_propagated()
    {
        $domain = 'not-propagated.com';
        $this->game->update([
            'custom_domain' => $domain,
            'domain_status' => 'pending'
        ]);
        
        // Mock gethostbyname to return the domain (indicating DNS not resolved)
        $this->mockGlobalFunction('gethostbyname', $domain);
        
        $result = $this->domainPublishingService->verifyDomain($this->game);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('pending', $result['status']);
        $this->assertStringContains('DNS propagation in progress', $result['message']);
    }

    public function test_verify_domain_wrong_ip()
    {
        $domain = 'wrong-ip.com';
        $wrongIp = '192.168.1.1';
        $expectedIp = env('SERVER_IP', '127.0.0.1');
        
        $this->game->update([
            'custom_domain' => $domain,
            'domain_status' => 'pending'
        ]);
        
        // Mock gethostbyname to return wrong IP
        $this->mockGlobalFunction('gethostbyname', $wrongIp);
        
        $result = $this->domainPublishingService->verifyDomain($this->game);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals($expectedIp, $result['expected_ip']);
        $this->assertEquals($wrongIp, $result['resolved_ip']);
        $this->assertArrayHasKey('troubleshooting', $result);
    }

    public function test_verify_domain_success()
    {
        $domain = 'working-domain.com';
        $expectedIp = env('SERVER_IP', '127.0.0.1');
        
        $this->game->update([
            'custom_domain' => $domain,
            'domain_status' => 'pending'
        ]);
        
        // Mock gethostbyname to return correct IP
        $this->mockGlobalFunction('gethostbyname', $expectedIp);
        
        $result = $this->domainPublishingService->verifyDomain($this->game);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
        $this->assertStringContains('successfully configured', $result['message']);
        $this->assertArrayHasKey('domain_url', $result);
        
        // Verify game status was updated
        $this->game->refresh();
        $this->assertEquals('active', $this->game->domain_status);
    }

    public function test_remove_domain_success()
    {
        $domain = 'to-be-removed.com';
        $this->game->update([
            'custom_domain' => $domain,
            'domain_status' => 'active',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);
        
        $result = $this->domainPublishingService->removeDomain($this->game);
        
        $this->assertTrue($result['success']);
        $this->assertStringContains('removed successfully', $result['message']);
        
        // Verify game was updated
        $this->game->refresh();
        $this->assertNull($this->game->custom_domain);
        $this->assertNull($this->game->domain_status);
        $this->assertNull($this->game->domain_config);
    }

    public function test_validate_domain_removes_protocol()
    {
        $domainWithProtocol = 'https://test-game.com/';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $domainWithProtocol);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('test-game.com', $result['domain']);
    }

    public function test_generate_virtual_host_config()
    {
        $domain = 'test-game.com';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $domain);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('vhost_config', $result);
        
        $vhostConfig = $result['vhost_config'];
        $this->assertStringContains($domain, $vhostConfig);
        $this->assertStringContains('VirtualHost', $vhostConfig);
        $this->assertStringContains('DocumentRoot', $vhostConfig);
    }

    public function test_get_server_ip_from_environment()
    {
        // Set environment variable
        config(['app.server_ip' => '192.168.1.100']);
        
        $domain = 'test-game.com';
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $domain);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('192.168.1.100', $result['dns_instructions']['value']);
    }

    public function test_troubleshooting_steps_included()
    {
        $invalidDomain = 'invalid..domain';
        
        $result = $this->domainPublishingService->setupCustomDomain($this->game, $invalidDomain);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('troubleshooting', $result);
        
        $troubleshooting = $result['troubleshooting'];
        $this->assertArrayHasKey('DNS Propagation', $troubleshooting);
        $this->assertArrayHasKey('Incorrect DNS Configuration', $troubleshooting);
        $this->assertArrayHasKey('Domain Registrar Issues', $troubleshooting);
        $this->assertArrayHasKey('Firewall or Network Issues', $troubleshooting);
    }

    /**
     * Mock a global function for testing
     */
    private function mockGlobalFunction(string $functionName, $returnValue): void
    {
        if (!function_exists($functionName . '_original')) {
            eval("function {$functionName}_original() { return call_user_func_array('{$functionName}', func_get_args()); }");
        }
        
        eval("function {$functionName}() { return '" . addslashes($returnValue) . "'; }");
    }

    protected function tearDown(): void
    {
        // Restore original functions if they were mocked
        $functionsToRestore = ['gethostbyname'];
        
        foreach ($functionsToRestore as $function) {
            if (function_exists($function . '_original')) {
                eval("function {$function}() { return call_user_func_array('{$function}_original', func_get_args()); }");
            }
        }
        
        parent::tearDown();
    }
}