<?php

namespace Tests\Unit;

use App\Services\OllamaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    private OllamaService $ollamaService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.ollama.url', 'http://localhost:11434');
        Config::set('services.ollama.timeout', 120);
        
        $this->ollamaService = new OllamaService();
    }

    public function test_is_available_returns_true_when_ollama_responds()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
        ]);

        $this->assertTrue($this->ollamaService->isAvailable());
    }

    public function test_is_available_returns_false_when_ollama_fails()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $this->assertFalse($this->ollamaService->isAvailable());
    }

    public function test_is_available_returns_false_when_connection_fails()
    {
        Http::fake(function () {
            throw new \Exception('Connection failed');
        });

        $this->assertFalse($this->ollamaService->isAvailable());
    }

    public function test_get_available_models_returns_formatted_models()
    {
        $mockResponse = [
            'models' => [
                [
                    'name' => 'qwen2.5-coder:7b',
                    'size' => 4200000000,
                    'modified_at' => '2024-01-01T00:00:00Z',
                ],
                [
                    'name' => 'codellama:7b',
                    'size' => 3800000000,
                ],
            ],
        ];

        Http::fake([
            'localhost:11434/api/tags' => Http::response($mockResponse, 200),
        ]);

        $models = $this->ollamaService->getAvailableModels();

        $this->assertCount(2, $models);
        $this->assertEquals('qwen2.5-coder:7b', $models[0]['name']);
        $this->assertEquals(4200000000, $models[0]['size']);
        $this->assertEquals('2024-01-01T00:00:00Z', $models[0]['modified_at']);
        $this->assertEquals('codellama:7b', $models[1]['name']);
        $this->assertNull($models[1]['modified_at']);
    }

    public function test_get_available_models_returns_empty_on_failure()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $models = $this->ollamaService->getAvailableModels();

        $this->assertEmpty($models);
    }

    public function test_pull_model_returns_true_on_success()
    {
        Http::fake([
            'localhost:11434/api/pull' => Http::response(['status' => 'success'], 200),
        ]);

        $result = $this->ollamaService->pullModel('qwen2.5-coder:7b');

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/pull' &&
                   $request['name'] === 'qwen2.5-coder:7b';
        });
    }

    public function test_pull_model_returns_false_on_failure()
    {
        Http::fake([
            'localhost:11434/api/pull' => Http::response([], 500),
        ]);

        $result = $this->ollamaService->pullModel('invalid-model');

        $this->assertFalse($result);
    }

    public function test_get_recommended_models_returns_expected_structure()
    {
        $models = $this->ollamaService->getRecommendedModels();

        $this->assertArrayHasKey('qwen2.5-coder:7b', $models);
        $this->assertArrayHasKey('qwen2.5-coder:14b', $models);
        $this->assertArrayHasKey('codellama:7b', $models);
        $this->assertArrayHasKey('deepseek-coder:6.7b', $models);

        $recommendedModel = $models['qwen2.5-coder:7b'];
        $this->assertEquals('Qwen2.5-Coder 7B', $recommendedModel['name']);
        $this->assertTrue($recommendedModel['recommended']);
        $this->assertArrayHasKey('description', $recommendedModel);
        $this->assertArrayHasKey('size', $recommendedModel);
        $this->assertArrayHasKey('use_case', $recommendedModel);
    }

    public function test_setup_for_ue_fails_when_ollama_unavailable()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $result = $this->ollamaService->setupForUE();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Ollama is not running', $result['message']);
        $this->assertArrayHasKey('instructions', $result);
    }

    public function test_setup_for_ue_pulls_model_when_not_available()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []], 200),
            'localhost:11434/api/pull' => Http::response(['status' => 'success'], 200),
        ]);

        $result = $this->ollamaService->setupForUE();

        $this->assertTrue($result['success']);
        $this->assertEquals('qwen2.5-coder:7b', $result['model']);
        $this->assertArrayHasKey('available_models', $result);
    }

    public function test_setup_for_ue_succeeds_when_model_already_available()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5-coder:7b', 'size' => 4200000000],
                ],
            ], 200),
        ]);

        $result = $this->ollamaService->setupForUE();

        $this->assertTrue($result['success']);
        $this->assertEquals('qwen2.5-coder:7b', $result['model']);
    }

    public function test_test_with_ue_prompt_returns_success_response()
    {
        $mockResponse = "To create a Blueprint that spawns an actor:\n1. Create new Blueprint\n2. Add input event\n3. Use Spawn Actor node";
        
        Http::fake([
            'localhost:11434/api/generate' => Http::response(
                json_encode(['response' => $mockResponse, 'done' => true]),
                200
            ),
        ]);

        $result = $this->ollamaService->testWithUEPrompt('qwen2.5-coder:7b');

        $this->assertTrue($result['success']);
        $this->assertEquals('qwen2.5-coder:7b', $result['model']);
        $this->assertEquals($mockResponse, $result['response']);
        $this->assertGreaterThan(0, $result['response_length']);
    }

    public function test_test_with_ue_prompt_handles_failure()
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response([], 500),
        ]);

        $result = $this->ollamaService->testWithUEPrompt('invalid-model');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid-model', $result['model']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_chat_streams_response_correctly()
    {
        $streamResponse = json_encode(['response' => 'Hello']) . "\n" .
                         json_encode(['response' => ' world']) . "\n" .
                         json_encode(['response' => '!', 'done' => true]) . "\n";

        Http::fake([
            'localhost:11434/api/generate' => Http::response($streamResponse, 200),
        ]);

        $chunks = [];
        foreach ($this->ollamaService->chat('test-model', 'Hello') as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertEquals(['Hello', ' world', '!'], $chunks);
    }

    public function test_chat_throws_exception_on_failure()
    {
        Http::fake([
            'localhost:11434/api/generate' => Http::response('Error', 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama request failed');

        iterator_to_array($this->ollamaService->chat('test-model', 'Hello'));
    }
}