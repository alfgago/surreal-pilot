<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\LocalConfigManager;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    public function __construct(
        private LocalConfigManager $configManager,
        private OllamaService $ollamaService
    ) {}

    /**
     * Get current desktop configuration
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = $this->configManager->getConfig();
            
            // Don't expose sensitive API keys in full
            if (isset($config['api_keys'])) {
                foreach ($config['api_keys'] as $provider => $key) {
                    if ($key) {
                        $config['api_keys'][$provider] = substr($key, 0, 8) . '...';
                    }
                }
            }
            
            return response()->json([
                'config' => $config,
                'server_port' => $this->configManager->getServerPort(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get desktop config: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'config_read_failed',
                'message' => 'Failed to read configuration',
            ], 500);
        }
    }

    /**
     * Update desktop configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_provider' => 'sometimes|string|in:openai,anthropic,gemini,ollama',
            'api_keys' => 'sometimes|array',
            'api_keys.openai' => 'sometimes|string|nullable',
            'api_keys.anthropic' => 'sometimes|string|nullable',
            'api_keys.gemini' => 'sometimes|string|nullable',
            'saas_token' => 'sometimes|string|nullable',
            'saas_url' => 'sometimes|url|nullable',
        ]);
        
        try {
            $this->configManager->updateConfig($validated);
            
            return response()->json([
                'message' => 'Configuration updated successfully',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update desktop config: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'config_update_failed',
                'message' => 'Failed to update configuration',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get server information
     */
    public function getServerInfo(): JsonResponse
    {
        return response()->json([
            'port' => $this->configManager->getServerPort(),
            'host' => '127.0.0.1',
            'url' => 'http://127.0.0.1:' . $this->configManager->getServerPort(),
            'status' => 'running',
            'version' => config('nativephp.version'),
        ]);
    }

    /**
     * Test connection to various services
     */
    public function testConnection(Request $request): JsonResponse
    {
        $service = $request->input('service', 'ollama');
        
        try {
            switch ($service) {
                case 'ollama':
                    return $this->testOllamaConnection();
                case 'saas':
                    return $this->testSaasConnection();
                default:
                    return response()->json([
                        'error' => 'invalid_service',
                        'message' => 'Unknown service to test',
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'connection_test_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Ollama local connection
     */
    private function testOllamaConnection(): JsonResponse
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get('http://localhost:11434/api/tags');
            
            if ($response->successful()) {
                return response()->json([
                    'status' => 'connected',
                    'message' => 'Ollama is running locally',
                    'models' => $response->json('models', []),
                ]);
            }
            
            return response()->json([
                'status' => 'failed',
                'message' => 'Ollama is not responding',
            ], 503);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Ollama is not available: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Test SaaS API connection
     */
    private function testSaasConnection(): JsonResponse
    {
        try {
            $config = $this->configManager->getConfig();
            $saasUrl = $config['saas_url'] ?? 'https://api.surrealpilot.com';
            $token = $config['saas_token'] ?? null;
            
            if (!$token) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'No SaaS token configured',
                ], 400);
            }
            
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->get($saasUrl . '/api/providers');
            
            if ($response->successful()) {
                return response()->json([
                    'status' => 'connected',
                    'message' => 'SaaS API is accessible',
                ]);
            }
            
            return response()->json([
                'status' => 'failed',
                'message' => 'SaaS API authentication failed',
            ], $response->status());
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'SaaS API is not available: ' . $e->getMessage(),
            ], 503);
        }
    }
}   
 /**
     * Setup Ollama for UE development
     */
    public function setupOllama(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'sometimes|url',
            'model' => 'sometimes|string',
            'enabled' => 'sometimes|boolean',
        ]);

        try {
            // Setup Ollama configuration
            $this->configManager->setupOllama($validated);
            
            // Test and setup Ollama
            $setupResult = $this->ollamaService->setupForUE();
            
            if ($setupResult['success']) {
                return response()->json([
                    'message' => 'Ollama setup completed successfully',
                    'setup_result' => $setupResult,
                ]);
            } else {
                return response()->json([
                    'error' => 'ollama_setup_failed',
                    'message' => $setupResult['message'],
                    'instructions' => $setupResult['instructions'] ?? null,
                    'manual_setup' => $setupResult['manual_setup'] ?? null,
                ], 422);
            }
            
        } catch (\Exception $e) {
            Log::error('Ollama setup failed: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'ollama_setup_error',
                'message' => 'Failed to setup Ollama',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Ollama status and available models
     */
    public function getOllamaStatus(): JsonResponse
    {
        try {
            $config = $this->configManager->getOllamaConfig();
            $isAvailable = $this->ollamaService->isAvailable();
            
            $status = [
                'config' => $config,
                'available' => $isAvailable,
                'models' => [],
                'recommended_models' => $this->ollamaService->getRecommendedModels(),
            ];
            
            if ($isAvailable) {
                $status['models'] = $this->ollamaService->getAvailableModels();
                
                // Test the configured model
                if (!empty($config['model'])) {
                    $testResult = $this->ollamaService->testWithUEPrompt($config['model']);
                    $status['model_test'] = $testResult;
                }
            }
            
            return response()->json($status);
            
        } catch (\Exception $e) {
            Log::error('Failed to get Ollama status: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'ollama_status_failed',
                'message' => 'Failed to get Ollama status',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}