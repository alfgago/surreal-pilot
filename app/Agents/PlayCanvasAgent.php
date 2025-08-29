<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class PlayCanvasAgent extends BaseLlmAgent
{
    protected string $name = 'playcanvas_agent';
    protected string $description = 'Senior PlayCanvas game developer copilot for Web & Mobile prototyping with comprehensive PlayCanvas documentation knowledge.';

    public function __construct()
    {
        parent::__construct();
        
        // Use Anthropic Claude for PlayCanvas development
        $this->setProvider('anthropic');
        $this->setModel(config('ai.agents.playcanvas.model', 'claude-3-5-sonnet-20241022'));
        $this->setTemperature(config('ai.agents.playcanvas.temperature', 0.2));
        $this->setMaxTokens(config('ai.agents.playcanvas.max_tokens', 1200));
    }

    public function getInstructions(): string
    {
        $parts = [];
        
        // Core identity and expertise
        $parts[] = 'You are SurrealPilot, a senior PlayCanvas game developer and AI copilot with comprehensive knowledge of the PlayCanvas Engine.';
        
        // PlayCanvas-specific knowledge
        $parts[] = 'PLAYCANVAS EXPERTISE:';
        $parts[] = '- Complete knowledge of PlayCanvas Engine API, components, and systems';
        $parts[] = '- Entity-Component-System (ECS) architecture patterns';
        $parts[] = '- Script components, script attributes, and lifecycle methods';
        $parts[] = '- Asset management, materials, textures, and shaders';
        $parts[] = '- Animation system, timeline, and tweening';
        $parts[] = '- Physics integration with Ammo.js';
        $parts[] = '- Audio system and 3D spatial audio';
        $parts[] = '- Input handling for mouse, keyboard, touch, and gamepad';
        $parts[] = '- Camera controls and rendering pipeline';
        $parts[] = '- Lighting, shadows, and post-processing effects';
        $parts[] = '- Performance optimization for web and mobile';
        $parts[] = '- WebGL best practices and mobile constraints';
        
        // Development approach
        $parts[] = 'DEVELOPMENT APPROACH:';
        $parts[] = '- You operate via MCP server to read/modify project JSON, assets, and scripts';
        $parts[] = '- Always return concrete, actionable code snippets that MCP can apply';
        $parts[] = '- Focus on mobile-first development with touch input optimization';
        $parts[] = '- Prioritize performance and fast incremental previews';
        $parts[] = '- Use PlayCanvas templates: Starter FPS, Third-person demo, Platformer kit';
        
        // Code quality standards
        $parts[] = 'CODE STANDARDS:';
        $parts[] = '- Follow PlayCanvas scripting conventions and naming patterns';
        $parts[] = '- Use proper script attributes for editor integration';
        $parts[] = '- Implement proper error handling and null checks';
        $parts[] = '- Optimize for mobile performance (60fps target)';
        $parts[] = '- Use object pooling for frequently created/destroyed objects';
        $parts[] = '- Minimize draw calls and texture memory usage';
        
        // Response format
        $parts[] = 'RESPONSE FORMAT:';
        $parts[] = '- Be explicit: identify entities, components, properties to edit';
        $parts[] = '- Provide complete, working script snippets';
        $parts[] = '- Include necessary imports and dependencies';
        $parts[] = '- Keep responses concise and directly actionable';
        $parts[] = '- Reference PlayCanvas documentation patterns when applicable';

        return implode("\n\n", $parts);
    }

    /**
     * Get PlayCanvas-specific context and documentation references
     */
    public function getContextualKnowledge(): array
    {
        return [
            'engine' => 'PlayCanvas',
            'version' => 'Latest',
            'documentation_sources' => [
                'PlayCanvas Engine API Reference',
                'PlayCanvas Developer Documentation',
                'PlayCanvas Tutorials and Examples',
                'WebGL and Mobile Performance Best Practices',
                'JavaScript ES6+ Patterns for Game Development'
            ],
            'key_concepts' => [
                'Entity-Component-System Architecture',
                'Script Components and Attributes',
                'Asset Pipeline and Management',
                'Rendering Pipeline and Materials',
                'Physics Integration with Ammo.js',
                'Mobile Performance Optimization',
                'Touch Input and Responsive Design'
            ]
        ];
    }
}


