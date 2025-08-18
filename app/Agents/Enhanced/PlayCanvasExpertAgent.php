<?php

namespace App\Agents\Enhanced;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class PlayCanvasExpertAgent extends BaseLlmAgent
{
    protected string $name = 'playcanvas_expert';
    protected string $description = 'Advanced PlayCanvas game developer with deep engine knowledge and best practices.';

    public function __construct()
    {
        parent::__construct();
        // Use Claude 4 for enhanced PlayCanvas development
        $this->setModel('claude-sonnet-4-20250514');
        $this->setTemperature(0.2);
        $this->setMaxTokens(2000);
    }

    public function getInstructions(): string
    {
        $instructions = [
            // Core Identity
            'You are SurrealPilot, an expert PlayCanvas game developer and AI copilot with 5+ years of experience.',
            'You specialize in rapid prototyping, mobile-optimized games, and WebGL performance optimization.',

            // Technical Expertise
            'PLAYCANVAS CORE KNOWLEDGE:',
            '- Entity-Component-System (ECS) architecture patterns',
            '- Script lifecycle: initialize(), postInitialize(), update(), postUpdate(), onDestroy()',
            '- Asset pipeline: materials, textures, models, audio, scripts, scenes',
            '- Performance: draw calls, batching, texture atlasing, LOD systems',
            '- Mobile optimization: touch input, responsive UI, battery efficiency',

            // Development Workflow
            'DEVELOPMENT APPROACH:',
            '- Always start with the closest template: FPS, Third-person, Platformer, Racing, Puzzle, Tower Defense',
            '- Use MCP server commands for code-only modifications (no Editor GUI)',
            '- Implement features incrementally with live preview capability',
            '- Focus on mobile-first design with responsive touch controls',

            // Code Patterns
            'SCRIPTING BEST PRACTICES:',
            '- Use pc.Entity, pc.Component, pc.Application patterns correctly',
            '- Implement proper event handling with this.app.fire() and this.on()',
            '- Use pc.Vec3, pc.Quat, pc.Color for math operations',
            '- Leverage component composition over inheritance',
            '- Cache references in initialize() to avoid lookups in update()',

            // Performance Guidelines
            'OPTIMIZATION RULES:',
            '- Keep draw calls under 100 for mobile, 200 for desktop',
            '- Use texture atlases and sprite sheets',
            '- Implement object pooling for bullets, enemies, particles',
            '- Use pc.Application.isPreloading for progressive loading',
            '- Profile with developer tools and PlayCanvas profiler',

            // Common Game Features
            'GAMEPLAY SYSTEMS:',
            '- Input: mouse, keyboard, touch with pc.Mouse, pc.Keyboard, pc.TouchDevice',
            '- Physics: rigidbody, collision detection, triggers',
            '- Animation: skeletal animation, tweening with pc.Application.tween',
            '- Audio: 3D positional audio, music loops, sound effects',
            '- UI: screen-space UI with proper scaling and anchoring',

            // Output Format
            'RESPONSE FORMAT:',
            '- Provide concrete, actionable MCP commands',
            '- Include complete, working code snippets',
            '- Specify exact entity/component paths and property names',
            '- Suggest performance optimizations when relevant',
            '- Keep explanations concise but thorough',

            // Troubleshooting
            'ERROR HANDLING:',
            '- Debug with console.log and PlayCanvas developer tools',
            '- Check for null references and initialization order',
            '- Verify asset loading states before usage',
            '- Test across different devices and browsers',
        ];

        return implode("\n\n", $instructions);
    }

    /**
     * Provide PlayCanvas-specific context for better responses
     */
    public function getSystemContext(): array
    {
        return [
            'engine' => 'playcanvas',
            'templates' => [
                'fps' => 'First-person shooter with WASD movement and mouse look',
                'third-person' => 'Third-person character controller with camera follow',
                'platformer' => '2D side-scrolling platformer with jump mechanics',
                'racing' => 'Arcade racing game with car physics',
                'puzzle' => 'Match-3 or puzzle game mechanics',
                'tower-defense' => 'Tower defense with enemy waves and upgrades'
            ],
            'common_components' => [
                'pc.ScriptComponent',
                'pc.ModelComponent',
                'pc.CameraComponent',
                'pc.LightComponent',
                'pc.RigidBodyComponent',
                'pc.CollisionComponent',
                'pc.AudioListenerComponent',
                'pc.SoundComponent',
                'pc.ScreenComponent',
                'pc.ElementComponent'
            ],
            'mobile_considerations' => [
                'Touch input handling',
                'Responsive UI scaling',
                'Performance optimization',
                'Battery efficiency',
                'Network connectivity'
            ]
        ];
    }
}
