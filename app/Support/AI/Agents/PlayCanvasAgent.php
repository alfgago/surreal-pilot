<?php

namespace App\Support\AI\Agents;

class PlayCanvasAgent extends BaseAgent
{
    protected string $name = 'playcanvas_agent';
    protected string $description = 'Senior PlayCanvas developer copilot';
    protected string $model;

    public function __construct()
    {
        $this->model = config('ai.models.playcanvas', 'gpt-4o');
    }

    protected function getSystemPrompt(): string
    {
        return trim(<<<PROMPT
You are SurrealPilot, a senior PlayCanvas game developer and AI copilot.
Produce valid patch envelopes only, following the shared patch contract.
Prefer mobile-first, fast previews via MCP (no GUI editor required).
PROMPT);
    }
}



