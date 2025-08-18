<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MobileUIComponentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test mobile chat route loads successfully
     */
    public function test_mobile_chat_route_loads()
    {
        $response = $this->get('/mobile/chat');
        
        $response->assertStatus(200);
        $response->assertSee('SurrealPilot Mobile');
        $response->assertSee('mobile-chat-container');
        $response->assertSee('mobile-message-input');
    }

    /**
     * Test mobile demos API endpoint
     */
    public function test_mobile_demos_api()
    {
        $response = $this->get('/api/mobile/demos');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'demos' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'engine_type',
                    'difficulty_level',
                    'estimated_setup_time',
                    'tags'
                ]
            ]
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['demos']);
        
        // Check that all demos are PlayCanvas
        foreach ($data['demos'] as $demo) {
            $this->assertEquals('playcanvas', $demo['engine_type']);
        }
    }

    /**
     * Test mobile device info API endpoint
     */
    public function test_mobile_device_info_api()
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/api/mobile/device-info');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'device' => [
                'is_mobile',
                'is_tablet',
                'is_ios',
                'is_android',
                'user_agent'
            ],
            'settings' => [
                'touch_targets_size',
                'font_size_base',
                'animation_duration',
                'haptic_feedback',
                'safe_area_support',
                'viewport_height_unit'
            ]
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['device']['is_mobile']);
        $this->assertTrue($data['device']['is_ios']);
        $this->assertEquals(44, $data['settings']['touch_targets_size']);
    }

    /**
     * Test PlayCanvas suggestions API
     */
    public function test_playcanvas_suggestions_api()
    {
        $response = $this->get('/api/mobile/playcanvas-suggestions?query=jump');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'suggestions'
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['suggestions']);
        
        // Should contain jump-related suggestions
        $suggestions = implode(' ', $data['suggestions']);
        $this->assertStringContainsString('jump', strtolower($suggestions));
    }

    /**
     * Test mobile workspace status API
     */
    public function test_mobile_workspace_status_api()
    {
        $workspaceId = 'test-workspace-123';
        $response = $this->get("/api/mobile/workspace/{$workspaceId}/status");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'workspace' => [
                'id',
                'name',
                'status',
                'preview_url',
                'published_url',
                'last_modified',
                'engine_type',
                'mobile_optimized'
            ]
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals($workspaceId, $data['workspace']['id']);
        $this->assertEquals('playcanvas', $data['workspace']['engine_type']);
        $this->assertTrue($data['workspace']['mobile_optimized']);
    }

    /**
     * Test mobile layout includes PWA manifest
     */
    public function test_mobile_layout_includes_pwa_manifest()
    {
        $response = $this->get('/mobile/chat');
        
        $response->assertStatus(200);
        $response->assertSee('manifest.json');
        $response->assertSee('theme-color');
        $response->assertSee('apple-mobile-web-app-capable');
        $response->assertSee('viewport');
    }

    /**
     * Test mobile layout includes touch-optimized CSS
     */
    public function test_mobile_layout_includes_touch_optimizations()
    {
        $response = $this->get('/mobile/chat');
        
        $response->assertStatus(200);
        $response->assertSee('touch-target');
        $response->assertSee('mobile-transition');
        $response->assertSee('haptic-feedback');
        $response->assertSee('safe-area');
    }

    /**
     * Test mobile chat interface includes required components
     */
    public function test_mobile_chat_interface_components()
    {
        $response = $this->get('/mobile/chat');
        
        $response->assertStatus(200);
        
        // Check for essential mobile UI components
        $response->assertSee('mobile-message-input');
        $response->assertSee('mobile-send-btn');
        $response->assertSee('mobile-suggestions');
        $response->assertSee('mobile-workspace-actions');
        $response->assertSee('mobile-demo-modal');
        $response->assertSee('mobile-preview-modal');
        $response->assertSee('mobile-credit-badge');
        $response->assertSee('mobile-menu-btn');
    }

    /**
     * Test mobile quick actions are present
     */
    public function test_mobile_quick_actions_present()
    {
        $response = $this->get('/mobile/chat');
        
        $response->assertStatus(200);
        $response->assertSee('Jump Higher');
        $response->assertSee('Faster Enemies');
        $response->assertSee('More Effects');
        $response->assertSee('New Lighting');
        $response->assertSee('mobile-quick-action');
    }

    /**
     * Test mobile device detection with different user agents
     */
    public function test_mobile_device_detection()
    {
        // Test iPhone
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/api/mobile/device-info');
        
        $data = $response->json();
        $this->assertTrue($data['device']['is_mobile']);
        $this->assertTrue($data['device']['is_ios']);
        $this->assertFalse($data['device']['is_android']);

        // Test Android
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36'
        ])->get('/api/mobile/device-info');
        
        $data = $response->json();
        $this->assertTrue($data['device']['is_mobile']);
        $this->assertFalse($data['device']['is_ios']);
        $this->assertTrue($data['device']['is_android']);

        // Test iPad
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15'
        ])->get('/api/mobile/device-info');
        
        $data = $response->json();
        $this->assertTrue($data['device']['is_tablet']);
        $this->assertTrue($data['device']['is_ios']);

        // Test Desktop
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->get('/api/mobile/device-info');
        
        $data = $response->json();
        $this->assertFalse($data['device']['is_mobile']);
        $this->assertFalse($data['device']['is_tablet']);
        $this->assertFalse($data['device']['is_ios']);
        $this->assertFalse($data['device']['is_android']);
    }

    /**
     * Test PWA manifest file exists and is valid
     */
    public function test_pwa_manifest_file()
    {
        $response = $this->get('/manifest.json');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        
        $manifest = $response->json();
        $this->assertEquals('SurrealPilot Mobile', $manifest['name']);
        $this->assertEquals('SurrealPilot', $manifest['short_name']);
        $this->assertEquals('/mobile/chat', $manifest['start_url']);
        $this->assertEquals('standalone', $manifest['display']);
        $this->assertEquals('#1f2937', $manifest['background_color']);
        $this->assertEquals('#1f2937', $manifest['theme_color']);
    }

    /**
     * Test mobile suggestions filter correctly
     */
    public function test_mobile_suggestions_filtering()
    {
        // Test with specific query
        $response = $this->get('/api/mobile/playcanvas-suggestions?query=speed');
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $suggestions = implode(' ', $data['suggestions']);
        $this->assertStringContainsString('speed', strtolower($suggestions));

        // Test with empty query
        $response = $this->get('/api/mobile/playcanvas-suggestions?query=');
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertEmpty($data['suggestions']);

        // Test with short query
        $response = $this->get('/api/mobile/playcanvas-suggestions?query=a');
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertEmpty($data['suggestions']);
    }
}