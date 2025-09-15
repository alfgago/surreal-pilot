<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GDevelop Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GDevelop CLI integration and game development features.
    |
    */

    'enabled' => env('GDEVELOP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | CLI Paths
    |--------------------------------------------------------------------------
    |
    | Paths to GDevelop CLI tools. These should be globally installed via npm.
    |
    */

    'cli_path' => env('GDEVELOP_CLI_PATH', 'gdexport'),
    'core_tools_path' => env('GDEVELOP_CORE_TOOLS_PATH', 'gdcore-tools'),

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    |
    | Paths for storing GDevelop game sessions, templates, and exports.
    | These are relative to the storage directory.
    |
    */

    'templates_path' => env('GDEVELOP_TEMPLATES_PATH', 'gdevelop/templates'),
    'sessions_path' => env('GDEVELOP_SESSIONS_PATH', 'gdevelop/sessions'),
    'exports_path' => env('GDEVELOP_EXPORTS_PATH', 'gdevelop/exports'),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for game development sessions.
    |
    */

    'max_session_size' => env('GDEVELOP_MAX_SESSION_SIZE', '100MB'),
    'session_timeout' => env('GDEVELOP_SESSION_TIMEOUT', '24h'),

    /*
    |--------------------------------------------------------------------------
    | Build Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for game builds and exports.
    |
    */

    'build_timeout' => 300, // 5 minutes
    'preview_timeout' => 120, // 2 minutes (cache timeout in minutes)
    'max_concurrent_builds' => 3,

    /*
    |--------------------------------------------------------------------------
    | Preview Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTML5 preview generation and serving.
    |
    */

    'preview' => [
        'cache_timeout' => env('GDEVELOP_PREVIEW_CACHE_TIMEOUT', 120), // minutes
        'max_file_size' => env('GDEVELOP_PREVIEW_MAX_FILE_SIZE', '10MB'),
        'allowed_extensions' => ['html', 'htm', 'js', 'css', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'mp3', 'wav', 'ogg', 'mp4', 'webm'],
        'cleanup_interval' => env('GDEVELOP_PREVIEW_CLEANUP_INTERVAL', '1 hour'),
        'enable_caching' => env('GDEVELOP_PREVIEW_ENABLE_CACHING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Game Templates
    |--------------------------------------------------------------------------
    |
    | Available game templates for quick project initialization.
    |
    */

    'templates' => [
        'platformer' => [
            'name' => 'Platformer Game',
            'description' => 'A basic platformer game with physics and controls',
            'file' => 'platformer.json'
        ],
        'tower_defense' => [
            'name' => 'Tower Defense',
            'description' => 'A tower defense game with towers and enemies',
            'file' => 'tower_defense.json'
        ],
        'puzzle' => [
            'name' => 'Puzzle Game',
            'description' => 'A logic-based puzzle game',
            'file' => 'puzzle.json'
        ],
        'arcade' => [
            'name' => 'Arcade Game',
            'description' => 'A fast-paced arcade-style game',
            'file' => 'arcade.json'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for game exports and ZIP generation.
    |
    */

    'export_timeout' => env('GDEVELOP_EXPORT_TIMEOUT', 30), // seconds
    'max_export_size' => env('GDEVELOP_MAX_EXPORT_SIZE', 100 * 1024 * 1024), // 100MB
    'export_cleanup_hours' => env('GDEVELOP_EXPORT_CLEANUP_HOURS', 24), // hours

    /*
    |--------------------------------------------------------------------------
    | Export Options
    |--------------------------------------------------------------------------
    |
    | Default options for game exports.
    |
    */

    'export_defaults' => [
        'minify' => true,
        'mobile_optimized' => false,
        'include_assets' => true,
        'compression_level' => 'standard'
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Recovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling and recovery mechanisms.
    |
    */

    'error_recovery' => [
        'max_retries' => env('GDEVELOP_MAX_RETRIES', 3),
        'retry_delay_seconds' => env('GDEVELOP_RETRY_DELAY', 2),
        'backoff_multiplier' => env('GDEVELOP_BACKOFF_MULTIPLIER', 2),
        'enable_fallback_suggestions' => env('GDEVELOP_ENABLE_FALLBACK', true),
        'error_tracking_duration' => env('GDEVELOP_ERROR_TRACKING_DURATION', '24 hours'),
        'system_health_checks' => [
            'cli_availability' => true,
            'disk_space' => true,
            'memory_usage' => true,
            'active_sessions' => true,
            'error_rate' => true
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific GDevelop features.
    |
    */

    'features' => [
        'preview_generation' => true,
        'export_generation' => true,
        'template_system' => true,
        'ai_integration' => true,
        'mobile_optimization' => true,
        'error_recovery' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different game engines in SurrealPilot.
    |
    */

    'engines' => [
        'gdevelop_enabled' => env('GDEVELOP_ENABLED', false),
        'playcanvas_enabled' => env('PLAYCANVAS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for performance optimization and monitoring.
    |
    */

    'performance' => [
        // Process Pool Configuration
        'process_pool_size' => env('GDEVELOP_PROCESS_POOL_SIZE', 3),
        'process_timeout' => env('GDEVELOP_PROCESS_TIMEOUT', 300), // 5 minutes
        'process_pool_enabled' => env('GDEVELOP_PROCESS_POOL_ENABLED', true),

        // Caching Configuration
        'cache_enabled' => env('GDEVELOP_CACHE_ENABLED', true),
        'template_cache_ttl' => env('GDEVELOP_TEMPLATE_CACHE_TTL', 3600), // 1 hour
        'game_structure_cache_ttl' => env('GDEVELOP_GAME_STRUCTURE_CACHE_TTL', 1800), // 30 minutes
        'validation_cache_ttl' => env('GDEVELOP_VALIDATION_CACHE_TTL', 600), // 10 minutes
        'assets_cache_ttl' => env('GDEVELOP_ASSETS_CACHE_TTL', 7200), // 2 hours

        // Async Processing Configuration
        'async_processing_enabled' => env('GDEVELOP_ASYNC_PROCESSING_ENABLED', true),
        'export_queue' => env('GDEVELOP_EXPORT_QUEUE', 'gdevelop-exports'),
        'preview_queue' => env('GDEVELOP_PREVIEW_QUEUE', 'gdevelop-previews'),
        'queue_retry_attempts' => env('GDEVELOP_QUEUE_RETRY_ATTEMPTS', 3),
        'queue_retry_delay' => env('GDEVELOP_QUEUE_RETRY_DELAY', 60), // seconds

        // Performance Monitoring Configuration
        'monitoring_enabled' => env('GDEVELOP_MONITORING_ENABLED', true),
        'metrics_ttl' => env('GDEVELOP_METRICS_TTL', 86400), // 24 hours
        'metrics_history_limit' => env('GDEVELOP_METRICS_HISTORY_LIMIT', 1000),
        'performance_alerts_enabled' => env('GDEVELOP_PERFORMANCE_ALERTS_ENABLED', true),
        'slow_operation_threshold' => env('GDEVELOP_SLOW_OPERATION_THRESHOLD', 30), // seconds

        // Resource Limits
        'max_concurrent_operations' => env('GDEVELOP_MAX_CONCURRENT_OPERATIONS', 5),
        'memory_limit' => env('GDEVELOP_MEMORY_LIMIT', '512M'),
        'disk_space_threshold' => env('GDEVELOP_DISK_SPACE_THRESHOLD', '1GB'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Validation
    |--------------------------------------------------------------------------
    |
    | Settings for validating GDevelop configuration and setup.
    |
    */

    'validation' => [
        'check_cli_availability' => true,
        'check_storage_paths' => true,
        'check_permissions' => true,
        'check_dependencies' => true,
        'validate_templates' => true,
        'health_check_timeout' => 30, // seconds
    ]
];