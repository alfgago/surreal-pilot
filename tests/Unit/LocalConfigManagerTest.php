<?php

namespace Tests\Unit;

use App\Services\LocalConfigManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LocalConfigManagerTest extends TestCase
{
    private LocalConfigManager $configManager;
    private string $originalHome;
    private string $testHome;
    private string $testConfigDir;
    private string $testConfigPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original HOME and create test home
        $this->originalHome = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $this->testHome = sys_get_temp_dir() . '/test_home_' . uniqid();
        $this->testConfigDir = $this->testHome . '/.surrealpilot';
        $this->testConfigPath = $this->testConfigDir . '/config.json';
        
        // Set test home directory
        $_SERVER['HOME'] = $this->testHome;
        if (isset($_SERVER['USERPROFILE'])) {
            $_SERVER['USERPROFILE'] = $this->testHome;
        }
        
        // Create config manager with test environment
        $this->configManager = new LocalConfigManager();
    }

    protected function tearDown(): void
    {
        // Restore original HOME
        if ($this->originalHome) {
            $_SERVER['HOME'] = $this->originalHome;
            if (isset($_SERVER['USERPROFILE'])) {
                $_SERVER['USERPROFILE'] = $this->originalHome;
            }
        }
        
        // Clean up test directory
        if (File::exists($this->testHome)) {
            File::deleteDirectory($this->testHome);
        }
        
        parent::tearDown();
    }

    public function test_constructor_creates_config_directory_and_file()
    {
        $this->assertTrue(File::exists($this->testConfigDir));
        $this->assertTrue(File::exists($this->testConfigPath));
    }

    public function test_get_config_returns_default_config_when_file_not_exists()
    {
        // Delete the config file
        File::delete($this->testConfigPath);
        
        $config = $this->configManager->getConfig();
        
        $this->assertIsArray($config);
        $this->assertEquals('openai', $config['preferred_provider']);
        $this->assertArrayHasKey('api_keys', $config);
        $this->assertArrayHasKey('saas_token', $config);
        $this->assertEquals(8000, $config['server_port']);
    }

    public function test_get_config_returns_merged_config_with_defaults()
    {
        // Write partial config
        $partialConfig = [
            'preferred_provider' => 'anthropic',
            'api_keys' => [
                'openai' => 'test-key'
            ]
        ];
        
        File::put($this->testConfigPath, json_encode($partialConfig));
        
        $config = $this->configManager->getConfig();
        
        $this->assertEquals('anthropic', $config['preferred_provider']);
        $this->assertEquals('test-key', $config['api_keys']['openai']);
        $this->assertArrayHasKey('anthropic', $config['api_keys']); // Should be merged from defaults
        $this->assertNull($config['api_keys']['anthropic']); // Should be null from defaults
        $this->assertEquals(8000, $config['server_port']); // Should be from defaults
    }

    public function test_get_config_handles_invalid_json()
    {
        // Write invalid JSON
        File::put($this->testConfigPath, 'invalid json');
        
        Log::shouldReceive('warning')->once();
        
        $config = $this->configManager->getConfig();
        
        // Should return defaults when JSON is invalid
        $this->assertEquals('openai', $config['preferred_provider']);
    }

    public function test_update_config_merges_with_existing_config()
    {
        $updates = [
            'preferred_provider' => 'gemini',
            'api_keys' => [
                'gemini' => 'test-gemini-key'
            ]
        ];
        
        $this->configManager->updateConfig($updates);
        
        $config = $this->configManager->getConfig();
        
        $this->assertEquals('gemini', $config['preferred_provider']);
        $this->assertEquals('test-gemini-key', $config['api_keys']['gemini']);
        $this->assertArrayHasKey('openai', $config['api_keys']); // Should remain from defaults
        $this->assertNull($config['api_keys']['openai']); // Should be null from defaults
        $this->assertArrayHasKey('updated_at', $config);
    }

    public function test_get_and_set_api_keys()
    {
        $this->configManager->setApiKey('openai', 'sk-test-key');
        $this->configManager->setApiKey('anthropic', 'sk-ant-test');
        
        $apiKeys = $this->configManager->getApiKeys();
        
        $this->assertEquals('sk-test-key', $apiKeys['openai']);
        $this->assertEquals('sk-ant-test', $apiKeys['anthropic']);
        $this->assertNull($apiKeys['gemini']);
    }

    public function test_get_and_set_preferred_provider()
    {
        $this->assertEquals('openai', $this->configManager->getPreferredProvider());
        
        $this->configManager->setPreferredProvider('anthropic');
        
        $this->assertEquals('anthropic', $this->configManager->getPreferredProvider());
    }

    public function test_get_and_set_saas_token()
    {
        $this->assertNull($this->configManager->getSaasToken());
        
        $this->configManager->setSaasToken('test-token');
        
        $this->assertEquals('test-token', $this->configManager->getSaasToken());
    }

    public function test_get_and_set_server_port()
    {
        $this->assertEquals(8000, $this->configManager->getServerPort());
        
        $this->configManager->setServerPort(8001);
        
        $this->assertEquals(8001, $this->configManager->getServerPort());
    }

    public function test_find_available_port_returns_valid_port()
    {
        // Test that findAvailablePort returns a valid port
        $port = $this->configManager->findAvailablePort();
        
        $this->assertIsInt($port);
        $this->assertGreaterThanOrEqual(8000, $port);
        $this->assertLessThanOrEqual(8100, $port);
        
        // Verify the port was saved to config
        $this->assertEquals($port, $this->configManager->getServerPort());
    }

    public function test_reset_config_restores_defaults()
    {
        // Modify config
        $this->configManager->setPreferredProvider('anthropic');
        $this->configManager->setApiKey('openai', 'test-key');
        
        // Reset
        $this->configManager->resetConfig();
        
        $config = $this->configManager->getConfig();
        
        $this->assertEquals('openai', $config['preferred_provider']);
        $this->assertNull($config['api_keys']['openai']);
    }

    public function test_config_exists_returns_correct_status()
    {
        $this->assertTrue($this->configManager->configExists());
        
        File::delete($this->testConfigPath);
        
        $this->assertFalse($this->configManager->configExists());
    }

    public function test_get_config_path_returns_correct_path()
    {
        $path = $this->configManager->getConfigPath();
        
        $this->assertTrue(str_ends_with(str_replace('\\', '/', $path), '/.surrealpilot/config.json'));
    }

    public function test_handles_file_operations_gracefully()
    {
        // Test that the config manager handles file operations without throwing exceptions
        $this->configManager->setApiKey('openai', 'test-key');
        $this->configManager->setPreferredProvider('anthropic');
        $this->configManager->setSaasToken('test-token');
        
        // Verify all operations completed successfully
        $this->assertEquals('test-key', $this->configManager->getApiKeys()['openai']);
        $this->assertEquals('anthropic', $this->configManager->getPreferredProvider());
        $this->assertEquals('test-token', $this->configManager->getSaasToken());
    }

    public function test_port_availability_check()
    {
        // Test that port availability checking works
        $port = $this->configManager->findAvailablePort();
        
        $this->assertIsInt($port);
        $this->assertGreaterThanOrEqual(8000, $port);
        $this->assertLessThanOrEqual(8100, $port);
    }
}