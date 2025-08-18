<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by Sanctum middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider' => 'sometimes|string|in:openai,anthropic,gemini,ollama',
            'model' => 'sometimes|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'context' => 'sometimes|array',
            'context.blueprint' => 'sometimes|string',
            'context.errors' => 'sometimes|array',
            'context.selection' => 'sometimes|string',
            'context.workspace_id' => 'sometimes|integer|exists:workspaces,id',
            'context.conversation_id' => 'sometimes|integer|exists:chat_conversations,id',
            'max_tokens' => 'sometimes|integer|min:1|max:4096',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'stream' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'messages.required' => 'At least one message is required.',
            'messages.*.role.required' => 'Each message must have a role.',
            'messages.*.role.in' => 'Message role must be one of: system, user, assistant.',
            'messages.*.content.required' => 'Each message must have content.',
            'provider.in' => 'Provider must be one of: openai, anthropic, gemini, ollama.',
            'max_tokens.max' => 'Maximum tokens cannot exceed 4096.',
            'temperature.min' => 'Temperature must be at least 0.',
            'temperature.max' => 'Temperature cannot exceed 2.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values and back-compat for tests using 'prompt' instead of messages
        $messages = $this->input('messages');
        if (!$messages && $this->filled('prompt')) {
            $messages = [['role' => 'user', 'content' => (string) $this->input('prompt')]];
            $this->merge(['messages' => $messages]);
        }

        $this->merge([
            'stream' => $this->boolean('stream', true),
            'provider' => $this->input('provider', 'openai'),
            'max_tokens' => $this->input('max_tokens', 2048),
            'temperature' => $this->input('temperature', 0.7),
        ]);
    }

    /**
     * Get the validated data with defaults applied.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        // Ensure we have the resolved provider from middleware
        $validated['resolved_provider'] = $this->input('resolved_provider');
        $validated['original_provider'] = $this->input('original_provider');
        
        return $validated;
    }
}