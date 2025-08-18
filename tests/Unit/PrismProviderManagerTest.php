<?php

namespace Tests\Unit;

use App\Services\PrismProviderManager;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PrismPHP\Prism\Prism;
use Tests\TestCase;

class PrismProviderManagerTest extends TestCase
{
    private PrismProviderManager $providerManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->providerManager = new PrismProviderManager();
        
        // Set up basic provider configuration
        Config::set('prism.default', 'openai');
        Config::set('prism.fallback_chain', ['openai', 'anthropic', 'gemini', 'ollama']);
        Config::set('prism.providers', [
            'openai' => [
                'api_key' => 'sk-test-key-123456789012345678901234567890',
                'models' => ['default' => 'gpt-4'],
            ],
            'anthropic' => [
                'api_key' => 'sk-ant-test-key-123456789012345678901234567890',
                'models' => ['default' => 'claude-3'],
            ],
            'gemini' => [
                'api_key' => 'test-gemini-key',
                'models' => ['default' => 'gemini-pro'],
            ],
            'ollama' => [
                'base_url' => 'http://localhost:11434',
                'health_check' => [
                    'enabled' => true,
                    'endpoint' => '/api/tags',
                    'timeout' => 5,
                ],
                'models' => ['default' => 'qwen2.5-coder:7b'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_provider_returns_preferred_when_available()
    {
        Log::shouldReceive('info')->once();

        $provider = $this->providerManager->resolveProvider('openai');

        $this->assertEquals('openai', $provider);
    }

    public function test_resolve_provider_falls_back_when_preferred_unavailable()
    {
        // Make OpenAI unavailable by removing API key
        Config::set('prism.providers.openai.api_key', '');
        
        Log::shouldReceive('debug')->once();
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $provider = $this->providerManager->resolveProvider('openai');

        $this->assertEquals('anthropic', $provider);
    }

    public function test_resolve_provider_throws_exception_when_none_available()
    {
        // Make all providers unavailable
        Config::set('prism.providers.openai.api_key', '');
        Config::set('prism.providers.anthropic.api_key', '');
        Config::set('prism.providers.gemini.api_key', '');
        Config::set('prism.providers.ollama.health_check.enabled', true);
        
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        Log::shouldReceive('debug')->atLeast()->once();
        Log::shouldReceive('warning')->once();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No AI providers are currently available');

        $this->providerManager->resolveProvider('openai');
    }

    public function test_is_provider_available_checks_openai_correctly()
    {
        $this->assertTrue($this->providerManager->isProviderAvailable('openai'));

        // Test with invalid API key
        Config::set('prism.providers.openai.api_key', 'invalid');
        Log::shouldReceive('debug')->once();
        
        $this->assertFalse($this->providerManager->isProviderAvailable('openai'));
    }

    public function test_is_provider_available_checks_anthropic_correctly()
    {
        $this->assertTrue($this->providerManager->isProviderAvailable('anthropic'));

        // Test with invalid API key
        Config::set('prism.providers.anthropic.api_key', 'invalid');
        Log::shouldReceive('debug')->once();
        
        $this->assertFalse($this->providerManager->isProviderAvailable('anthropic'));
    }

    public function test_is_provider_available_checks_gemini_correctly()
    {
        $this->assertTrue($this->providerManager->isProviderAvailable('gemini'));

        // Test with short API key
        Config::set('prism.providers.gemini.api_key', 'short');
        Log::shouldReceive('debug')->once();
        
        $this->assertFalse($this->providerManager->isProviderAvailable('gemini'));
    }

    public function test_is_provider_available_checks_ollama_with_health_check()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        Log::shouldReceive('debug')->once();

        $this->assertTrue($this->providerManager->isProviderAvailable('ollama'));
    }

    public function test_is_provider_available_ollama_fails_health_check()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        Log::shouldReceive('debug')->twice();

        $this->assertFalse($this->providerManager->isProviderAvailable('ollama'));
    }

    public function test_is_provider_available_ollama_with_disabled_health_check()
    {
        Config::set('prism.providers.ollama.health_check.enabled', false);

        $this->assertTrue($this->providerManager->isProviderAvailable('ollama'));
    }

    public function test_is_provider_available_returns_false_for_unknown_provider()
    {
        Log::shouldReceive('warning')->once();

        $this->assertFalse($this->providerManager->isProviderAvailable('unknown'));
    }

    public function test_is_provider_available_handles_exceptions()
    {
        Config::set('prism.providers.openai', null);
        Log::shouldReceive('debug')->once();

        $this->assertFalse($this->providerManager->isProviderAvailable('openai'));
    }

    public function test_get_available_providers_returns_only_available()
    {
        // Make anthropic unavailable
        Config::set('prism.providers.anthropic.api_key', '');
        
        // Mock Ollama as available
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        Log::shouldReceive('debug')->atLeast()->once();

        $available = $this->providerManager->getAvailableProviders();

        $this->assertContains('openai', $available);
        $this->assertContains('gemini', $available);
        $this->assertContains('ollama', $available);
        $this->assertNotContains('anthropic', $available);
    }

    public function test_get_provider_config_returns_resolved_config()
    {
        Log::shouldReceive('info')->once();

        $config = $this->providerManager->getProviderConfig('openai');

        $this->assertEquals('sk-test-key-123456789012345678901234567890', $config['api_key']);
        $this->assertEquals('gpt-4', $config['models']['default']);
    }

    public function test_create_prism_instance_returns_prism_object()
    {
        // Mock the Prism class
        $mockPrism = Mockery::mock('alias:' . Prism::class);
        $mockPrism->shouldReceive('with')
            ->with('openai')
            ->once()
            ->andReturn($mockPrism);

        Log::shouldReceive('info')->once();

        $prism = $this->providerManager->createPrismInstance('openai');

        $this->assertInstanceOf(Prism::class, $prism);
    }

    public function test_get_provider_stats_returns_complete_information()
    {
        // Mock Ollama as available
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        Log::shouldReceive('debug')->atLeast()->once();

        $stats = $this->providerManager->getProviderStats();

        $this->assertArrayHasKey('openai', $stats);
        $this->assertArrayHasKey('anthropic', $stats);
        $this->assertArrayHasKey('gemini', $stats);
        $this->assertArrayHasKey('ollama', $stats);

        $openaiStats = $stats['openai'];
        $this->assertEquals('openai', $openaiStats['name']);
        $this->assertTrue($openaiStats['available']);
        $this->assertTrue($openaiStats['configured']);
        $this->assertEquals('gpt-4', $openaiStats['default_model']);

        $ollamaStats = $stats['ollama'];
        $this->assertEquals('ollama', $ollamaStats['name']);
        $this->assertTrue($ollamaStats['available']);
        $this->assertTrue($ollamaStats['configured']); // Ollama doesn't need API key
    }

    public function test_check_openai_availability_validates_api_key_format()
    {
        $validConfig = ['api_key' => 'sk-test123456789012345678901234567890'];
        $invalidConfig1 = ['api_key' => 'invalid-key'];
        $invalidConfig2 = ['api_key' => 'sk-short'];
        $invalidConfig3 = ['api_key' => ''];

        $reflection = new \ReflectionClass($this->providerManager);
        $method = $reflection->getMethod('checkOpenAIAvailability');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->providerManager, $validConfig));
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig1));
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig2));
        
        Log::shouldReceive('debug')->once();
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig3));
    }

    public function test_check_anthropic_availability_validates_api_key_format()
    {
        $validConfig = ['api_key' => 'sk-ant-test123456789012345678901234567890'];
        $invalidConfig1 = ['api_key' => 'sk-test123456789012345678901234567890']; // Wrong prefix
        $invalidConfig2 = ['api_key' => 'sk-ant-short'];

        $reflection = new \ReflectionClass($this->providerManager);
        $method = $reflection->getMethod('checkAnthropicAvailability');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->providerManager, $validConfig));
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig1));
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig2));
    }

    public function test_check_gemini_availability_validates_api_key_length()
    {
        $validConfig = ['api_key' => 'valid-gemini-key'];
        $invalidConfig = ['api_key' => 'short'];

        $reflection = new \ReflectionClass($this->providerManager);
        $method = $reflection->getMethod('checkGeminiAvailability');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->providerManager, $validConfig));
        
        Log::shouldReceive('debug')->once();
        $this->assertFalse($method->invoke($this->providerManager, $invalidConfig));
    }

    public function test_check_ollama_availability_handles_connection_errors()
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $config = [
            'base_url' => 'http://localhost:11434',
            'health_check' => [
                'enabled' => true,
                'endpoint' => '/api/tags',
                'timeout' => 5,
            ],
        ];

        $reflection = new \ReflectionClass($this->providerManager);
        $method = $reflection->getMethod('checkOllamaAvailability');
        $method->setAccessible(true);

        Log::shouldReceive('debug')->once();

        $this->assertFalse($method->invoke($this->providerManager, $config));
    }
}