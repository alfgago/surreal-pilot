<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ai.default_provider' => ['required', 'string', 'in:anthropic,openai,gemini,ollama'],
            'ai.temperature' => ['required', 'numeric', 'min:0', 'max:1'],
            'ai.stream_responses' => ['boolean'],
            'ai.save_history' => ['boolean'],
            'ui.theme' => ['nullable', 'string', 'in:light,dark,system'],
            'ui.compact_mode' => ['boolean'],
            'ui.show_line_numbers' => ['boolean'],
            'notifications.email' => ['boolean'],
            'notifications.browser' => ['boolean'],
            'notifications.chat_mentions' => ['boolean'],
            'notifications.game_updates' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ai.default_provider.required' => 'Please select a default AI provider.',
            'ai.default_provider.in' => 'Invalid AI provider selected.',
            'ai.temperature.required' => 'Temperature setting is required.',
            'ai.temperature.numeric' => 'Temperature must be a number.',
            'ai.temperature.min' => 'Temperature must be at least 0.',
            'ai.temperature.max' => 'Temperature cannot exceed 1.',
            'ui.theme.in' => 'Invalid theme selected.',
        ];
    }
}