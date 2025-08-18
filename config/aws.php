<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS services used by the application.
    |
    */

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
    'session_token' => env('AWS_SESSION_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | ECS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS ECS services.
    |
    */
    'ecs' => [
        'cluster' => env('AWS_ECS_CLUSTER'),
        'task_definition' => env('AWS_ECS_TASK_DEFINITION'),
        'subnets' => env('AWS_ECS_SUBNETS'),
        'security_groups' => env('AWS_ECS_SECURITY_GROUPS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | S3 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS S3 services.
    |
    */
    's3' => [
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    ],
];