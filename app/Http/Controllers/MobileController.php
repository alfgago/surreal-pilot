<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MobileController extends Controller
{
    /**
     * Show the mobile chat interface
     */
    public function chat(): View
    {
        $providers = [
            'openai' => ['name' => 'OpenAI GPT-4', 'requires_key' => true],
            'anthropic' => ['name' => 'Anthropic Claude', 'requires_key' => true],
            'gemini' => ['name' => 'Google Gemini', 'requires_key' => true],
            'ollama' => ['name' => 'Ollama (Local)', 'requires_key' => false],
        ];

        return view('mobile.chat', compact('providers'));
    }

    /**
     * Show the mobile tutorials interface
     */
    public function tutorials(): View
    {
        return view('mobile.tutorials');
    }

    /**
     * Get mobile-optimized demo templates
     */
    public function getDemos(Request $request): JsonResponse
    {
        // Mock demo data - in real implementation, this would come from the database
        $demos = [
            [
                'id' => 'starter-fps',
                'name' => 'Starter FPS',
                'description' => 'First-person shooter with basic controls and enemies',
                'engine_type' => 'playcanvas',
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 30,
                'preview_image' => '/images/demos/fps-preview.jpg',
                'tags' => ['FPS', 'Beginner', 'Action']
            ],
            [
                'id' => 'third-person',
                'name' => 'Third-Person Adventure',
                'description' => 'Third-person character controller with platforming',
                'engine_type' => 'playcanvas',
                'difficulty_level' => 'intermediate',
                'estimated_setup_time' => 45,
                'preview_image' => '/images/demos/third-person-preview.jpg',
                'tags' => ['Adventure', 'Platformer', 'Intermediate']
            ],
            [
                'id' => '2d-platformer',
                'name' => '2D Platformer',
                'description' => 'Classic 2D side-scrolling platformer game',
                'engine_type' => 'playcanvas',
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 25,
                'preview_image' => '/images/demos/2d-platformer-preview.jpg',
                'tags' => ['2D', 'Platformer', 'Classic']
            ],
            [
                'id' => 'racing-game',
                'name' => 'Racing Game',
                'description' => 'Simple racing game with physics and checkpoints',
                'engine_type' => 'playcanvas',
                'difficulty_level' => 'intermediate',
                'estimated_setup_time' => 60,
                'preview_image' => '/images/demos/racing-preview.jpg',
                'tags' => ['Racing', 'Physics', 'Intermediate']
            ],
            [
                'id' => 'puzzle-game',
                'name' => 'Puzzle Game',
                'description' => 'Match-3 style puzzle game with animations',
                'engine_type' => 'playcanvas',
                'difficulty_level' => 'beginner',
                'estimated_setup_time' => 35,
                'preview_image' => '/images/demos/puzzle-preview.jpg',
                'tags' => ['Puzzle', 'Casual', 'Beginner']
            ]
        ];

        return response()->json([
            'success' => true,
            'demos' => $demos
        ]);
    }

    /**
     * Get mobile device information for optimization
     */
    public function getDeviceInfo(Request $request): JsonResponse
    {
        $userAgent = $request->header('User-Agent');
        $isMobile = $this->isMobileDevice($userAgent);
        $isTablet = $this->isTabletDevice($userAgent);
        $isIOS = $this->isIOSDevice($userAgent);
        $isAndroid = $this->isAndroidDevice($userAgent);

        // Determine optimal settings based on device
        $settings = [
            'touch_targets_size' => $isMobile ? 44 : 32,
            'font_size_base' => $isMobile ? 16 : 14,
            'animation_duration' => $isMobile ? 300 : 200,
            'haptic_feedback' => $isMobile && ($isIOS || $isAndroid),
            'safe_area_support' => $isIOS,
            'viewport_height_unit' => $isMobile ? 'dvh' : 'vh'
        ];

        return response()->json([
            'device' => [
                'is_mobile' => $isMobile,
                'is_tablet' => $isTablet,
                'is_ios' => $isIOS,
                'is_android' => $isAndroid,
                'user_agent' => $userAgent
            ],
            'settings' => $settings
        ]);
    }

    /**
     * Get mobile-optimized workspace status
     */
    public function getWorkspaceStatus(Request $request, string $workspaceId): JsonResponse
    {
        // Mock workspace status - in real implementation, this would query the database
        $status = [
            'id' => $workspaceId,
            'name' => 'My Game',
            'status' => 'ready', // ready, building, error
            'preview_url' => "https://preview.example.com/{$workspaceId}",
            'published_url' => null,
            'last_modified' => now()->toISOString(),
            'engine_type' => 'playcanvas',
            'mobile_optimized' => true
        ];

        return response()->json([
            'success' => true,
            'workspace' => $status
        ]);
    }

    /**
     * Get mobile-specific PlayCanvas suggestions
     */
    public function getPlayCanvasSuggestions(Request $request): JsonResponse
    {
        $input = $request->input('query', '');
        
        $allSuggestions = [
            // Movement and controls
            'double the jump height',
            'increase player speed',
            'make the character run faster',
            'add double jump ability',
            'improve movement controls',
            
            // Visual effects
            'make enemies faster',
            'change the lighting to sunset',
            'add more particles',
            'make it more colorful',
            'add glowing effects',
            'change the sky color',
            
            // Gameplay mechanics
            'add more obstacles',
            'make the world bigger',
            'add sound effects',
            'increase difficulty',
            'add power-ups',
            'create more levels',
            
            // Camera and view
            'change the camera angle',
            'make camera follow closer',
            'add camera shake',
            'improve camera smoothing',
            
            // Environment
            'add more trees',
            'change the ground texture',
            'add water effects',
            'make the environment darker',
            'add fog effects'
        ];

        // Filter suggestions based on input
        $filteredSuggestions = [];
        if (strlen($input) >= 2) {
            $inputLower = strtolower($input);
            $filteredSuggestions = array_filter($allSuggestions, function($suggestion) use ($inputLower) {
                return strpos(strtolower($suggestion), $inputLower) !== false;
            });
        }

        return response()->json([
            'success' => true,
            'suggestions' => array_values(array_slice($filteredSuggestions, 0, 5))
        ]);
    }

    /**
     * Check if the user agent indicates a mobile device
     */
    private function isMobileDevice(string $userAgent): bool
    {
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPod', 'BlackBerry', 
            'Windows Phone', 'Opera Mini', 'IEMobile'
        ];

        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent indicates a tablet device
     */
    private function isTabletDevice(string $userAgent): bool
    {
        $tabletKeywords = ['iPad', 'Android.*Tablet', 'Kindle', 'Silk'];

        foreach ($tabletKeywords as $keyword) {
            if (preg_match("/{$keyword}/i", $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user agent indicates an iOS device
     */
    private function isIOSDevice(string $userAgent): bool
    {
        return stripos($userAgent, 'iPhone') !== false || 
               stripos($userAgent, 'iPad') !== false || 
               stripos($userAgent, 'iPod') !== false;
    }

    /**
     * Check if the user agent indicates an Android device
     */
    private function isAndroidDevice(string $userAgent): bool
    {
        return stripos($userAgent, 'Android') !== false;
    }
}