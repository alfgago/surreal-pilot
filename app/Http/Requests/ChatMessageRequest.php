<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:10000',
            'conversation_id' => 'required|integer|exists:chat_conversations,id',
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'include_context' => 'boolean',
            'stream' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Message content is required.',
            'message.max' => 'Message content cannot exceed 10,000 characters.',
            'conversation_id.required' => 'Conversation ID is required.',
            'conversation_id.exists' => 'The specified conversation does not exist.',
            'workspace_id.required' => 'Workspace ID is required.',
            'workspace_id.exists' => 'The specified workspace does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'include_context' => $this->boolean('include_context', true),
            'stream' => $this->boolean('stream', true),
        ]);
    }
}