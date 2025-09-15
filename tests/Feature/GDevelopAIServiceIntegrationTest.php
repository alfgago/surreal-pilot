<?php

namespace Tests\Feature;

use App\Services\GDevelopAIService;
use App\Services\GDevelopTemplateService;
use App\Services\GDevelopJsonValidator;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GDevelopAIServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopAIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiService = new GDevelopAIService(
            new GDevelopTemplateService(),
            new GDevelopJsonValidator()
        );
    }

    public function test_generates_valid_tower_defense_game()
    {
        $request = "Create a tower defense game with towers that shoot at enemies";
        
        $result = $this->aiService->generateGameFromRequest($request);

        // Verify basic structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('objects', $result);
        $this->assertArrayHasKey('layouts', $result);
        $this->assertArrayHasKey('variables', $result);

        // Verify game properties
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('projectUuid', $result['properties']);
        $this->assertStringContainsString('Tower', $result['properties']['name']);

        // Verify objects were created
        $this->assertNotEmpty($result['objects']);
        
        // Check for tower and enemy objects
        $objectNames = array_column($result['objects'], 'name');
        $this->assertContains('Tower', $objectNames);
        $this->assertContains('Enemy', $objectNames);

        // Verify layouts
        $this->assertNotEmpty($result['layouts']);
        $this->assertArrayHasKey('name', $result['layouts'][0]);
        $this->assertArrayHasKey('layers', $result['layouts'][0]);

        // Verify variables
        $this->assertNotEmpty($result['variables']);
        $variableNames = array_column($result['variables'], 'name');
        $this->assertContains('Score', $variableNames);
    }

    public function test_generates_valid_platformer_game()
    {
        $request = "Make a platformer game with a player that can jump";
        
        $result = $this->aiService->generateGameFromRequest($request);

        // Verify basic structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('properties', $result);
        $this->assertStringContainsString('Platformer', $result['properties']['name']);

        // Check for player object
        $objectNames = array_column($result['objects'], 'name');
        $this->assertContains('Player', $objectNames);

        // Find player object and check for platformer behavior
        $playerObject = null;
        foreach ($result['objects'] as $object) {
            if ($object['name'] === 'Player') {
                $playerObject = $object;
                break;
            }
        }

        $this->assertNotNull($playerObject);
        $this->assertArrayHasKey('behaviors', $playerObject);
        
        // Should have platformer behavior (check for any platformer behavior type)
        $behaviorTypes = array_column($playerObject['behaviors'], 'type');
        $hasPlatformerBehavior = false;
        foreach ($behaviorTypes as $behaviorType) {
            if (str_contains($behaviorType, 'PlatformerObject') || str_contains($behaviorType, 'PlatformBehavior')) {
                $hasPlatformerBehavior = true;
                break;
            }
        }
        $this->assertTrue($hasPlatformerBehavior, 'Player should have a platformer behavior');
    }

    public function test_modifies_existing_game_correctly()
    {
        // First create a game
        $initialRequest = "Create a simple arcade game";
        $initialGame = $this->aiService->generateGameFromRequest($initialRequest);

        // Then modify it
        $modificationRequest = "Add an enemy that moves around";
        $modifiedGame = $this->aiService->modifyGameFromRequest($modificationRequest, $initialGame);

        // Verify the game was modified
        $this->assertIsArray($modifiedGame);
        $this->assertArrayHasKey('objects', $modifiedGame);

        // Should have more objects than before (or at least the same)
        $this->assertGreaterThanOrEqual(count($initialGame['objects']), count($modifiedGame['objects']));

        // Check if enemy was added
        $objectNames = array_column($modifiedGame['objects'], 'name');
        $this->assertContains('Enemy', $objectNames);
    }

    public function test_generates_events_for_shooting_mechanic()
    {
        $gameLogic = "When the player presses space, create a bullet";
        $gameObjects = [
            ['name' => 'Player', 'type' => 'Sprite'],
            ['name' => 'Bullet', 'type' => 'Sprite']
        ];

        $events = $this->aiService->generateGDevelopEvents($gameLogic, $gameObjects);

        $this->assertIsArray($events);
        
        // If events were generated, verify structure
        if (!empty($events)) {
            $this->assertArrayHasKey('type', $events[0]);
            $this->assertArrayHasKey('conditions', $events[0]);
            $this->assertArrayHasKey('actions', $events[0]);
        }
    }

    public function test_handles_complex_game_request()
    {
        $request = "Create a tower defense game called 'Super Defense' with 3 different tower types, enemies that spawn in waves, and a scoring system";
        
        $result = $this->aiService->generateGameFromRequest($request);

        // Verify game name was extracted
        $this->assertEquals('Super Defense', $result['properties']['name']);

        // Verify objects were created
        $this->assertNotEmpty($result['objects']);
        
        // Should have tower and enemy objects
        $objectNames = array_column($result['objects'], 'name');
        $this->assertContains('Tower', $objectNames);
        $this->assertContains('Enemy', $objectNames);

        // Should have scoring variables
        $variableNames = array_column($result['variables'], 'name');
        $this->assertContains('Score', $variableNames);
    }

    public function test_generates_mobile_optimized_game()
    {
        $request = "Create a puzzle game optimized for mobile devices";
        
        $result = $this->aiService->generateGameFromRequest($request);

        // Should use portrait orientation for puzzle games
        $this->assertEquals('portrait', $result['properties']['orientation']);

        // Verify it's a valid game structure
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('objects', $result);
        $this->assertArrayHasKey('layouts', $result);
    }

    public function test_preserves_existing_game_elements_during_modification()
    {
        // Create initial game with specific elements
        $initialGame = [
            'properties' => [
                'name' => 'Test Game',
                'version' => '1.0.0',
                'projectUuid' => '12345678-1234-1234-1234-123456789abc'
            ],
            'objects' => [
                [
                    'name' => 'Player',
                    'type' => 'Sprite',
                    'variables' => [
                        ['name' => 'Health', 'type' => 'number', 'value' => 100]
                    ]
                ]
            ],
            'layouts' => [
                [
                    'name' => 'MainScene',
                    'layers' => [
                        ['name' => 'Base', 'visibility' => true]
                    ]
                ]
            ],
            'variables' => [
                ['name' => 'Score', 'type' => 'number', 'value' => 0]
            ],
            'resources' => ['resources' => []]
        ];

        // Modify the game
        $modificationRequest = "Add a coin that the player can collect";
        $modifiedGame = $this->aiService->modifyGameFromRequest($modificationRequest, $initialGame);

        // Verify original elements are preserved
        $this->assertEquals('Test Game', $modifiedGame['properties']['name']);
        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $modifiedGame['properties']['projectUuid']);

        // Original player should still exist
        $objectNames = array_column($modifiedGame['objects'], 'name');
        $this->assertContains('Player', $objectNames);

        // Original score variable should still exist
        $variableNames = array_column($modifiedGame['variables'], 'name');
        $this->assertContains('Score', $variableNames);

        // Original layout should still exist
        $this->assertEquals('MainScene', $modifiedGame['layouts'][0]['name']);
    }
}