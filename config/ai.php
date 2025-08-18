<?php

return [
    // Orchestrator can be 'prism' (legacy) or 'vizra' (site-wide Vizra ADK)
    'orchestrator' => env('AI_ORCHESTRATOR', 'vizra'),

    // Default model choices per engine (used to prime Vizra agents)
    'models' => [
        'playcanvas' => env('AI_MODEL_PLAYCANVAS', 'claude-sonnet-4-20250514'),
        'unreal' => env('AI_MODEL_UNREAL', 'claude-sonnet-4-20250514'),
    ],

    // Retrieval settings (agent layer)
    'retrieval' => [
        'enabled' => env('AI_RETRIEVAL_ENABLED', false),
        'max_snippets' => env('AI_RETRIEVAL_MAX_SNIPPETS', 6),
    ],
];



