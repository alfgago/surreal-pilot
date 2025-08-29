<?php

namespace App\Http\Requests;

use App\Services\ValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class WorkspaceRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentCompany;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $company = auth()->user()->currentCompany;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9\s\-_\.]+$/',
                function ($attribute, $value, $fail) use ($company) {
                    if ($company) {
                        $exists = \App\Models\Workspace::where('company_id', $company->id)
                            ->where('name', $value)
                            ->exists();
                        if ($exists) {
                            $fail('A workspace with this name already exists in your company.');
                        }
                    }
                }
            ],
            'description' => 'nullable|string|max:500',
            'engine_type' => 'required|string|in:playcanvas,unreal',
            'template_id' => 'nullable|integer|exists:templates,id',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Workspace name is required.',
            'name.min' => 'Workspace name must be at least 2 characters long.',
            'name.max' => 'Workspace name cannot exceed 100 characters.',
            'name.regex' => 'Workspace name can only contain letters, numbers, spaces, hyphens, underscores, and periods.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'engine_type.required' => 'Please select an engine type.',
            'engine_type.in' => 'The selected engine type is invalid.',
            'template_id.exists' => 'The selected template does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'workspace name',
            'description' => 'workspace description',
            'engine_type' => 'engine type',
            'template_id' => 'template',
            'is_public' => 'public visibility',
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
            'message' => 'Workspace registration validation failed.',
            'user_message' => 'Please check your workspace details and try again.',
            'data' => [
                'validation_errors' => $validator->errors(),
                'actions' => [
                    'fix_errors' => 'Correct the highlighted fields',
                    'choose_different_name' => 'Try a different workspace name',
                    'view_existing_workspaces' => '/api/workspaces',
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

        // Trim whitespace from name and description
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->input('name'))
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->input('description'))
            ]);
        }

        // Set default for is_public
        if (!$this->has('is_public')) {
            $this->merge(['is_public' => false]);
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
        return $validationService->validateWorkspaceRegistration($validated, $this->user());
    }
}