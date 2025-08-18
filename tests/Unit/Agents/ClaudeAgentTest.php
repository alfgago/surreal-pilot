<?php

namespace Tests\Unit\Agents;

use Tests\TestCase;
use App\Agents\PlayCanvasAgent;
use App\Agents\UnrealAgent;

class ClaudeAgentTest extends TestCase
{
    public function test_playcanvas_agent_uses_claude_4()
    {
        $agent = new PlayCanvasAgent();

        // Test that the agent is configured to use Claude 4
        $this->assertStringContainsString('claude', $agent->getModel());
        $this->assertEquals(0.2, $agent->getTemperature());
        $this->assertEquals(1200, $agent->getMaxTokens());
    }

    public function test_unreal_agent_uses_claude_4()
    {
        $agent = new UnrealAgent();

        // Test that the agent is configured to use Claude 4
        $this->assertStringContainsString('claude', $agent->getModel());
        $this->assertEquals(0.2, $agent->getTemperature());
        $this->assertEquals(1200, $agent->getMaxTokens());
    }

    public function test_playcanvas_agent_instructions_are_comprehensive()
    {
        $agent = new PlayCanvasAgent();
        $instructions = $agent->getInstructions();

        // Verify key PlayCanvas concepts are included
        $this->assertStringContainsString('PlayCanvas', $instructions);
        $this->assertStringContainsString('MCP server', $instructions);
        $this->assertStringContainsString('mobile', $instructions);
        $this->assertStringContainsString('performance', $instructions);
    }

    public function test_unreal_agent_instructions_are_comprehensive()
    {
        $agent = new UnrealAgent();
        $instructions = $agent->getInstructions();

        // Verify key Unreal concepts are included
        $this->assertStringContainsString('Unreal Engine', $instructions);
        $this->assertStringContainsString('Blueprint', $instructions);
        $this->assertStringContainsString('C++', $instructions);
        $this->assertStringContainsString('FScopedTransaction', $instructions);
    }

    public function test_agents_have_proper_names_and_descriptions()
    {
        $playcanvasAgent = new PlayCanvasAgent();
        $unrealAgent = new UnrealAgent();

        // Test agent names
        $this->assertEquals('playcanvas_agent', $playcanvasAgent->getName());
        $this->assertEquals('unreal_agent', $unrealAgent->getName());

        // Test descriptions contain relevant keywords
        $this->assertStringContainsString('PlayCanvas', $playcanvasAgent->getDescription());
        $this->assertStringContainsString('Unreal Engine', $unrealAgent->getDescription());
    }
}
