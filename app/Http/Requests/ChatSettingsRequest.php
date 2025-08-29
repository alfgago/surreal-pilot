<?php

namespace App\Http\Requests;

use App\Services\ValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ChatSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ai_model' => 'required|string|max:100',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'required|integer|min:1|max:8000',
            'streaming_enabled' => 'required|boolean',
            'engine_type' => 'nullable|string|in:playcanvas,unreal',
            'auto_save_conversations' => 'nullable|boolean',
            'show_token_usage' => 'nullable|boolean',
            'enable_code_highlighting' => 'nullable|boolean',
            'theme' => 'nullable|string|in:light,dark,auto',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ai_model.required' => 'Please select an AI model.',
            'ai_model.max' => 'AI model name cannot exceed 100 characters.',
            'temperature.required' => 'Temperature setting is required.',
            'temperature.numeric' => 'Temperature must be a valid number.',
            'temperature.min' => 'Temperature cannot be negative.',
            'temperature.max' => 'Temperature cannot exceed 2.0.',
            'max_tokens.required' => 'Maximum tokens setting is required.',
            'max_tokens.integer' => 'Maximum tokens must be a valid number.',
            'max_tokens.min' => 'Maximum tokens must be at least 1.',
            'max_tokens.max' => 'Maximum tokens cannot exceed 8000.',
            'streaming_enabled.required' => 'Streaming preference is required.',
            'streaming_enabled.boolean' => 'Streaming preference must be true or false.',
            'engine_type.in' => 'Engine type must be either PlayCanvas or Unreal Engine.',
            'auto_save_conversations.boolean' => 'Auto-save preference must be true or false.',
            'show_token_usage.boolean' => 'Token usage display preference must be true or false.',
            'enable_code_highlighting.boolean' => 'Code highlighting preference must be true or false.',
            'theme.in' => 'Theme must be one of: light, dark, or auto.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'ai_model' => 'AI model',
            'temperature' => 'temperature setting',
            'max_tokens' => 'maximum tokens',
            'streaming_enabled' => 'streaming preference',
            'engine_type' => 'engine type',
            'auto_save_conversations' => 'auto-save conversations',
            'show_token_usage' => 'show token usage',
            'enable_code_highlighting' => 'code highlighting',
            'theme' => 'theme preference',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'error' => 'validation_failed',
            'error_code' => 'VALIDATION_FAILED',
            'message' => 'Chat settings validation failed.',
            'user_message' => 'Please check your settings and try again.',
            'data' => [
                'validation_errors' => $validator->errors(),
                'limits' => [
                    'temperature_min' => 0,
                    'temperature_max' => 2.0,
                    'max_tokens_min' => 1,
                    'max_tokens_max' => 8000,
                ],
                'allowed_values' => [
                    'engine_type' => ['playcanvas', 'unreal'],
                    'theme' => ['light', 'dark', 'auto'],
                ],
                'actions' => [
                    'fix_errors' => 'Correct the highlighted fields',
                    'reset_defaults' => 'Reset to default settings',
                    'view_available_models' => '/api/chat/models',
                ],
            ],
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize engine type to lowercase
        if ($this->has('engine_type')) {
            $this->merge([
                'engine_type' => strtolower($this->input('engine_type'))
            ]);
        }

        // Normalize theme to lowercase
        if ($this->has('theme')) {
            $this->merge([
                'theme' => strtolower($this->input('theme'))
            ]);
        }

        // Ensure boolean values are properly cast
        $booleanFields = [
            'streaming_enabled',
            'auto_save_conversations',
            'show_token_usage',
            'enable_code_highlighting'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    $this->merge([
                        $field => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ]);
                }
            }
        }

        // Ensure numeric values are properly cast
        if ($this->has('temperature')) {
            $this->merge([
                'temperature' => (float) $this->input('temperature')
            ]);
        }

        if ($this->has('max_tokens')) {
            $this->merge([
                'max_tokens' => (int) $this->input('max_tokens')
            ]);
        }

        // Set defaults for optional fields
        $defaults = [
            'auto_save_conversations' => true,
            'show_token_usage' => false,
            'enable_code_highlighting' => true,
            'theme' => 'auto',
        ];

        foreach ($defaults as $field => $default) {
            if (!$this->has($field)) {
                $this->merge([$field => $default]);
            }
        }
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Use ValidationService for additional validation
        $validationService = app(ValidationService::class);
        return $validationService->validateChatSettings($validated, $this->user());
    }

    /**
     * Check if the AI model is available.
     */
    public function isModelAvailable(): bool
    {
        $model = $this->input('ai_model');
        
        // Get available models (this would typically come from a service)
        $availableModels = $this->getAvailableModels();
        
        return in_array($model, array_column($availableModels, 'id'));
    }

    /**
     * Get available AI models.
     */
    public function getAvailableModels(): array
    {
        // This would typically come from a service or config
        $models = [
            [
                'id' => 'gpt-4',
                'name' => 'GPT-4',
                'provider' => 'openai',
                'available' => true,
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'provider' => 'openai',
                'available' => true,
            ],
            [
                'id' => 'claude-3-sonnet',
                'name' => 'Claude 3 Sonnet',
                'provider' => 'anthropic',
                'available' => true,
            ],
        ];

        // Add AI_MODEL_PLAYCANVAS if set in environment
        $playcanvasModel = env('AI_MODEL_PLAYCANVAS');
        if ($playcanvasModel) {
            $models[] = [
                'id' => $playcanvasModel,
                'name' => 'PlayCanvas Model',
                'provider' => 'custom',
                'available' => true,
            ];
        }

        return $models;
    }

    /**
     * Get settings summary.
     */
    public function getSettingsSummary(): array
    {
        return [
            'model_selected' => $this->input('ai_model'),
            'temperature' => $this->input('temperature'),
            'max_tokens' => $this->input('max_tokens'),
            'streaming_enabled' => $this->input('streaming_enabled'),
            'engine_specific' => !empty($this->input('engine_type')),
            'has_custom_theme' => $this->input('theme') !== 'auto',
            'auto_save_enabled' => $this->input('auto_save_conversations', true),
        ];
    }
}