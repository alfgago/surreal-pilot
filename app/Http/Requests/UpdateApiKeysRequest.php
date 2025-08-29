<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeysRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only company owners can update API keys
        return $this->user()->currentCompany && 
               $this->user()->currentCompany->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'openai_api_key' => ['nullable', 'string', 'regex:/^sk-[a-zA-Z0-9]{48,}$/'],
            'anthropic_api_key' => ['nullable', 'string', 'regex:/^sk-ant-[a-zA-Z0-9\-_]{95,}$/'],
            'gemini_api_key' => ['nullable', 'string', 'min:20'],
            'playcanvas_api_key' => ['nullable', 'string', 'min:10'],
            'playcanvas_project_id' => ['nullable', 'string', 'min:5'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'openai_api_key.regex' => 'OpenAI API key must start with "sk-" and be at least 48 characters long.',
            'anthropic_api_key.regex' => 'Anthropic API key must start with "sk-ant-" and be properly formatted.',
            'gemini_api_key.min' => 'Gemini API key must be at least 20 characters long.',
            'playcanvas_api_key.min' => 'PlayCanvas API key must be at least 10 characters long.',
            'playcanvas_project_id.min' => 'PlayCanvas Project ID must be at least 5 characters long.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'openai_api_key' => 'OpenAI API Key',
            'anthropic_api_key' => 'Anthropic API Key',
            'gemini_api_key' => 'Gemini API Key',
            'playcanvas_api_key' => 'PlayCanvas API Key',
            'playcanvas_project_id' => 'PlayCanvas Project ID',
        ];
    }
}