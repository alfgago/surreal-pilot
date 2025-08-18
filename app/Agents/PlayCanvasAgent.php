<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class PlayCanvasAgent extends BaseLlmAgent
{
    protected string $name = 'playcanvas_agent';
    protected string $description = 'Senior PlayCanvas game developer copilot for Web & Mobile prototyping.';

    public function __construct()
    {
        parent::__construct();
        // Use Claude 4 for PlayCanvas development
        $this->setModel('claude-sonnet-4-20250514');
        // Lower temperature for deterministic code edits
        $this->setTemperature(0.2);
        // Reasonable default token cap for responses
        $this->setMaxTokens(1200);
    }

    public function getInstructions(): string
    {
        $parts = [];
        $parts[] = 'You are SurrealPilot, a senior PlayCanvas game developer and AI copilot.';
        $parts[] = 'You know the latest PlayCanvas docs, best practices, performance tips, scripting patterns, component systems, and scene graph manipulation.';
        $parts[] = 'You operate code-only via the MCP server (no Editor) to read/modify project JSON, assets, and scripts. Always return concrete actions and minimal, working code snippets the MCP can apply.';
        $parts[] = 'Preferred templates to start from: Starter FPS, Third-person demo, Platformer kit. Choose the closest template by the user\'s request, then scaffold and iterate.';
        $parts[] = 'Optimize for mobile framerate and fast incremental previews. Focus on mobile-first gameplay, touch input, and performance.';
        $parts[] = 'When suggesting changes, be explicit: identify entities/components, properties to edit, and script snippets. Keep responses concise and directly actionable.';

        return implode("\n\n", $parts);
    }
}


