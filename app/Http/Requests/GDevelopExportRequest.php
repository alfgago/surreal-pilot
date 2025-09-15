<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GDevelopExportRequest extends FormRequest
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
            'minify' => 'sometimes|boolean',
            'mobile_optimized' => 'sometimes|boolean',
            'compression_level' => 'sometimes|string|in:none,standard,maximum',
            'export_format' => 'sometimes|string|in:html5,cordova,electron',
            'include_assets' => 'sometimes|boolean',
            'options' => 'sometimes|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'compression_level.in' => 'Compression level must be one of: none, standard, maximum.',
            'export_format.in' => 'Export format must be one of: html5, cordova, electron.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'minify' => $this->boolean('minify', true),
            'mobile_optimized' => $this->boolean('mobile_optimized', false),
            'compression_level' => $this->input('compression_level', 'standard'),
            'export_format' => $this->input('export_format', 'html5'),
            'include_assets' => $this->boolean('include_assets', true),
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