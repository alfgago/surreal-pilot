<?php

namespace App\Http\Requests;

use App\Services\ValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class GameRequest extends FormRequest
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
            'title' => 'required|string|min:1|max:255',
            'description' => 'nullable|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:chat_conversations,id',
            'preview_url' => 'nullable|url|max:500',
            'published_url' => 'nullable|url|max:500',
            'thumbnail_url' => 'nullable|url|max:500',
            'metadata' => 'nullable|array',
            'metadata.engine_version' => 'nullable|string|max:50',
            'metadata.build_version' => 'nullable|string|max:50',
            'metadata.tags' => 'nullable|array',
            'metadata.tags.*' => 'string|max:50',
            'metadata.difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'metadata.genre' => 'nullable|string|max:100',
            'metadata.platform' => 'nullable|array',
            'metadata.platform.*' => 'string|in:web,mobile,desktop',
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
            'title.required' => 'Game title is required.',
            'title.min' => 'Game title must be at least 1 character long.',
            'title.max' => 'Game title cannot exceed 255 characters.',
            'description.max' => 'Game description cannot exceed 1000 characters.',
            'conversation_id.exists' => 'The specified conversation does not exist.',
            'preview_url.url' => 'Preview URL must be a valid URL.',
            'published_url.url' => 'Published URL must be a valid URL.',
            'thumbnail_url.url' => 'Thumbnail URL must be a valid URL.',
            'workspace_id.required' => 'Workspace ID is required.',
            'workspace_id.exists' => 'The specified workspace does not exist.',
            'metadata.engine_version.max' => 'Engine version cannot exceed 50 characters.',
            'metadata.build_version.max' => 'Build version cannot exceed 50 characters.',
            'metadata.tags.array' => 'Tags must be an array.',
            'metadata.tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'metadata.difficulty.in' => 'Difficulty must be beginner, intermediate, or advanced.',
            'metadata.genre.max' => 'Genre cannot exceed 100 characters.',
            'metadata.platform.array' => 'Platform must be an array.',
            'metadata.platform.*.in' => 'Platform must be web, mobile, or desktop.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'game title',
            'description' => 'game description',
            'conversation_id' => 'conversation',
            'preview_url' => 'preview URL',
            'published_url' => 'published URL',
            'thumbnail_url' => 'thumbnail URL',
            'workspace_id' => 'workspace',
            'metadata.engine_version' => 'engine version',
            'metadata.build_version' => 'build version',
            'metadata.tags' => 'tags',
            'metadata.difficulty' => 'difficulty level',
            'metadata.genre' => 'game genre',
            'metadata.platform' => 'target platforms',
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
            'message' => 'Game validation failed.',
            'user_message' => 'Please check your game details and try again.',
            'data' => [
                'validation_errors' => $validator->errors(),
                'limits' => [
                    'max_title_length' => 255,
                    'max_description_length' => 1000,
                    'max_url_length' => 500,
                    'max_tag_length' => 50,
                ],
                'allowed_values' => [
                    'difficulty' => ['beginner', 'intermediate', 'advanced'],
                    'platform' => ['web', 'mobile', 'desktop'],
                ],
                'actions' => [
                    'fix_errors' => 'Correct the highlighted fields',
                    'shorten_title' => 'Use a shorter title',
                    'shorten_description' => 'Use a shorter description',
                    'check_urls' => 'Verify all URLs are valid',
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
                'title' => trim($this->input('title'))
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

        // Normalize metadata
        if ($this->has('metadata')) {
            $metadata = $this->input('metadata', []);
            
            // Normalize difficulty
            if (isset($metadata['difficulty'])) {
                $metadata['difficulty'] = strtolower($metadata['difficulty']);
            }
            
            // Normalize platforms
            if (isset($metadata['platform']) && is_array($metadata['platform'])) {
                $metadata['platform'] = array_map('strtolower', $metadata['platform']);
            }
            
            // Clean up tags
            if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                $metadata['tags'] = array_map('trim', $metadata['tags']);
                $metadata['tags'] = array_filter($metadata['tags']); // Remove empty tags
            }
            
            $this->merge(['metadata' => $metadata]);
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
        return $validationService->validateGame($validated, $this->user());
    }

    /**
     * Check if URLs are accessible.
     */
    public function validateUrls(): array
    {
        $urls = array_filter([
            'preview_url' => $this->input('preview_url'),
            'published_url' => $this->input('published_url'),
            'thumbnail_url' => $this->input('thumbnail_url'),
        ]);

        $validationService = app(ValidationService::class);
        $results = [];

        foreach ($urls as $type => $url) {
            $results[$type] = [
                'url' => $url,
                'valid' => $validationService->validateUrl($url),
            ];
        }

        return $results;
    }

    /**
     * Get game metadata summary.
     */
    public function getMetadataSummary(): array
    {
        $metadata = $this->input('metadata', []);
        
        return [
            'has_engine_version' => !empty($metadata['engine_version']),
            'has_build_version' => !empty($metadata['build_version']),
            'tag_count' => count($metadata['tags'] ?? []),
            'has_difficulty' => !empty($metadata['difficulty']),
            'has_genre' => !empty($metadata['genre']),
            'platform_count' => count($metadata['platform'] ?? []),
        ];
    }
}