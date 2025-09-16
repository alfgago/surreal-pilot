<?php

namespace App\Support\AI;

use App\Agents\GDevelopAgent;
use App\Agents\PlayCanvasAgent;
use App\Agents\UnrealAgent;

class AgentRouter
{
    public static function forEngine(string $engine): string
    {
        return match ($engine) {
            'gdevelop' => GDevelopAgent::class,
            'playcanvas' => PlayCanvasAgent::class,
            'unreal' => UnrealAgent::class,
            default => UnrealAgent::class,
        };
    }
}



