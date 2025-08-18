<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used when
    | no specific provider is requested. This should be one of the providers
    | configured in the "providers" array below.
    |
    */

    'default' => env('PRISM_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Fallback Chain
    |--------------------------------------------------------------------------
    |
    | When a provider fails or is unavailable, the system will attempt to
    | use providers in this fallback order. Remove providers from this list
    | if you don't want them used as fallbacks.
    |
    */

    'fallback_chain' => [
        'openai',
        'anthropic',
        'gemini',
        'ollama',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers for your application. Each
    | provider has its own configuration options and API credentials.
    |
    */

    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => env('OPENAI_TIMEOUT', 30),
            'max_retries' => env('OPENAI_MAX_RETRIES', 3),
            'models' => [
                'default' => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
                'available' => [
                    'gpt-4',
                    'gpt-4-turbo',
                    'gpt-3.5-turbo',
                    'gpt-4o',
                    'gpt-4o-mini',
                ],
            ],
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
            'max_retries' => env('ANTHROPIC_MAX_RETRIES', 3),
            'models' => [
                'default' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-20250514'),
                'available' => [
                    'claude-3-opus-20240229',
                    'claude-3-sonnet-20240229',
                    'claude-3-haiku-20240307',
                    'claude-3-5-sonnet-20241022',
                    'claude-sonnet-4-20250514',
                ],
            ],
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => env('GEMINI_TIMEOUT', 30),
            'max_retries' => env('GEMINI_MAX_RETRIES', 3),
            'models' => [
                'default' => env('GEMINI_DEFAULT_MODEL', 'gemini-1.5-pro'),
                'available' => [
                    'gemini-1.5-pro',
                    'gemini-1.5-flash',
                    'gemini-pro',
                ],
            ],
        ],

        'ollama' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'timeout' => env('OLLAMA_TIMEOUT', 60),
            'max_retries' => env('OLLAMA_MAX_RETRIES', 2),
            'models' => [
                'default' => env('OLLAMA_DEFAULT_MODEL', 'llama3.1'),
                'available' => [
                    'llama3.1',
                    'llama3.1:70b',
                    'codellama',
                    'mistral',
                    'phi3',
                ],
            ],
            'health_check' => [
                'enabled' => env('OLLAMA_HEALTH_CHECK', true),
                'endpoint' => '/api/tags',
                'timeout' => 5,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Counting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for token counting and usage tracking. This is used
    | for credit deduction and usage analytics.
    |
    */

    'token_counting' => [
        'enabled' => env('PRISM_TOKEN_COUNTING', true),
        'method' => env('PRISM_TOKEN_COUNT_METHOD', 'tiktoken'), // tiktoken, estimate, provider
        'fallback_estimate' => [
            'chars_per_token' => 4, // Rough estimate: 1 token ≈ 4 characters
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting per provider to avoid hitting API limits.
    |
    */

    'rate_limiting' => [
        'enabled' => env('PRISM_RATE_LIMITING', true),
        'per_provider' => [
            'openai' => [
                'requests_per_minute' => env('OPENAI_RPM', 60),
                'tokens_per_minute' => env('OPENAI_TPM', 90000),
            ],
            'anthropic' => [
                'requests_per_minute' => env('ANTHROPIC_RPM', 60),
                'tokens_per_minute' => env('ANTHROPIC_TPM', 100000),
            ],
            'gemini' => [
                'requests_per_minute' => env('GEMINI_RPM', 60),
                'tokens_per_minute' => env('GEMINI_TPM', 32000),
            ],
            'ollama' => [
                'requests_per_minute' => env('OLLAMA_RPM', 30),
                'tokens_per_minute' => env('OLLAMA_TPM', 10000),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for streaming responses, which is essential for the
    | real-time chat functionality.
    |
    */

    'streaming' => [
        'enabled' => env('PRISM_STREAMING', true),
        'chunk_size' => env('PRISM_STREAM_CHUNK_SIZE', 1024),
        'timeout' => env('PRISM_STREAM_TIMEOUT', 120),
        'buffer_size' => env('PRISM_STREAM_BUFFER_SIZE', 8192),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for AI provider interactions, useful for debugging
    | and monitoring.
    |
    */

    'logging' => [
        'enabled' => env('PRISM_LOGGING', true),
        'channel' => env('PRISM_LOG_CHANNEL', 'stack'),
        'level' => env('PRISM_LOG_LEVEL', 'info'),
        'log_requests' => env('PRISM_LOG_REQUESTS', false),
        'log_responses' => env('PRISM_LOG_RESPONSES', false),
        'log_tokens' => env('PRISM_LOG_TOKENS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for provider responses to improve performance
    | and reduce API costs.
    |
    */

    'cache' => [
        'enabled' => env('PRISM_CACHE_ENABLED', false),
        'store' => env('PRISM_CACHE_STORE', 'default'),
        'ttl' => env('PRISM_CACHE_TTL', 3600), // 1 hour
        'key_prefix' => env('PRISM_CACHE_PREFIX', 'prism:'),
    ],
];
