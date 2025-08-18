<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'cloudfront_distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID'),
        'cloudfront_domain' => env('AWS_CLOUDFRONT_DOMAIN'),
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'username' => env('GITHUB_USERNAME'),
        'pages_repo' => env('GITHUB_PAGES_REPO', 'surrealpilot-builds'),
    ],

    'publishing' => [
        'method' => env('PUBLISHING_METHOD', 's3'), // 's3' or 'github'
        'mobile_optimization' => env('PUBLISHING_MOBILE_OPTIMIZATION', true),
        'compression' => env('PUBLISHING_COMPRESSION', true),
    ],

];
