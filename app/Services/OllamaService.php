<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->timeout = config('services.ollama.timeout', 120);
    }

    /**
     * Check if Ollama is running and accessible
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (\Exception $e) {
            Log::debug('Ollama availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of available models
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
            
            if ($response->successful()) {
                $data = $response->json();
                return collect($data['models'] ?? [])
                    ->map(fn($model) => [
                        'name' => $model['name'],
                        'size' => $model['size'] ?? 0,
                        'modified_at' => $model['modified_at'] ?? null,
                    ])
                    ->toArray();
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Ollama models: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Pull a model if not already available
     */
    public function pullModel(string $model): bool
    {
        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/pull", [
                'name' => $model,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Failed to pull Ollama model {$model}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recommended models for UE development
     */
    public function getRecommendedModels(): array
    {
        return [
            'qwen2.5-coder:7b' => [
                'name' => 'Qwen2.5-Coder 7B',
                'description' => 'Optimized for code generation and debugging',
                'size' => '4.2GB',
                'recommended' => true,
                'use_case' => 'General coding, Blueprint help, C++ assistance',
            ],
            'qwen2.5-coder:14b' => [
                'name' => 'Qwen2.5-Coder 14B',
                'description' => 'Larger model with better reasoning for complex problems',
                'size' => '8.2GB',
                'recommended' => false,
                'use_case' => 'Complex architecture decisions, advanced debugging',
            ],
            'codellama:7b' => [
                'name' => 'CodeLlama 7B',
                'description' => 'Meta\'s code-focused model',
                'size' => '3.8GB',
                'recommended' => false,
                'use_case' => 'Alternative for code generation',
            ],
            'deepseek-coder:6.7b' => [
                'name' => 'DeepSeek Coder 6.7B',
                'description' => 'Specialized in code understanding and generation',
                'size' => '3.7GB',
                'recommended' => false,
                'use_case' => 'Code analysis and refactoring',
            ],
        ];
    }

    /**
     * Setup Ollama with recommended models for UE development
     */
    public function setupForUE(): array
    {
        $results = [];
        
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'message' => 'Ollama is not running. Please start Ollama first.',
                'instructions' => [
                    '1. Download Ollama from https://ollama.ai',
                    '2. Install and start Ollama',
                    '3. Run: ollama serve',
                    '4. Try this setup again',
                ],
            ];
        }

        $availableModels = collect($this->getAvailableModels())->pluck('name')->toArray();
        $recommendedModel = 'qwen2.5-coder:7b';

        if (!in_array($recommendedModel, $availableModels)) {
            Log::info("Pulling recommended model: {$recommendedModel}");
            
            if ($this->pullModel($recommendedModel)) {
                $results['model_pulled'] = $recommendedModel;
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to pull recommended model: {$recommendedModel}",
                    'manual_setup' => "Run: ollama pull {$recommendedModel}",
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Ollama is ready for UE development',
            'model' => $recommendedModel,
            'available_models' => $this->getAvailableModels(),
        ];
    }

    /**
     * Generate chat completion
     */
    public function chat(string $model, string $prompt, array $options = []): \Generator
    {
        $defaultOptions = [
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 4000,
        ];

        $requestData = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => true,
            'options' => array_merge($defaultOptions, $options),
        ];

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/generate", $requestData);

        if (!$response->successful()) {
            throw new \Exception('Ollama request failed: ' . $response->body());
        }

        $body = $response->body();
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $data = json_decode($line, true);
            if ($data && isset($data['response'])) {
                yield $data['response'];
            }

            if ($data && isset($data['done']) && $data['done']) {
                break;
            }
        }
    }

    /**
     * Test model with UE-specific prompt
     */
    public function testWithUEPrompt(string $model): array
    {
        $testPrompt = "You are SurrealPilot, an AI assistant for Unreal Engine development. " .
                     "A user asks: 'How do I create a Blueprint that spawns an actor when the player presses a key?' " .
                     "Provide a concise, step-by-step answer.";

        try {
            $response = '';
            foreach ($this->chat($model, $testPrompt) as $chunk) {
                $response .= $chunk;
            }

            return [
                'success' => true,
                'model' => $model,
                'response' => $response,
                'response_length' => strlen($response),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'model' => $model,
                'error' => $e->getMessage(),
            ];
        }
    }
}