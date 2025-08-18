<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\LocalConfigManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private LocalConfigManager $configManager
    ) {}

    /**
     * Show the desktop chat interface
     */
    public function index(): View
    {
        return view('desktop.chat', [
            'title' => 'Chat - SurrealPilot',
            'providers' => $this->getAvailableProviders(),
        ]);
    }

    /**
     * Handle assist requests (proxy to main API or handle locally)
     */
    public function assist(Request $request): JsonResponse
    {
        try {
            // For desktop app, we can handle requests locally with stored API keys
            $config = $this->configManager->getConfig();
            
            // If we have local API keys, process locally
            if ($this->hasLocalApiKeys($config)) {
                return $this->processLocalAssist($request, $config);
            }
            
            // Otherwise, proxy to SaaS API if configured
            return $this->proxySaasRequest($request, 'assist');
            
        } catch (\Exception $e) {
            Log::error('Desktop assist error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'request_failed',
                'message' => 'Failed to process assist request',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle streaming chat requests
     */
    public function chat(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            try {
                $config = $this->configManager->getConfig();
                $provider = $request->input('provider', 'openai');
                $messages = $request->input('messages', []);
                $context = $request->input('context', []);
                
                // Route to appropriate provider
                if ($provider === 'ollama') {
                    $this->handleOllamaChat($messages, $context);
                } elseif ($this->hasLocalApiKeys($config)) {
                    // Use Vizra agents locally; stream chunks via SSE
                    $engine = strtolower($context['engine_type'] ?? 'unreal');
                    $agentClass = \App\Support\AI\AgentRouter::forEngine($engine);
                    $input = $this->buildUEContextPrompt($messages, $context);

                    // Sync default model/provider similar to API middleware
                    config([
                        'vizra-adk.default_provider' => $provider,
                        'vizra-adk.default_model' => $engine === 'playcanvas' ? config('ai.models.playcanvas') : config('ai.models.unreal'),
                    ]);

                    $executor = $agentClass::run($input)->streaming(true)->withContext($context);
                    $stream = $executor->go();

                    foreach ($stream as $chunk) {
                        $text = method_exists($chunk, 'getContent') ? $chunk->getContent() : ($chunk->text ?? (string) $chunk);
                        echo "data: " . json_encode([
                            'type' => 'message',
                            'content' => $text,
                            'timestamp' => now()->toISOString(),
                        ]) . "\n\n";
                        if (ob_get_level()) { ob_flush(); }
                        flush();
                    }

                    echo "data: " . json_encode([
                        'type' => 'complete',
                        'timestamp' => now()->toISOString(),
                    ]) . "\n\n";
                } else {
                    $this->handleSaasChat($provider, $messages, $context);
                }
                
            } catch (\Exception $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ]) . "\n\n";
                
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Handle Ollama chat with Qwen3
     */
    private function handleOllamaChat(array $messages, array $context): void
    {
        $config = $this->configManager->getConfig();
        $ollamaUrl = $config['ollama_url'] ?? 'http://localhost:11434';
        $model = $config['ollama_model'] ?? 'qwen2.5-coder:7b';
        
        // Build prompt for Qwen3 with UE context
        $prompt = $this->buildUEContextPrompt($messages, $context);
        
        try {
            $response = Http::timeout(120)->post("{$ollamaUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => true,
                'options' => [
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                    'max_tokens' => 4000,
                ]
            ]);
            
            if ($response->successful()) {
                $body = $response->body();
                $lines = explode("\n", $body);
                
                foreach ($lines as $line) {
                    if (trim($line)) {
                        $data = json_decode($line, true);
                        if ($data && isset($data['response'])) {
                            echo "data: " . json_encode([
                                'type' => 'message',
                                'content' => $data['response'],
                                'timestamp' => now()->toISOString(),
                            ]) . "\n\n";
                            
                            ob_flush();
                            flush();
                        }
                        
                        if ($data && isset($data['done']) && $data['done']) {
                            break;
                        }
                    }
                }
            } else {
                throw new \Exception('Ollama request failed: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            echo "data: " . json_encode([
                'type' => 'error',
                'message' => 'Ollama error: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]) . "\n\n";
        }
        
        echo "data: " . json_encode([
            'type' => 'complete',
            'timestamp' => now()->toISOString(),
        ]) . "\n\n";
        
        ob_flush();
        flush();
    }

    /**
     * Handle Prism-based chat for cloud providers
     */
    // Prism path removed; Vizra ADK agents are used for local execution
    private function handlePrismChat(string $provider, array $messages, array $context, array $config): void
    {
        throw new \RuntimeException('Prism path removed. Desktop now uses Vizra agents for local chat.');
    }

    /**
     * Handle SaaS chat
     */
    private function handleSaasChat(string $provider, array $messages, array $context): void
    {
        // Proxy to SaaS API
        $saasUrl = config('app.saas_url', 'https://api.surrealpilot.com');
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->configManager->getSaasToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ])->timeout(120)->post("{$saasUrl}/api/chat", [
                'provider' => $provider,
                'messages' => $messages,
                'context' => $context,
                'stream' => true,
            ]);
            
            if ($response->successful()) {
                echo $response->body();
            } else {
                throw new \Exception('SaaS API error: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            echo "data: " . json_encode([
                'type' => 'error',
                'message' => 'SaaS API error: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]) . "\n\n";
        }
        
        ob_flush();
        flush();
    }

    /**
     * Build UE-specific prompt for Ollama/Qwen3
     */
    private function buildUEContextPrompt(array $messages, array $context): string
    {
        $prompt = "You are SurrealPilot, an AI assistant specialized in Unreal Engine development. ";
        $prompt .= "You help with Blueprints, C++ code, scene editing, build errors, and UE best practices.\n\n";
        
        // Add context information
        if (!empty($context)) {
            $prompt .= "UNREAL ENGINE CONTEXT:\n";
            
            if (isset($context['ue_context'])) {
                $prompt .= "Current UE Context: " . $context['ue_context'] . "\n";
            }
            
            if (isset($context['source'])) {
                $prompt .= "Source: " . $context['source'] . "\n";
            }
            
            $prompt .= "\n";
        }
        
        // Add conversation history
        $prompt .= "CONVERSATION:\n";
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            $prompt .= strtoupper($role) . ": " . $content . "\n";
        }
        
        $prompt .= "\nASSISTANT: ";
        
        return $prompt;
    }

    /**
     * Enhance messages with UE context for Prism
     */
    private function enhanceMessagesWithUEContext(array $messages, array $context): array
    {
        if (empty($messages)) {
            return $messages;
        }
        
        // Add system message with UE context
        $systemMessage = [
            'role' => 'system',
            'content' => 'You are SurrealPilot, an AI assistant specialized in Unreal Engine development. ' .
                        'You help with Blueprints, C++ code, scene editing, build errors, and UE best practices.'
        ];
        
        // Add context to the first user message
        if (!empty($context) && isset($messages[0])) {
            $contextInfo = "\n\n[UE Context: " . json_encode($context) . "]";
            $messages[0]['content'] .= $contextInfo;
        }
        
        return array_merge([$systemMessage], $messages);
    }

    /**
     * Get available providers
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => $this->getAvailableProviders(),
            'default' => $this->configManager->getPreferredProvider(),
        ]);
    }

    /**
     * Get credit balance for desktop app
     */
    public function credits(): JsonResponse
    {
        try {
            // For desktop app, we'll simulate credit data
            // In a real implementation, this would connect to the SaaS API
            // or use local credit tracking
            
            $config = $this->configManager->getConfig();
            
            // Mock credit data for desktop
            return response()->json([
                'credits' => 2500,
                'plan' => 'Pro',
                'monthly_limit' => 10000,
                'usage_this_month' => 1200,
                'is_approaching_limit' => false,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'failed_to_load_credits',
                'message' => 'Unable to load credit information',
            ], 500);
        }
    }

    /**
     * Get available AI providers
     */
    private function getAvailableProviders(): array
    {
        $config = $this->configManager->getConfig();
        
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4', 'gpt-3.5-turbo', 'gpt-4-turbo'],
                'requires_key' => true,
                'available' => !empty($config['api_keys']['openai'] ?? null),
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
                'requires_key' => true,
                'available' => !empty($config['api_keys']['anthropic'] ?? null),
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-pro', 'gemini-pro-vision'],
                'requires_key' => true,
                'available' => !empty($config['api_keys']['gemini'] ?? null),
            ],
            'ollama' => [
                'name' => 'Ollama (Local)',
                'models' => [
                    'qwen2.5-coder:7b',
                    'qwen2.5-coder:14b',
                    'qwen2.5-coder:32b',
                    'codellama:7b',
                    'codellama:13b',
                    'deepseek-coder:6.7b',
                    'starcoder2:7b'
                ],
                'requires_key' => false,
                'available' => $this->checkOllamaAvailability(),
                'description' => 'Local AI models via Ollama - includes Qwen2.5-Coder optimized for code generation',
            ],
        ];
    }

    /**
     * Check if Ollama is available
     */
    private function checkOllamaAvailability(): bool
    {
        $config = $this->configManager->getConfig();
        $ollamaUrl = $config['ollama_url'] ?? 'http://localhost:11434';
        
        try {
            $response = Http::timeout(5)->get("{$ollamaUrl}/api/tags");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if we have local API keys configured
     */
    private function hasLocalApiKeys(array $config): bool
    {
        return !empty($config['api_keys']) && 
               count(array_filter($config['api_keys'])) > 0;
    }

    /**
     * Process assist request locally using stored API keys
     */
    private function processLocalAssist(Request $request, array $config): JsonResponse
    {
        // This will be implemented when we integrate with Prism-PHP
        // For now, return a placeholder response
        return response()->json([
            'message' => 'Local processing will be implemented with Prism-PHP integration',
            'provider' => $request->input('provider', 'openai'),
            'context' => $request->input('context'),
        ]);
    }

    /**
     * Proxy request to SaaS API
     */
    private function proxySaasRequest(Request $request, string $endpoint): JsonResponse
    {
        $saasUrl = config('app.saas_url', 'https://api.surrealpilot.com');
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->configManager->getSaasToken(),
                'Content-Type' => 'application/json',
            ])->post("{$saasUrl}/api/{$endpoint}", $request->all());
            
            return response()->json($response->json(), $response->status());
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'saas_proxy_failed',
                'message' => 'Failed to connect to SaaS API',
            ], 503);
        }
    }
}