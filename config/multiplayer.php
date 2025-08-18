<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS ECS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS ECS Fargate tasks used for multiplayer sessions.
    |
    */
    'ecs_cluster' => env('MULTIPLAYER_ECS_CLUSTER', 'playcanvas-multiplayer'),
    'task_definition' => env('MULTIPLAYER_TASK_DEFINITION', 'playcanvas-multiplayer:1'),
    'subnets' => env('MULTIPLAYER_SUBNETS', ''),
    'security_groups' => env('MULTIPLAYER_SECURITY_GROUPS', ''),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for multiplayer sessions.
    |
    */
    'default_max_players' => env('MULTIPLAYER_DEFAULT_MAX_PLAYERS', 8),
    'default_ttl_minutes' => env('MULTIPLAYER_DEFAULT_TTL_MINUTES', 40),
    'cleanup_interval_minutes' => env('MULTIPLAYER_CLEANUP_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for storing multiplayer server files and progress data.
    |
    */
    'storage_disk' => env('MULTIPLAYER_STORAGE_DISK', 'public'),
    'storage_path' => env('MULTIPLAYER_STORAGE_PATH', 'multiplayer'),
    'max_file_size' => env('MULTIPLAYER_MAX_FILE_SIZE', 10485760), // 10MB in bytes
    'allowed_extensions' => ['json', 'txt', 'dat', 'save'],

    /*
    |--------------------------------------------------------------------------
    | Ngrok Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for ngrok tunneling service.
    |
    */
    'ngrok_auth_token' => env('NGROK_AUTH_TOKEN'),
    'ngrok_region' => env('NGROK_REGION', 'us'),
    'ngrok_api_url' => env('NGROK_API_URL', 'https://api.ngrok.com'),

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the PlayCanvas multiplayer server container.
    |
    */
    'server_port' => env('MULTIPLAYER_SERVER_PORT', 3000),
    'server_memory' => env('MULTIPLAYER_SERVER_MEMORY', 1024),
    'server_cpu' => env('MULTIPLAYER_SERVER_CPU', 512),
];