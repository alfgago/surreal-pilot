<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ChatSettingsController extends Controller
{
    /**
     * Get user chat settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get settings from cache or use defaults
            $cacheKey = "chat_settings_user_{$user->id}";
            $settings = Cache::get($cacheKey, $this->getDefaultSettings());

            return response()->json([
                'success' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve chat settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save user chat settings.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'ai_model' => 'required|string|max:100',
                'temperature' => 'required|numeric|min:0|max:2',
                'max_tokens' => 'required|integer|min:1|max:8000',
                'streaming_enabled' => 'required|boolean',
                'engine_type' => 'nullable|string|in:playcanvas,unreal',
            ]);

            // Validate AI model is available
            $availableModels = $this->getAvailableModels();
            $modelIds = array_column($availableModels, 'id');
            
            if (!in_array($validated['ai_model'], $modelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid AI model selected',
                    'available_models' => $modelIds,
                ], 422);
            }

            // Save settings to cache
            $cacheKey = "chat_settings_user_{$user->id}";
            Cache::put($cacheKey, $validated, now()->addDays(30));

            return response()->json([
                'success' => true,
                'message' => 'Chat settings saved successfully',
                'settings' => $validated,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save chat settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available AI models.
     */
    public function getModels(Request $request): JsonResponse
    {
        try {
            $models = $this->getAvailableModels();

            return response()->json([
                'success' => true,
                'models' => $models,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available models',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset chat settings to defaults.
     */
    public function resetSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $defaultSettings = $this->getDefaultSettings();
            
            // Save default settings to cache
            $cacheKey = "chat_settings_user_{$user->id}";
            Cache::put($cacheKey, $defaultSettings, now()->addDays(30));

            return response()->json([
                'success' => true,
                'message' => 'Chat settings reset to defaults',
                'settings' => $defaultSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset chat settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get default chat settings.
     */
    private function getDefaultSettings(): array
    {
        return [
            'ai_model' => env('AI_MODEL_PLAYCANVAS', config('vizra-adk.default_model', 'claude-sonnet-4-20250514')),
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'streaming_enabled' => true,
            'engine_type' => 'playcanvas', // Default to PlayCanvas
        ];
    }

    /**
     * Get available AI models.
     */
    private function getAvailableModels(): array
    {
        $models = [];

        // Add PlayCanvas model from environment
        $playCanvasModel = env('AI_MODEL_PLAYCANVAS');
        if ($playCanvasModel) {
            $models[] = [
                'id' => $playCanvasModel,
                'name' => 'PlayCanvas Optimized (' . $playCanvasModel . ')',
                'provider' => 'anthropic',
                'description' => 'Specialized model optimized for PlayCanvas game development, web games, and JavaScript frameworks',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => 'playcanvas',
                'recommended' => true,
                'features' => ['Game Development', 'JavaScript', 'Web Technologies', 'PlayCanvas API']
            ];
        }

        // Add Unreal model from environment
        $unrealModel = env('AI_MODEL_UNREAL');
        if ($unrealModel) {
            $models[] = [
                'id' => $unrealModel,
                'name' => 'Unreal Engine Optimized (' . $unrealModel . ')',
                'provider' => 'anthropic',
                'description' => 'Specialized model optimized for Unreal Engine development, C++, and Blueprint scripting',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => 'unreal',
                'recommended' => true,
                'features' => ['Game Development', 'C++', 'Blueprint', 'Unreal Engine API']
            ];
        }

        // Add Anthropic models
        $anthropicModels = [
            [
                'id' => 'claude-3-5-sonnet-20241022',
                'name' => 'Claude 3.5 Sonnet (Latest)',
                'provider' => 'anthropic',
                'description' => 'Most capable Claude model with excellent coding and reasoning abilities',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Advanced Reasoning', 'Code Generation', 'Analysis']
            ],
            [
                'id' => 'claude-3-opus-20240229',
                'name' => 'Claude 3 Opus',
                'provider' => 'anthropic',
                'description' => 'Most powerful Claude model for complex tasks',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Complex Reasoning', 'Creative Writing', 'Advanced Analysis']
            ],
            [
                'id' => 'claude-3-sonnet-20240229',
                'name' => 'Claude 3 Sonnet',
                'provider' => 'anthropic',
                'description' => 'Balanced Claude model for most tasks',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['General Purpose', 'Code Generation', 'Analysis']
            ],
            [
                'id' => 'claude-3-haiku-20240307',
                'name' => 'Claude 3 Haiku',
                'provider' => 'anthropic',
                'description' => 'Fastest Claude model for quick responses',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Fast Response', 'Lightweight', 'Quick Tasks']
            ]
        ];

        // Add OpenAI models
        $openaiModels = [
            [
                'id' => 'gpt-4-turbo-preview',
                'name' => 'GPT-4 Turbo',
                'provider' => 'openai',
                'description' => 'Latest GPT-4 model with improved performance',
                'available' => !empty(env('OPENAI_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Advanced Reasoning', 'Code Generation', 'Large Context']
            ],
            [
                'id' => 'gpt-4',
                'name' => 'GPT-4',
                'provider' => 'openai',
                'description' => 'OpenAI GPT-4 model for complex tasks',
                'available' => !empty(env('OPENAI_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Advanced Reasoning', 'Code Generation', 'Analysis']
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'provider' => 'openai',
                'description' => 'Fast and efficient OpenAI model',
                'available' => !empty(env('OPENAI_API_KEY')),
                'engine_type' => null,
                'recommended' => false,
                'features' => ['Fast Response', 'General Purpose', 'Cost Effective']
            ]
        ];

        // Add all models, avoiding duplicates
        $allModels = array_merge($anthropicModels, $openaiModels);
        foreach ($allModels as $model) {
            if (!in_array($model['id'], array_column($models, 'id'))) {
                $models[] = $model;
            }
        }

        // Sort models: recommended first, then by provider
        usort($models, function ($a, $b) {
            if ($a['recommended'] && !$b['recommended']) return -1;
            if (!$a['recommended'] && $b['recommended']) return 1;
            if ($a['provider'] !== $b['provider']) {
                return strcmp($a['provider'], $b['provider']);
            }
            return strcmp($a['name'], $b['name']);
        });

        return $models;
    }

    /**
     * Get settings for a specific engine type.
     */
    public function getEngineSettings(Request $request, string $engineType): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Validate engine type
            if (!in_array($engineType, ['playcanvas', 'unreal'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid engine type',
                ], 422);
            }

            // Get user settings
            $cacheKey = "chat_settings_user_{$user->id}";
            $settings = Cache::get($cacheKey, $this->getDefaultSettings());

            // Get engine-specific models
            $availableModels = $this->getAvailableModels();
            $engineModels = array_filter($availableModels, function ($model) use ($engineType) {
                return $model['engine_type'] === $engineType || $model['engine_type'] === null;
            });

            // Set default model for engine if not already set
            if ($engineType === 'playcanvas' && env('AI_MODEL_PLAYCANVAS')) {
                $settings['recommended_model'] = env('AI_MODEL_PLAYCANVAS');
            } elseif ($engineType === 'unreal' && env('AI_MODEL_UNREAL')) {
                $settings['recommended_model'] = env('AI_MODEL_UNREAL');
            }

            return response()->json([
                'success' => true,
                'engine_type' => $engineType,
                'settings' => $settings,
                'available_models' => array_values($engineModels),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve engine settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user API keys (masked for security).
     */
    public function getApiKeys(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get user API keys from cache
            $cacheKey = "api_keys_user_{$user->id}";
            $apiKeys = Cache::get($cacheKey, []);

            // Mask the keys for security
            $maskedKeys = [];
            foreach ($apiKeys as $provider => $key) {
                if (!empty($key)) {
                    $maskedKeys[$provider] = $this->maskApiKey($key);
                }
            }

            // Add system-level key availability
            $systemKeys = [
                'anthropic' => !empty(env('ANTHROPIC_API_KEY')),
                'openai' => !empty(env('OPENAI_API_KEY')),
            ];

            return response()->json([
                'success' => true,
                'user_keys' => $maskedKeys,
                'system_keys_available' => $systemKeys,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve API keys',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save user API keys.
     */
    public function saveApiKeys(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'anthropic_key' => 'nullable|string|max:200',
                'openai_key' => 'nullable|string|max:200',
            ]);

            // Validate API key formats
            if (!empty($validated['anthropic_key']) && !str_starts_with($validated['anthropic_key'], 'sk-ant-')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Anthropic API key format',
                ], 422);
            }

            if (!empty($validated['openai_key']) && !str_starts_with($validated['openai_key'], 'sk-')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OpenAI API key format',
                ], 422);
            }

            // Save API keys to cache (encrypted)
            $cacheKey = "api_keys_user_{$user->id}";
            $apiKeys = [];
            
            if (!empty($validated['anthropic_key'])) {
                $apiKeys['anthropic'] = encrypt($validated['anthropic_key']);
            }
            
            if (!empty($validated['openai_key'])) {
                $apiKeys['openai'] = encrypt($validated['openai_key']);
            }

            Cache::put($cacheKey, $apiKeys, now()->addDays(90));

            return response()->json([
                'success' => true,
                'message' => 'API keys saved successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save API keys',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete user API keys.
     */
    public function deleteApiKeys(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'providers' => 'required|array',
                'providers.*' => 'string|in:anthropic,openai',
            ]);

            // Get current API keys
            $cacheKey = "api_keys_user_{$user->id}";
            $apiKeys = Cache::get($cacheKey, []);

            // Remove specified providers
            foreach ($validated['providers'] as $provider) {
                unset($apiKeys[$provider]);
            }

            // Update cache
            Cache::put($cacheKey, $apiKeys, now()->addDays(90));

            return response()->json([
                'success' => true,
                'message' => 'API keys deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete API keys',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mask API key for display.
     */
    private function maskApiKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}
