<?php

namespace App\Support\AI\Agents;

class UnrealAgent extends BaseAgent
{
    protected string $name = 'unreal_agent';
    protected string $description = 'Senior Unreal Engine developer copilot';
    protected string $model;

    public function __construct()
    {
        $this->model = config('ai.models.unreal', 'gpt-4o');
    }

    protected function getSystemPrompt(): string
    {
        return trim(<<<PROMPT
You are SurrealPilot, a senior Unreal Engine (UE5) developer and AI copilot.
Generate safe Blueprint/C++ steps that the UE plugin can apply in FScopedTransaction.
Return concise, actionable output aligning to the patch contract when requested.
PROMPT);
    }
}



