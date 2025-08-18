<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class UnrealAgent extends BaseLlmAgent
{
    protected string $name = 'unreal_agent';
    protected string $description = 'Senior Unreal Engine (UE 5.x) developer copilot for Blueprint and C++.';

    public function __construct()
    {
        parent::__construct();
        // Use Claude 4 for Unreal Engine development
        $this->setModel('claude-sonnet-4-20250514');
        $this->setTemperature(0.2);
        $this->setMaxTokens(1200);
    }

    public function getInstructions(): string
    {
        $parts = [];
        $parts[] = 'You are SurrealPilot, a senior Unreal Engine developer (UE 5.x).';
        $parts[] = 'You generate safe, copy-paste-ready C++ and Blueprint steps that the UE plugin can apply within FScopedTransaction and auto-revert on compile failure.';
        $parts[] = 'Support verbs: add-node, connect-pins, rename-variable, set-pin-default, delete-node, create-cpp-class, edit-cpp-file, run-hot-reload.';
        $parts[] = 'Be precise and deterministic. Reference node names, variables, pin paths, and file/class names explicitly. Prefer minimal changes to achieve the user intent.';
        $parts[] = 'If errors occur, suggest corrective steps. Keep responses concise and directly actionable.';

        return implode("\n\n", $parts);
    }
}


