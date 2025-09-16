<?php

return [
    // Always use Vizra ADK with DeepSeek
    'orchestrator' => 'vizra',
    'provider' => env('AI_PROVIDER', 'deepseek'),
    'model' => env('AI_MODEL', 'deepseek-chat'),

    // Engine-specific Vizra agents
    'agents' => [
        'playcanvas' => [
            'class' => \App\Agents\PlayCanvasAgent::class,
            'model' => env('AI_MODEL_PLAYCANVAS', 'deepseek-chat'),
            'temperature' => 0.2,
            'max_tokens' => 1200,
        ],
        'unreal' => [
            'class' => \App\Agents\UnrealAgent::class,
            'model' => env('AI_MODEL_UNREAL', 'deepseek-chat'),
            'temperature' => 0.2,
            'max_tokens' => 1200,
        ],
        'gdevelop' => [
            'class' => \App\Agents\GDevelopAgent::class,
            'model' => env('AI_MODEL_GDEVELOP', 'deepseek-chat'),
            'temperature' => 0.2,
            'max_tokens' => 1200,
        ],
    ],

    // Retrieval settings (agent layer)
    'retrieval' => [
        'enabled' => env('AI_RETRIEVAL_ENABLED', true),
        'max_snippets' => env('AI_RETRIEVAL_MAX_SNIPPETS', 6),
    ],
];



