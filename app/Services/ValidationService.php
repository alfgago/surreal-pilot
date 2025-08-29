<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ValidationService
{
    public function __construct(
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Validate engine selection data.
     */
    public function validateEngineSelection(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $validator = Validator::make($data, [
                'engine_type' => 'required|string|in:playcanvas,unreal'
            ], [
                'engine_type.required' => 'Please select an engine type.',
                'engine_type.in' => 'The selected engine type is invalid. Please choose either PlayCanvas or Unreal Engine.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('engine_selection', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Engine selection validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $data]
                );
            }
            throw $e;
        }
    }

    /**
     * Validate workspace registration data.
     */
    public function validateWorkspaceRegistration(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $company = $user?->currentCompany;

            $validator = Validator::make($data, [
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
            ], [
                'name.required' => 'Workspace name is required.',
                'name.min' => 'Workspace name must be at least 2 characters long.',
                'name.max' => 'Workspace name cannot exceed 100 characters.',
                'name.regex' => 'Workspace name can only contain letters, numbers, spaces, hyphens, underscores, and periods.',
                'description.max' => 'Description cannot exceed 500 characters.',
                'engine_type.required' => 'Please select an engine type.',
                'engine_type.in' => 'The selected engine type is invalid.',
                'template_id.exists' => 'The selected template does not exist.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('workspace_registration', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Workspace registration validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $this->sanitizeData($data)]
                );
            }
            throw $e;
        }
    }

    /**
     * Validate conversation data.
     */
    public function validateConversation(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $validator = Validator::make($data, [
                'title' => 'nullable|string|min:1|max:255',
                'description' => 'nullable|string|max:1000',
                'workspace_id' => 'sometimes|required|integer|exists:workspaces,id',
            ], [
                'title.min' => 'Conversation title must be at least 1 character long.',
                'title.max' => 'Conversation title cannot exceed 255 characters.',
                'description.max' => 'Conversation description cannot exceed 1000 characters.',
                'workspace_id.required' => 'Workspace ID is required.',
                'workspace_id.exists' => 'The specified workspace does not exist.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('conversation', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Conversation validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $this->sanitizeData($data)]
                );
            }
            throw $e;
        }
    }

    /**
     * Validate chat message data.
     */
    public function validateChatMessage(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $validator = Validator::make($data, [
                'role' => 'required|string|in:user,assistant,system',
                'content' => 'required|string|min:1|max:50000',
                'metadata' => 'nullable|array',
                'metadata.tokens' => 'nullable|integer|min:0',
                'metadata.model' => 'nullable|string|max:100',
                'metadata.temperature' => 'nullable|numeric|min:0|max:2',
                'metadata.max_tokens' => 'nullable|integer|min:1|max:8000',
                'metadata.streaming' => 'nullable|boolean',
            ], [
                'role.required' => 'Message role is required.',
                'role.in' => 'Message role must be one of: user, assistant, or system.',
                'content.required' => 'Message content is required.',
                'content.min' => 'Message content must be at least 1 character long.',
                'content.max' => 'Message content cannot exceed 50,000 characters.',
                'metadata.array' => 'Metadata must be a valid object.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('chat_message', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Chat message validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $this->sanitizeData($data)]
                );
            }
            throw $e;
        }
    }

    /**
     * Validate game data.
     */
    public function validateGame(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $validator = Validator::make($data, [
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
            ], [
                'title.required' => 'Game title is required.',
                'title.min' => 'Game title must be at least 1 character long.',
                'title.max' => 'Game title cannot exceed 255 characters.',
                'description.max' => 'Game description cannot exceed 1000 characters.',
                'conversation_id.exists' => 'The specified conversation does not exist.',
                'preview_url.url' => 'Preview URL must be a valid URL.',
                'published_url.url' => 'Published URL must be a valid URL.',
                'thumbnail_url.url' => 'Thumbnail URL must be a valid URL.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('game', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Game validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $this->sanitizeData($data)]
                );
            }
            throw $e;
        }
    }

    /**
     * Validate chat settings data.
     */
    public function validateChatSettings(array $data, ?\App\Models\User $user = null): array
    {
        try {
            $validator = Validator::make($data, [
                'ai_model' => 'required|string|max:100',
                'temperature' => 'required|numeric|min:0|max:2',
                'max_tokens' => 'required|integer|min:1|max:8000',
                'streaming_enabled' => 'required|boolean',
                'engine_type' => 'nullable|string|in:playcanvas,unreal',
                'auto_save_conversations' => 'nullable|boolean',
                'show_token_usage' => 'nullable|boolean',
                'enable_code_highlighting' => 'nullable|boolean',
                'theme' => 'nullable|string|in:light,dark,auto',
            ], [
                'ai_model.required' => 'Please select an AI model.',
                'ai_model.max' => 'AI model name cannot exceed 100 characters.',
                'temperature.required' => 'Temperature setting is required.',
                'temperature.numeric' => 'Temperature must be a valid number.',
                'temperature.min' => 'Temperature cannot be negative.',
                'temperature.max' => 'Temperature cannot exceed 2.0.',
                'max_tokens.required' => 'Maximum tokens setting is required.',
                'max_tokens.integer' => 'Maximum tokens must be a valid number.',
                'max_tokens.min' => 'Maximum tokens must be at least 1.',
                'max_tokens.max' => 'Maximum tokens cannot exceed 8000.',
                'streaming_enabled.required' => 'Streaming preference is required.',
                'streaming_enabled.boolean' => 'Streaming preference must be true or false.',
                'engine_type.in' => 'Engine type must be either PlayCanvas or Unreal Engine.',
                'theme.in' => 'Theme must be one of: light, dark, or auto.',
            ]);

            if ($validator->fails()) {
                $this->logValidationError('chat_settings', $validator->errors()->toArray(), $user);
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            return $validator->validated();

        } catch (\Throwable $e) {
            if (!($e instanceof \Illuminate\Validation\ValidationException)) {
                $this->errorMonitoring->trackError(
                    'validation_service_error',
                    "Chat settings validation failed: {$e->getMessage()}",
                    $user,
                    $user?->currentCompany,
                    ['data' => $this->sanitizeData($data)]
                );
            }
            throw $e;
        }
    }

    /**
     * Log validation errors.
     */
    private function logValidationError(string $type, array $errors, ?\App\Models\User $user = null): void
    {
        try {
            Log::warning("Validation failed: {$type}", [
                'validation_type' => $type,
                'errors' => $errors,
                'user_id' => $user?->id,
                'company_id' => $user?->currentCompany?->id,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            // Don't let logging errors break validation
        }
    }

    /**
     * Sanitize data for logging (remove sensitive information).
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'api_key',
            'anthropic_key',
            'openai_key',
            'token',
            'secret',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Validate URL format and accessibility.
     */
    public function validateUrl(string $url, array $allowedDomains = []): bool
    {
        try {
            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }

            // Check allowed domains if specified
            if (!empty($allowedDomains)) {
                $parsedUrl = parse_url($url);
                $domain = $parsedUrl['host'] ?? '';
                
                $domainAllowed = false;
                foreach ($allowedDomains as $allowedDomain) {
                    if (str_ends_with($domain, $allowedDomain)) {
                        $domainAllowed = true;
                        break;
                    }
                }
                
                if (!$domainAllowed) {
                    return false;
                }
            }

            // Check for dangerous protocols
            $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
            foreach ($dangerousProtocols as $protocol) {
                if (str_starts_with(strtolower($url), $protocol)) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('URL validation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate file upload.
     */
    public function validateFileUpload(\Illuminate\Http\UploadedFile $file, array $allowedTypes = [], int $maxSize = 10485760): array
    {
        try {
            $errors = [];

            // Check file size (default 10MB)
            if ($file->getSize() > $maxSize) {
                $errors[] = "File size exceeds maximum allowed size of " . number_format($maxSize / 1048576, 1) . "MB";
            }

            // Check file type
            if (!empty($allowedTypes)) {
                $mimeType = $file->getMimeType();
                if (!in_array($mimeType, $allowedTypes)) {
                    $errors[] = "File type '{$mimeType}' is not allowed";
                }
            }

            // Check for malicious files
            $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'pif', 'vbs', 'js'];
            $extension = strtolower($file->getClientOriginalExtension());
            if (in_array($extension, $dangerousExtensions)) {
                $errors[] = "File extension '{$extension}' is not allowed for security reasons";
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $extension,
                ]
            ];

        } catch (\Throwable $e) {
            Log::error('File validation failed', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'errors' => ['File validation failed'],
                'file_info' => []
            ];
        }
    }
}