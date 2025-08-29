<?php

namespace App\Http\Requests;

use App\Services\ValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class EngineSelectionRequest extends FormRequest
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
            'engine_type' => 'required|string|in:playcanvas,unreal'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'engine_type.required' => 'Please select an engine type.',
            'engine_type.in' => 'The selected engine type is invalid. Please choose either PlayCanvas or Unreal Engine.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'engine_type' => 'engine type',
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
            'message' => 'Engine selection validation failed.',
            'user_message' => 'Please select a valid engine type.',
            'data' => [
                'validation_errors' => $validator->errors(),
                'available_engines' => ['playcanvas', 'unreal'],
                'actions' => [
                    'select_engine' => 'Choose either PlayCanvas or Unreal Engine',
                    'view_engines' => '/api/engines',
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
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Use ValidationService for additional validation
        $validationService = app(ValidationService::class);
        return $validationService->validateEngineSelection($validated, $this->user());
    }
}