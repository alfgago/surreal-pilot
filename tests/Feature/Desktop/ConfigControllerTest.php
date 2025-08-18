<?php

namespace Tests\Feature\Desktop;

use App\Services\LocalConfigManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $originalHome;
    private string $testHome;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original HOME and create test home
        $this->originalHome = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $this->testHome = sys_get_temp_dir() . '/test_home_' . uniqid();
        
        // Set test home directory
        $_SERVER['HOME'] = $this->testHome;
        if (isset($_SERVER['USERPROFILE'])) {
            $_SERVER['USERPROFILE'] = $this->testHome;
        }
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

    public function test_get_config_returns_configuration()
    {
        $response = $this->get('/api/desktop/config');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'config' => [
                'preferred_provider',
                'api_keys',
                'saas_token',
                'saas_url',
                'server_port',
                'created_at'
            ],
            'server_port'
        ]);
        
        $data = $response->json();
        $this->assertEquals('openai', $data['config']['preferred_provider']);
        $this->assertEquals(8000, $data['server_port']);
    }

    public function test_get_config_masks_api_keys()
    {
        // Set up config with API keys
        $configManager = app(LocalConfigManager::class);
        $configManager->setApiKey('openai', 'sk-1234567890abcdef');
        $configManager->setApiKey('anthropic', 'sk-ant-1234567890abcdef');
        
        $response = $this->get('/api/desktop/config');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals('sk-12345...', $data['config']['api_keys']['openai']);
        $this->assertEquals('sk-ant-1...', $data['config']['api_keys']['anthropic']);
    }

    public function test_update_config_updates_settings()
    {
        $updateData = [
            'preferred_provider' => 'anthropic',
            'api_keys' => [
                'openai' => 'sk-new-key',
                'anthropic' => 'sk-ant-new-key'
            ],
            'saas_token' => 'new-token',
            'saas_url' => 'https://new-api.example.com'
        ];
        
        $response = $this->post('/api/desktop/config', $updateData);
        
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Configuration updated successfully'
        ]);
        
        // Verify the config was actually updated
        $configManager = app(LocalConfigManager::class);
        $this->assertEquals('anthropic', $configManager->getPreferredProvider());
        $this->assertEquals('sk-new-key', $configManager->getApiKeys()['openai']);
        $this->assertEquals('new-token', $configManager->getSaasToken());
    }

    public function test_update_config_validates_input()
    {
        $invalidData = [
            'preferred_provider' => 'invalid-provider',
            'saas_url' => 'not-a-url'
        ];
        
        $response = $this->postJson('/api/desktop/config', $invalidData);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['preferred_provider', 'saas_url']);
    }

    public function test_get_server_info_returns_server_details()
    {
        $response = $this->get('/api/desktop/server-info');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'port',
            'host',
            'url',
            'status',
            'version'
        ]);
        
        $data = $response->json();
        $this->assertEquals('127.0.0.1', $data['host']);
        $this->assertEquals('running', $data['status']);
        $this->assertStringStartsWith('http://127.0.0.1:', $data['url']);
    }

    public function test_test_ollama_connection_success()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama2'],
                    ['name' => 'codellama']
                ]
            ], 200)
        ]);
        
        $response = $this->post('/api/desktop/test-connection', [
            'service' => 'ollama'
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'connected',
            'message' => 'Ollama is running locally'
        ]);
        
        $data = $response->json();
        $this->assertArrayHasKey('models', $data);
    }

    public function test_test_ollama_connection_failure()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500)
        ]);
        
        $response = $this->post('/api/desktop/test-connection', [
            'service' => 'ollama'
        ]);
        
        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'failed',
            'message' => 'Ollama is not responding'
        ]);
    }

    public function test_test_saas_connection_success()
    {
        // Set up SaaS token
        $configManager = app(LocalConfigManager::class);
        $configManager->setSaasToken('test-token');
        
        Http::fake([
            'https://api.surrealpilot.com/api/providers' => Http::response([
                'providers' => ['openai', 'anthropic']
            ], 200)
        ]);
        
        $response = $this->post('/api/desktop/test-connection', [
            'service' => 'saas'
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'connected',
            'message' => 'SaaS API is accessible'
        ]);
    }

    public function test_test_saas_connection_no_token()
    {
        $response = $this->post('/api/desktop/test-connection', [
            'service' => 'saas'
        ]);
        
        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'failed',
            'message' => 'No SaaS token configured'
        ]);
    }

    public function test_test_connection_invalid_service()
    {
        $response = $this->post('/api/desktop/test-connection', [
            'service' => 'invalid'
        ]);
        
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'invalid_service',
            'message' => 'Unknown service to test'
        ]);
    }
}