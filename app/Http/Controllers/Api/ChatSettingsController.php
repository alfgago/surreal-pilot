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
            'ai_model' => config('vizra-adk.default_model', 'claude-sonnet-4-20250514'),
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'streaming_enabled' => true,
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
                'name' => 'PlayCanvas Model (' . $playCanvasModel . ')',
                'provider' => 'anthropic',
                'description' => 'Optimized for PlayCanvas game development',
                'available' => true,
                'engine_type' => 'playcanvas',
            ];
        }

        // Add Unreal model from environment
        $unrealModel = env('AI_MODEL_UNREAL');
        if ($unrealModel) {
            $models[] = [
                'id' => $unrealModel,
                'name' => 'Unreal Engine Model (' . $unrealModel . ')',
                'provider' => 'anthropic',
                'description' => 'Optimized for Unreal Engine development',
                'available' => true,
                'engine_type' => 'unreal',
            ];
        }

        // Add default Vizra model
        $defaultModel = config('vizra-adk.default_model');
        if ($defaultModel && !in_array($defaultModel, array_column($models, 'id'))) {
            $models[] = [
                'id' => $defaultModel,
                'name' => 'Default Model (' . $defaultModel . ')',
                'provider' => config('vizra-adk.default_provider', 'anthropic'),
                'description' => 'Default AI model for general assistance',
                'available' => true,
                'engine_type' => null,
            ];
        }

        // Add common models
        $commonModels = [
            [
                'id' => 'gpt-4',
                'name' => 'GPT-4',
                'provider' => 'openai',
                'description' => 'OpenAI GPT-4 model',
                'available' => !empty(env('OPENAI_API_KEY')),
                'engine_type' => null,
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'provider' => 'openai',
                'description' => 'OpenAI GPT-3.5 Turbo model',
                'available' => !empty(env('OPENAI_API_KEY')),
                'engine_type' => null,
            ],
            [
                'id' => 'claude-3-opus',
                'name' => 'Claude 3 Opus',
                'provider' => 'anthropic',
                'description' => 'Anthropic Claude 3 Opus model',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
            ],
            [
                'id' => 'claude-3-sonnet',
                'name' => 'Claude 3 Sonnet',
                'provider' => 'anthropic',
                'description' => 'Anthropic Claude 3 Sonnet model',
                'available' => !empty(env('ANTHROPIC_API_KEY')),
                'engine_type' => null,
            ],
        ];

        // Add common models that aren't already in the list
        foreach ($commonModels as $commonModel) {
            if (!in_array($commonModel['id'], array_column($models, 'id'))) {
                $models[] = $commonModel;
            }
        }

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
}
