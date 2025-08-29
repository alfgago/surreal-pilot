<?php

namespace App\Http\Requests;

use App\Services\ValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ConversationRequest extends FormRequest
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
        $rules = [
            'title' => 'nullable|string|min:1|max:255',
            'description' => 'nullable|string|max:1000',
        ];

        // Add workspace_id validation for creation requests
        if ($this->isMethod('POST') && !$this->route('workspaceId')) {
            $rules['workspace_id'] = 'required|integer|exists:workspaces,id';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.min' => 'Conversation title must be at least 1 character long.',
            'title.max' => 'Conversation title cannot exceed 255 characters.',
            'description.max' => 'Conversation description cannot exceed 1000 characters.',
            'workspace_id.required' => 'Workspace ID is required.',
            'workspace_id.exists' => 'The specified workspace does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'conversation title',
            'description' => 'conversation description',
            'workspace_id' => 'workspace',
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
            'message' => 'Conversation validation failed.',
            'user_message' => 'Please check your conversation details and try again.',
            'data' => [
                'validation_errors' => $validator->errors(),
                'actions' => [
                    'fix_errors' => 'Correct the highlighted fields',
                    'shorten_title' => 'Use a shorter title',
                    'shorten_description' => 'Use a shorter description',
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
        // Trim whitespace from title and description
        if ($this->has('title')) {
            $this->merge([
                'title' => trim($this->input('title')) ?: null
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->input('description')) ?: null
            ]);
        }

        // Add workspace_id from route if available
        if ($this->route('workspaceId') && !$this->has('workspace_id')) {
            $this->merge([
                'workspace_id' => $this->route('workspaceId')
            ]);
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
        return $validationService->validateConversation($validated, $this->user());
    }
}