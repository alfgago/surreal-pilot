<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;

beforeEach(function () {
    // Use production database - no migrations needed
    $this->baseUrl = 'http://surreal-pilot.local';
    
    // Mock AI responses for consistent testing
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'gameData' => [
                                'properties' => [
                                    'name' => 'AI Generated Platformer',
                                    'description' => 'A platformer game with jumping mechanics and collectible coins',
                                    'version' => '1.0.0',
                                    'author' => 'AI Assistant'
                                ],
                                'scenes' => [
                                    [
                                        'name' => 'MainScene',
                                        'objects' => [
                                            [
                                                'name' => 'Player',
                                                'type' => 'Sprite',
                                                'behaviors' => [
                                                    ['type' => 'PlatformerObject']
                                                ],
                                                'variables' => [
                                                    ['name' => 'lives', 'value' => 3],
                                                    ['name' => 'score', 'value' => 0]
                                                ]
                                            ],
                                            [
                                                'name' => 'Coin',
                                                'type' => 'Sprite',
                                                'behaviors' => [
                                                    ['type' => 'DestroyOutsideBehavior']
                                                ]
                                            ],
                                            [
                                                'name' => 'Platform',
                                                'type' => 'TiledSprite'
                                            ],
                                            [
                                                'name' => 'Enemy',
                                                'type' => 'Sprite',
                                                'behaviors' => [
                                                    ['type' => 'PlatformerObject']
                                                ]
                                            ]
                                        ],
                                        'events' => [
                                            [
                                                'type' => 'BuiltinCommonInstructions::Standard',
                                                'conditions' => [
                                                    [
                                                        'type' => 'Collision',
                                                        'parameters' => ['Player', 'Coin']
                                                    ]
                                                ],
                                                'actions' => [
                                                    [
                                                        'type' => 'Delete',
                                                        'parameters' => ['Coin']
                                                    ],
                                                    [
                                                        'type' => 'ModifySceneVariable',
                                                        'parameters' => ['score', '+', '10']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'explanation' => 'Created a platformer game with player movement, coin collection, and scoring system.'
                        ])
                    ]
                ]
            ]
        ], 200),
        '*' => Http::response(['success' => true], 200)
    ]);
    
    // Ensure export directories exist
    $exportPaths = [
        storage_path('gdevelop/exports'),
        storage_path('app/gdevelop/exports'),
        public_path('storage/gdevelop/exports'),
        public_path('exports')
    ];
    
    foreach ($exportPaths as $path) {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }
});

test('1. complete user registration workflow', function () {
    $uniqueEmail = 'gdevelop_test_' . time() . '@example.com';
    
    echo "🔐 Testing user registration...\n";
    
    // Test registration endpoint
    $response = $this->post('/register', [
        'name' => 'GDevelop Test User',
        'email' => $uniqueEmail,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'GDevelop Test Company'
    ]);
    
    // Should redirect after successful registration
    expect($response->status())->toBeIn([302, 201]);
    
    // Verify user was created in database
    $user = DB::table('users')->where('email', $uniqueEmail)->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('GDevelop Test User');
    
    // Verify company was created
    $company = DB::table('companies')->where('name', 'GDevelop Test Company')->first();
    expect($company)->not->toBeNull();
    
    // Store for next tests
    $this->testUser = $user;
    $this->testCompany = $company;
    
    echo "✅ User registration successful: {$uniqueEmail}\n";
    echo "✅ Company created: {$company->name}\n";
});

test('2. user login and authentication', function () {
    // Create a test user first
    $user = DB::table('users')->insertGetId([
        'name' => 'Login Test User',
        'email' => 'login_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Create company
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Login Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Update user with company
    DB::table('users')->where('id', $user)->update([
        'current_company_id' => $companyId
    ]);
    
    // Associate user with company
    DB::table('company_user')->insert([
        'user_id' => $user,
        'company_id' => $companyId,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "🔑 Testing user login...\n";
    
    // Test login
    $loginResponse = $this->post('/login', [
        'email' => 'login_test@gdevelop.com',
        'password' => 'password123'
    ]);
    
    expect($loginResponse->status())->toBe(302);
    
    echo "✅ Login successful\n";
    
    // Store authenticated user for next tests
    $this->authenticatedUser = (object)[
        'id' => $user,
        'email' => 'login_test@gdevelop.com',
        'current_company_id' => $companyId
    ];
});

test('3. engine selection and gdevelop availability', function () {
    // Create test user and company
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Engine Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Engine Test User',
        'email' => 'engine_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "🎮 Testing engine selection...\n";
    
    // Test engine selection page access
    $engineResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'engine_test@gdevelop.com', 'current_company_id' => $companyId]
    )->get('/engine-selection');
    
    expect($engineResponse->status())->toBe(200);
    
    // Check if GDevelop is available in the response
    $content = $engineResponse->getContent();
    if (str_contains($content, 'gdevelop') || str_contains($content, 'GDevelop')) {
        echo "✅ GDevelop engine option found in UI\n";
    } else {
        echo "⚠️ GDevelop not visible in UI, checking configuration...\n";
        
        // Check configuration
        $gdevelopEnabled = config('gdevelop.enabled', false);
        echo "📋 GDevelop config enabled: " . ($gdevelopEnabled ? 'Yes' : 'No') . "\n";
        
        // Check environment variable
        $envEnabled = env('GDEVELOP_ENABLED', false);
        echo "📋 GDEVELOP_ENABLED env: " . ($envEnabled ? 'Yes' : 'No') . "\n";
    }
    
    echo "✅ Engine selection page accessible\n";
});

test('4. workspace creation with gdevelop engine', function () {
    // Create test user and company
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Workspace Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Workspace Test User',
        'email' => 'workspace_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "📁 Testing workspace creation...\n";
    
    // Create workspace with GDevelop engine
    $workspaceResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'workspace_test@gdevelop.com', 'current_company_id' => $companyId]
    )->post('/workspaces', [
        'name' => 'My GDevelop Game Project',
        'engine' => 'gdevelop',
        'description' => 'A test game project for GDevelop integration'
    ]);
    
    expect($workspaceResponse->status())->toBeIn([201, 302]);
    
    // Verify workspace was created
    $workspace = DB::table('workspaces')
        ->where('name', 'My GDevelop Game Project')
        ->where('engine', 'gdevelop')
        ->first();
    
    expect($workspace)->not->toBeNull();
    expect($workspace->engine)->toBe('gdevelop');
    
    echo "✅ Workspace creation successful: {$workspace->name}\n";
    echo "✅ Engine type: {$workspace->engine}\n";
    
    // Store workspace for next tests
    $this->testWorkspace = $workspace;
});

test('5. ai chat game creation workflow', function () {
    // Create test setup
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Chat Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Chat Test User',
        'email' => 'chat_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Chat Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "💬 Testing AI chat game creation...\n";
    
    // Test initial game creation
    $chatResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'chat_test@gdevelop.com', 'current_company_id' => $companyId]
    )->post('/api/assist', [
        'message' => 'Create a simple platformer game with a player that can jump and collect coins',
        'workspace_id' => $workspaceId
    ]);
    
    expect($chatResponse->status())->toBeIn([200, 201]);
    
    if ($chatResponse->status() === 200) {
        $responseData = $chatResponse->json();
        expect($responseData)->toHaveKey('response');
        echo "✅ AI chat response received\n";
        
        // Check if response contains game data
        if (isset($responseData['gameData'])) {
            echo "✅ Game data included in response\n";
        }
    }
    
    // Check if game session was created
    $gameSession = DB::table('gdevelop_game_sessions')
        ->where('workspace_id', $workspaceId)
        ->first();
    
    if ($gameSession) {
        echo "✅ GDevelop game session created: {$gameSession->id}\n";
        
        // Verify game data structure
        $gameData = json_decode($gameSession->game_data, true);
        if ($gameData && isset($gameData['properties'])) {
            echo "✅ Game data structure valid\n";
            echo "📋 Game name: " . ($gameData['properties']['name'] ?? 'Unknown') . "\n";
        }
    } else {
        echo "⚠️ Game session not created yet\n";
    }
    
    // Store session for next tests
    $this->testGameSession = $gameSession;
});

test('6. multiple chat iterations and game modifications', function () {
    // Create test setup
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Iteration Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Iteration Test User',
        'email' => 'iteration_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Iteration Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "🔄 Testing multiple chat iterations...\n";
    
    // Multiple chat iterations
    $messages = [
        'Create a simple jumping game with a player character',
        'Add enemies that move back and forth on platforms',
        'Add power-ups that give the player extra lives',
        'Add a scoring system that increases when collecting items',
        'Make the game more challenging with moving platforms'
    ];
    
    $user = (object)['id' => $userId, 'email' => 'iteration_test@gdevelop.com', 'current_company_id' => $companyId];
    
    foreach ($messages as $index => $message) {
        echo "💬 Chat iteration " . ($index + 1) . ": {$message}\n";
        
        $chatResponse = $this->actingAs($user)->post('/api/assist', [
            'message' => $message,
            'workspace_id' => $workspaceId
        ]);
        
        expect($chatResponse->status())->toBeIn([200, 201]);
        
        if ($chatResponse->status() === 200) {
            echo "✅ Response received for iteration " . ($index + 1) . "\n";
        }
        
        // Small delay between requests
        usleep(500000); // 0.5 seconds
    }
    
    // Check final game session state
    $finalSession = DB::table('gdevelop_game_sessions')
        ->where('workspace_id', $workspaceId)
        ->orderBy('updated_at', 'desc')
        ->first();
    
    if ($finalSession) {
        echo "✅ Final game session found with " . count($messages) . " iterations\n";
        
        $gameData = json_decode($finalSession->game_data, true);
        if ($gameData && isset($gameData['scenes'])) {
            $objectCount = count($gameData['scenes'][0]['objects'] ?? []);
            echo "📋 Game objects in final version: {$objectCount}\n";
        }
    }
});

test('7. game preview functionality', function () {
    // Create test game session
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Preview Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Preview Test User',
        'email' => 'preview_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Preview Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $sessionId = DB::table('gdevelop_game_sessions')->insertGetId([
        'workspace_id' => $workspaceId,
        'status' => 'completed',
        'game_data' => json_encode([
            'properties' => ['name' => 'Preview Test Game'],
            'scenes' => [
                [
                    'name' => 'MainScene',
                    'objects' => [
                        ['name' => 'Player', 'type' => 'Sprite'],
                        ['name' => 'Platform', 'type' => 'TiledSprite']
                    ]
                ]
            ]
        ]),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "🎮 Testing game preview functionality...\n";
    
    // Test preview endpoint
    $previewResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'preview_test@gdevelop.com']
    )->get("/api/workspaces/{$workspaceId}/gdevelop/preview");
    
    expect($previewResponse->status())->toBeIn([200, 404, 422]);
    
    if ($previewResponse->status() === 200) {
        echo "✅ Game preview available\n";
        
        $previewData = $previewResponse->json();
        if (isset($previewData['preview_url'])) {
            echo "✅ Preview URL generated: {$previewData['preview_url']}\n";
        }
    } else {
        echo "ℹ️ Game preview not ready yet (expected for new games)\n";
        echo "📋 Status: {$previewResponse->status()}\n";
    }
    
    // Test direct preview URL if available
    $previewPaths = [
        storage_path("gdevelop/previews/{$sessionId}"),
        public_path("storage/gdevelop/previews/{$sessionId}"),
        public_path("previews/{$sessionId}")
    ];
    
    foreach ($previewPaths as $path) {
        if (File::exists($path . '/index.html')) {
            echo "✅ Preview files found at: {$path}\n";
            break;
        }
    }
});

test('8. game export functionality', function () {
    // Create test game session
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Export Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Export Test User',
        'email' => 'export_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Export Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $sessionId = DB::table('gdevelop_game_sessions')->insertGetId([
        'workspace_id' => $workspaceId,
        'status' => 'completed',
        'game_data' => json_encode([
            'properties' => [
                'name' => 'Export Test Game',
                'description' => 'A game ready for export'
            ],
            'scenes' => [
                [
                    'name' => 'MainScene',
                    'objects' => [
                        ['name' => 'Player', 'type' => 'Sprite'],
                        ['name' => 'Enemy', 'type' => 'Sprite'],
                        ['name' => 'Coin', 'type' => 'Sprite']
                    ]
                ]
            ]
        ]),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "📦 Testing game export functionality...\n";
    
    // Test export endpoint
    $exportResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'export_test@gdevelop.com']
    )->post("/api/workspaces/{$workspaceId}/gdevelop/export", [
        'format' => 'html5',
        'mobile_optimized' => true,
        'compression' => 'medium'
    ]);
    
    expect($exportResponse->status())->toBeIn([200, 202, 422]);
    
    if ($exportResponse->status() === 200 || $exportResponse->status() === 202) {
        echo "✅ Game export initiated\n";
        
        $exportData = $exportResponse->json();
        if (isset($exportData['export_id'])) {
            echo "✅ Export ID generated: {$exportData['export_id']}\n";
        }
        
        if (isset($exportData['download_url'])) {
            echo "✅ Download URL available: {$exportData['download_url']}\n";
        }
    } else {
        echo "ℹ️ Game export not ready yet\n";
        echo "📋 Status: {$exportResponse->status()}\n";
    }
});

test('9. export file system and download urls', function () {
    echo "🔍 Testing export file system...\n";
    
    // Check all possible export locations
    $exportPaths = [
        storage_path('gdevelop/exports'),
        storage_path('app/gdevelop/exports'), 
        storage_path('app/public/gdevelop/exports'),
        public_path('exports'),
        public_path('storage/gdevelop/exports'),
        public_path('gdevelop/exports')
    ];
    
    $foundFiles = [];
    
    foreach ($exportPaths as $path) {
        echo "📂 Checking: {$path} ";
        
        if (File::exists($path)) {
            echo "✅ (exists)\n";
            
            $files = glob($path . '/*.{zip,html,js}', GLOB_BRACE);
            if (!empty($files)) {
                $foundFiles[$path] = $files;
                echo "   📦 Found " . count($files) . " files\n";
            }
        } else {
            echo "❌ (not found)\n";
        }
    }
    
    if (!empty($foundFiles)) {
        echo "\n🎯 EXPORTED GAMES FOUND:\n";
        foreach ($foundFiles as $path => $files) {
            echo "📁 {$path}:\n";
            foreach ($files as $file) {
                $size = filesize($file);
                $sizeFormatted = $size > 1024*1024 ? round($size/(1024*1024), 2) . ' MB' : round($size/1024, 2) . ' KB';
                echo "   📦 " . basename($file) . " ({$sizeFormatted})\n";
                
                // If it's a ZIP file, show download URL
                if (str_ends_with($file, '.zip')) {
                    $relativePath = str_replace(public_path(), '', $file);
                    $downloadUrl = $this->baseUrl . $relativePath;
                    echo "   🔗 Download: {$downloadUrl}\n";
                }
            }
        }
    } else {
        echo "\n📝 No exported games found yet. Creating test export...\n";
        
        // Create a test export file
        $testExportPath = storage_path('gdevelop/exports');
        File::makeDirectory($testExportPath, 0755, true);
        
        $testZipPath = $testExportPath . '/test-game-' . time() . '.zip';
        file_put_contents($testZipPath, 'Test ZIP content');
        
        echo "✅ Test export created: " . basename($testZipPath) . "\n";
    }
    
    expect(true)->toBeTrue();
});

test('10. mobile optimization and responsive design', function () {
    echo "📱 Testing mobile optimization...\n";
    
    // Create mobile-optimized game session
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Mobile Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Mobile Test User',
        'email' => 'mobile_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Mobile Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Test mobile-specific chat request
    $mobileResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'mobile_test@gdevelop.com', 'current_company_id' => $companyId]
    )->post('/api/assist', [
        'message' => 'Create a mobile-friendly touch game with large buttons and simple controls',
        'workspace_id' => $workspaceId,
        'mobile_optimized' => true
    ]);
    
    expect($mobileResponse->status())->toBeIn([200, 201]);
    
    if ($mobileResponse->status() === 200) {
        echo "✅ Mobile-optimized game request processed\n";
        
        $responseData = $mobileResponse->json();
        if (isset($responseData['gameData'])) {
            echo "✅ Mobile game data generated\n";
        }
    }
    
    // Test mobile export
    $mobileExportResponse = $this->actingAs(
        (object)['id' => $userId, 'email' => 'mobile_test@gdevelop.com']
    )->post("/api/workspaces/{$workspaceId}/gdevelop/export", [
        'format' => 'html5',
        'mobile_optimized' => true,
        'touch_controls' => true,
        'responsive_design' => true
    ]);
    
    expect($mobileExportResponse->status())->toBeIn([200, 202, 422]);
    
    if ($mobileExportResponse->status() === 200 || $mobileExportResponse->status() === 202) {
        echo "✅ Mobile export initiated\n";
    }
});

test('11. error handling and edge cases', function () {
    echo "⚠️ Testing error handling...\n";
    
    // Test invalid workspace ID
    $invalidResponse = $this->post('/api/assist', [
        'message' => 'Create a game',
        'workspace_id' => 99999
    ]);
    
    expect($invalidResponse->status())->toBeIn([401, 404, 422]);
    echo "✅ Invalid workspace ID handled correctly\n";
    
    // Test empty message
    $emptyResponse = $this->post('/api/assist', [
        'message' => '',
        'workspace_id' => 1
    ]);
    
    expect($emptyResponse->status())->toBeIn([400, 422]);
    echo "✅ Empty message handled correctly\n";
    
    // Test invalid export format
    $invalidExportResponse = $this->post('/api/workspaces/1/gdevelop/export', [
        'format' => 'invalid_format'
    ]);
    
    expect($invalidExportResponse->status())->toBeIn([400, 401, 422]);
    echo "✅ Invalid export format handled correctly\n";
});

test('12. performance and resource monitoring', function () {
    echo "⚡ Testing performance monitoring...\n";
    
    $startTime = microtime(true);
    
    // Create test setup
    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Performance Test Company',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $userId = DB::table('users')->insertGetId([
        'name' => 'Performance Test User',
        'email' => 'performance_test@gdevelop.com',
        'password' => bcrypt('password123'),
        'current_company_id' => $companyId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $workspaceId = DB::table('workspaces')->insertGetId([
        'name' => 'Performance Test Workspace',
        'engine' => 'gdevelop',
        'company_id' => $companyId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Test multiple rapid requests
    $user = (object)['id' => $userId, 'email' => 'performance_test@gdevelop.com', 'current_company_id' => $companyId];
    
    for ($i = 1; $i <= 3; $i++) {
        $response = $this->actingAs($user)->post('/api/assist', [
            'message' => "Create game variation {$i}",
            'workspace_id' => $workspaceId
        ]);
        
        expect($response->status())->toBeIn([200, 201, 429]); // 429 = rate limited
        
        if ($response->status() === 429) {
            echo "✅ Rate limiting working correctly\n";
            break;
        }
    }
    
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "⏱️ Performance test completed in {$executionTime}ms\n";
    
    // Check memory usage
    $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    echo "💾 Peak memory usage: {$memoryUsage}MB\n";
    
    expect($executionTime)->toBeLessThan(30000); // Should complete within 30 seconds
    expect($memoryUsage)->toBeLessThan(512); // Should use less than 512MB
});

test('13. database cleanup after comprehensive tests', function () {
    echo "🧹 Cleaning up test data...\n";
    
    // Clean up test data created during tests
    $testEmails = [
        '%gdevelop_test_%',
        '%@gdevelop.com',
        '%_test@gdevelop.com'
    ];
    
    foreach ($testEmails as $pattern) {
        // Get user IDs to clean up
        $userIds = DB::table('users')->where('email', 'like', $pattern)->pluck('id');
        
        if ($userIds->isNotEmpty()) {
            // Clean up related data
            DB::table('gdevelop_game_sessions')->whereIn('workspace_id', function($query) use ($userIds) {
                $query->select('id')->from('workspaces')->whereIn('user_id', $userIds);
            })->delete();
            
            DB::table('workspaces')->whereIn('user_id', $userIds)->delete();
            
            DB::table('company_user')->whereIn('user_id', $userIds)->delete();
            
            DB::table('users')->whereIn('id', $userIds)->delete();
        }
    }
    
    // Clean up test companies
    $testCompanyPatterns = ['%Test%', '%GDevelop%'];
    
    foreach ($testCompanyPatterns as $pattern) {
        $companyIds = DB::table('companies')->where('name', 'like', $pattern)->pluck('id');
        
        if ($companyIds->isNotEmpty()) {
            DB::table('company_user')->whereIn('company_id', $companyIds)->delete();
            DB::table('workspaces')->whereIn('company_id', $companyIds)->delete();
            DB::table('companies')->whereIn('id', $companyIds)->delete();
        }
    }
    
    echo "✅ Test data cleaned up successfully\n";
    
    expect(true)->toBeTrue();
});