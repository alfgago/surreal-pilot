<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GDevelopChatRequest extends FormRequest
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
            'message' => 'required|string|min:1|max:2000',
            'session_id' => 'sometimes|string|uuid',
            'workspace_id' => 'sometimes|integer|exists:workspaces,id',
            'template' => 'sometimes|array',
            'template.name' => 'sometimes|string|in:basic,platformer,tower-defense,puzzle,arcade',
            'template.properties' => 'sometimes|array',
            'options' => 'sometimes|array',
            'options.game_type' => 'sometimes|string|in:basic,platformer,tower-defense,puzzle,arcade',
            'options.mobile_optimized' => 'sometimes|boolean',
            'options.target_device' => 'sometimes|string|in:desktop,mobile,tablet',
            'options.control_scheme' => 'sometimes|string|in:virtual_dpad,touch_direct,drag_drop,touch_gesture',
            'options.orientation' => 'sometimes|string|in:portrait,landscape,default',
            'options.touch_controls' => 'sometimes|boolean',
            'options.responsive_ui' => 'sometimes|boolean',
            'options.preserve_existing' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'A message is required to process the chat request.',
            'message.min' => 'Message must be at least 1 character long.',
            'message.max' => 'Message cannot exceed 2000 characters.',
            'session_id.uuid' => 'Session ID must be a valid UUID.',
            'workspace_id.exists' => 'The specified workspace does not exist.',
            'template.name.in' => 'Template name must be one of: basic, platformer, tower-defense, puzzle, arcade.',
            'options.game_type.in' => 'Game type must be one of: basic, platformer, tower-defense, puzzle, arcade.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'options' => array_merge([
                'mobile_optimized' => false,
                'target_device' => 'desktop',
                'control_scheme' => 'touch_direct',
                'orientation' => 'default',
                'touch_controls' => false,
                'responsive_ui' => false,
                'preserve_existing' => true,
            ], $this->input('options', []))
        ]);
    }

    /**
     * Get the validated data with defaults applied.
     */
    public function getValidatedData(): array
    {
        return $this->validated();
    }
}