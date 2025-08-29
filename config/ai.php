<?php

return [
    // Always use Vizra ADK with Anthropic Claude
    'orchestrator' => 'vizra',
    'provider' => 'anthropic',
    'model' => env('AI_MODEL', 'claude-3-5-sonnet-20241022'),

    // Engine-specific Vizra agents
    'agents' => [
        'playcanvas' => [
            'class' => \App\Agents\PlayCanvasAgent::class,
            'model' => env('AI_MODEL_PLAYCANVAS', 'claude-3-5-sonnet-20241022'),
            'temperature' => 0.2,
            'max_tokens' => 1200,
        ],
        'unreal' => [
            'class' => \App\Agents\UnrealAgent::class,
            'model' => env('AI_MODEL_UNREAL', 'claude-3-5-sonnet-20241022'),
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



