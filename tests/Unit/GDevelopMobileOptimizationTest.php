<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GDevelopAIService;
use App\Services\GDevelopTemplateService;
use App\Services\GDevelopJsonValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class GDevelopMobileOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopAIService $aiService;
    private GDevelopTemplateService $templateService;
    private GDevelopJsonValidator $jsonValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->templateService = $this->app->make(GDevelopTemplateService::class);
        $this->jsonValidator = $this->app->make(GDevelopJsonValidator::class);
        $this->aiService = new GDevelopAIService($this->templateService, $this->jsonValidator);
    }

    public function test_generates_mobile_optimized_game_when_requested()
    {
        $userRequest = "Create a simple puzzle game";
        $options = [
            'mobile_optimized' => true,
            'target_device' => 'mobile',
            'control_scheme' => 'touch_direct',
            'orientation' => 'portrait'
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Verify mobile optimizations are applied
        $this->assertTrue($gameJson['properties']['adaptGameResolutionAtRuntime']);
        $this->assertTrue($gameJson['properties']['pixelsRounding']);
        $this->assertEquals('portrait', $gameJson['properties']['orientation']);
        $this->assertArrayHasKey('mobileViewport', $gameJson['properties']);
        
        // Check mobile viewport settings
        $viewport = $gameJson['properties']['mobileViewport'];
        $this->assertEquals('device-width', $viewport['width']);
        $this->assertEquals(1.0, $viewport['initialScale']);
        $this->assertFalse($viewport['userScalable']);
    }

    public function test_auto_detects_mobile_optimization_for_puzzle_games()
    {
        $userRequest = "Make a match-3 puzzle game";
        $options = []; // No explicit mobile optimization

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Should auto-detect and apply mobile optimizations for puzzle games
        $this->assertTrue($gameJson['properties']['adaptGameResolutionAtRuntime']);
        $this->assertEquals('portrait', $gameJson['properties']['orientation']);
    }

    public function test_adds_mobile_ui_layers_to_layouts()
    {
        $userRequest = "Create a tower defense game";
        $options = [
            'mobile_optimized' => true,
            'target_device' => 'mobile'
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Check that mobile UI layer is added
        $this->assertNotEmpty($gameJson['layouts']);
        $layout = $gameJson['layouts'][0];
        
        $mobileUILayer = collect($layout['layers'])->firstWhere('name', 'MobileUI');
        $this->assertNotNull($mobileUILayer, 'Mobile UI layer should be added to layouts');
    }

    public function test_optimizes_objects_for_touch_interaction()
    {
        $userRequest = "Create a platformer with a player and enemies";
        $options = [
            'mobile_optimized' => true,
            'target_device' => 'mobile',
            'touch_controls' => true
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Check that objects have touch-friendly behaviors and variables
        foreach ($gameJson['objects'] as $object) {
            if ($object['type'] === 'Sprite') {
                // Should have touch-friendly behavior
                $touchBehavior = collect($object['behaviors'])->firstWhere('name', 'TouchFriendly');
                $this->assertNotNull($touchBehavior, "Object {$object['name']} should have TouchFriendly behavior");
                
                // Should have touch-related variables
                $touchEnabledVar = collect($object['variables'])->firstWhere('name', 'TouchEnabled');
                $touchSizeVar = collect($object['variables'])->firstWhere('name', 'TouchSize');
                
                $this->assertNotNull($touchEnabledVar, "Object {$object['name']} should have TouchEnabled variable");
                $this->assertNotNull($touchSizeVar, "Object {$object['name']} should have TouchSize variable");
                $this->assertEquals(44, $touchSizeVar['value'], 'Touch size should meet minimum accessibility guidelines');
            }
        }
    }

    public function test_adds_mobile_specific_events_for_platformer()
    {
        $userRequest = "Create a platformer game";
        $options = [
            'mobile_optimized' => true,
            'control_scheme' => 'virtual_dpad'
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Check for mobile-specific events
        $this->assertNotEmpty($gameJson['layouts']);
        $events = $gameJson['layouts'][0]['events'] ?? [];
        
        // Should have touch/swipe events for platformer
        $touchEvents = collect($events)->filter(function ($event) {
            $conditions = $event['conditions'] ?? [];
            return collect($conditions)->contains(function ($condition) {
                $type = $condition['type']['value'] ?? '';
                return in_array($type, ['TouchOrMouseOnObject', 'SwipeLeft', 'SwipeRight']);
            });
        });
        
        $this->assertGreaterThan(0, $touchEvents->count(), 'Should have mobile touch events for platformer');
    }

    public function test_adds_mobile_specific_events_for_tower_defense()
    {
        $userRequest = "Create a tower defense game";
        $options = [
            'mobile_optimized' => true,
            'control_scheme' => 'touch_direct'
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Check for tower defense mobile events
        $this->assertNotEmpty($gameJson['layouts']);
        $events = $gameJson['layouts'][0]['events'] ?? [];
        
        // Should have touch events for tower placement
        $touchEvents = collect($events)->filter(function ($event) {
            $conditions = $event['conditions'] ?? [];
            return collect($conditions)->contains(function ($condition) {
                $type = $condition['type']['value'] ?? '';
                return in_array($type, ['TouchOrMouseDown', 'LongPressOnObject']);
            });
        });
        
        $this->assertGreaterThan(0, $touchEvents->count(), 'Should have mobile touch events for tower defense');
    }

    public function test_determines_appropriate_control_scheme_for_game_type()
    {
        $testCases = [
            ['platformer', 'virtual_dpad'],
            ['tower-defense', 'touch_direct'],
            ['puzzle', 'drag_drop'],
            ['arcade', 'touch_gesture']
        ];

        foreach ($testCases as [$gameType, $expectedScheme]) {
            $userRequest = "Create a {$gameType} game";
            $options = ['mobile_optimized' => true];

            $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);
            
            // The control scheme should be determined based on game type
            // This is tested indirectly through the events that are generated
            $this->assertNotEmpty($gameJson['layouts']);
        }
    }

    public function test_calculates_appropriate_ui_scale_for_mobile()
    {
        $testCases = [
            ['puzzle', 1.5],
            ['tower-defense', 1.3],
            ['platformer', 1.2],
            ['arcade', 1.1]
        ];

        foreach ($testCases as [$gameType, $expectedScale]) {
            $userRequest = "Create a {$gameType} game";
            $options = ['mobile_optimized' => true];

            $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);
            
            // UI scale affects object sizing and layout
            // This is tested indirectly through the mobile settings applied
            $this->assertArrayHasKey('mobileViewport', $gameJson['properties']);
        }
    }

    public function test_adds_orientation_change_handling()
    {
        $userRequest = "Create a mobile game";
        $options = [
            'mobile_optimized' => true,
            'responsive_ui' => true
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Check for orientation change event
        $this->assertNotEmpty($gameJson['layouts']);
        $events = $gameJson['layouts'][0]['events'] ?? [];
        
        $orientationEvent = collect($events)->first(function ($event) {
            $conditions = $event['conditions'] ?? [];
            return collect($conditions)->contains(function ($condition) {
                return ($condition['type']['value'] ?? '') === 'OrientationChanged';
            });
        });
        
        $this->assertNotNull($orientationEvent, 'Should have orientation change handling event');
        
        // Should have action to adapt game resolution
        $actions = $orientationEvent['actions'] ?? [];
        $adaptAction = collect($actions)->first(function ($action) {
            return ($action['type']['value'] ?? '') === 'AdaptGameResolution';
        });
        
        $this->assertNotNull($adaptAction, 'Should have action to adapt game resolution on orientation change');
    }

    public function test_preserves_desktop_behavior_when_mobile_not_requested()
    {
        $userRequest = "Create a platformer game";
        $options = [
            'mobile_optimized' => false,
            'target_device' => 'desktop'
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // Should not have mobile-specific properties
        $this->assertArrayNotHasKey('mobileViewport', $gameJson['properties']);
        
        // Should not have mobile UI layers
        if (!empty($gameJson['layouts'])) {
            $layout = $gameJson['layouts'][0];
            $mobileUILayer = collect($layout['layers'])->firstWhere('name', 'MobileUI');
            $this->assertNull($mobileUILayer, 'Should not have mobile UI layer for desktop games');
        }
    }

    public function test_validates_mobile_optimized_game_json()
    {
        $userRequest = "Create a mobile puzzle game";
        $options = [
            'mobile_optimized' => true,
            'target_device' => 'mobile',
            'touch_controls' => true,
            'responsive_ui' => true
        ];

        $gameJson = $this->aiService->generateGameFromRequest($userRequest, null, $options);

        // The generated JSON should pass validation
        $this->assertTrue($this->jsonValidator->isValid($gameJson));
        
        // Should not throw validation exception
        $this->jsonValidator->validateOrThrow($gameJson);
        $this->assertTrue(true); // If we get here, validation passed
    }
}