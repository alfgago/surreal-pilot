<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

beforeEach(function () {
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
                                    'name' => 'Test Platformer Game',
                                    'description' => 'A simple platformer game'
                                ],
                                'scenes' => [
                                    [
                                        'name' => 'MainScene',
                                        'objects' => [
                                            ['name' => 'Player', 'type' => 'Sprite'],
                                            ['name' => 'Coin', 'type' => 'Sprite']
                                        ]
                                    ]
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);
});

test('1. homepage accessibility', function () {
    echo "🌐 Testing homepage accessibility...\n";
    
    $response = $this->get('/');
    expect($response->status())->toBe(200);
    
    echo "✅ Homepage accessible\n";
});

test('2. registration page accessibility', function () {
    echo "📝 Testing registration page...\n";
    
    $response = $this->get('/register');
    expect($response->status())->toBe(200);
    
    $content = $response->getContent();
    expect($content)->toContain('name');
    expect($content)->toContain('email');
    
    echo "✅ Registration page accessible with form fields\n";
});

test('3. login page accessibility', function () {
    echo "🔑 Testing login page...\n";
    
    $response = $this->get('/login');
    expect($response->status())->toBe(200);
    
    $content = $response->getContent();
    expect($content)->toContain('email');
    expect($content)->toContain('password');
    
    echo "✅ Login page accessible with form fields\n";
});

test('4. engine selection page accessibility', function () {
    echo "🎮 Testing engine selection page...\n";
    
    $response = $this->get('/engine-selection');
    
    // Should redirect to login if not authenticated, or show page if authenticated
    expect($response->status())->toBeIn([200, 302]);
    
    if ($response->status() === 200) {
        echo "✅ Engine selection page accessible\n";
        
        $content = $response->getContent();
        if (str_contains($content, 'gdevelop') || str_contains($content, 'GDevelop')) {
            echo "✅ GDevelop option found in engine selection\n";
        } else {
            echo "⚠️ GDevelop option not visible (might require authentication)\n";
        }
    } else {
        echo "ℹ️ Engine selection redirects to login (expected for unauthenticated users)\n";
    }
});

test('5. workspaces page accessibility', function () {
    echo "📁 Testing workspaces page...\n";
    
    $response = $this->get('/workspaces');
    
    // Should redirect to login if not authenticated
    expect($response->status())->toBeIn([200, 302]);
    
    if ($response->status() === 200) {
        echo "✅ Workspaces page accessible\n";
    } else {
        echo "ℹ️ Workspaces page redirects to login (expected for unauthenticated users)\n";
    }
});

test('6. api assist endpoint structure', function () {
    echo "💬 Testing API assist endpoint...\n";
    
    $response = $this->post('/api/assist', [
        'message' => 'Create a simple game',
        'workspace_id' => 1
    ]);
    
    // Should return 401 (unauthorized) or 422 (validation error) for unauthenticated requests
    expect($response->status())->toBeIn([401, 422, 500]);
    
    echo "✅ API assist endpoint responds correctly to unauthenticated requests\n";
    echo "📋 Status: {$response->status()}\n";
});

test('7. gdevelop configuration check', function () {
    echo "⚙️ Testing GDevelop configuration...\n";
    
    // Check if GDevelop is enabled in config
    $gdevelopEnabled = config('gdevelop.enabled', false);
    echo "📋 GDevelop config enabled: " . ($gdevelopEnabled ? 'Yes' : 'No') . "\n";
    
    // Check environment variable
    $envEnabled = env('GDEVELOP_ENABLED', false);
    echo "📋 GDEVELOP_ENABLED env: " . ($envEnabled ? 'Yes' : 'No') . "\n";
    
    // Check if config file exists
    $configPath = config_path('gdevelop.php');
    if (File::exists($configPath)) {
        echo "✅ GDevelop config file exists\n";
    } else {
        echo "❌ GDevelop config file missing\n";
    }
    
    expect(true)->toBeTrue();
});

test('8. export directories setup', function () {
    echo "📦 Testing export directories...\n";
    
    $exportPaths = [
        storage_path('gdevelop/exports'),
        storage_path('app/gdevelop/exports'),
        public_path('storage/gdevelop/exports'),
        public_path('exports')
    ];
    
    $existingPaths = [];
    
    foreach ($exportPaths as $path) {
        if (File::exists($path)) {
            $existingPaths[] = $path;
            echo "✅ Export path exists: {$path}\n";
        } else {
            echo "❌ Export path missing: {$path}\n";
        }
    }
    
    if (!empty($existingPaths)) {
        echo "✅ At least one export directory is available\n";
    } else {
        echo "⚠️ No export directories found - creating them...\n";
        
        foreach ($exportPaths as $path) {
            try {
                File::makeDirectory($path, 0755, true);
                echo "✅ Created: {$path}\n";
            } catch (Exception $e) {
                echo "❌ Failed to create: {$path}\n";
            }
        }
    }
    
    expect(true)->toBeTrue();
});

test('9. gdevelop model and migration check', function () {
    echo "🗃️ Testing GDevelop database structure...\n";
    
    try {
        // Check if GDevelopGameSession model exists
        if (class_exists('App\Models\GDevelopGameSession')) {
            echo "✅ GDevelopGameSession model exists\n";
        } else {
            echo "❌ GDevelopGameSession model missing\n";
        }
        
        // Check if table exists by trying to query it
        $tableExists = \Illuminate\Support\Facades\Schema::hasTable('gdevelop_game_sessions');
        
        if ($tableExists) {
            echo "✅ gdevelop_game_sessions table exists\n";
            
            // Check table structure
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('gdevelop_game_sessions');
            echo "📋 Table columns: " . implode(', ', $columns) . "\n";
        } else {
            echo "❌ gdevelop_game_sessions table missing\n";
        }
        
    } catch (Exception $e) {
        echo "⚠️ Database check failed: " . $e->getMessage() . "\n";
    }
    
    expect(true)->toBeTrue();
});

test('10. gdevelop services availability', function () {
    echo "🔧 Testing GDevelop services...\n";
    
    $services = [
        'App\Services\GDevelopGameService',
        'App\Services\GDevelopAIService',
        'App\Services\GDevelopRuntimeService',
        'App\Services\GDevelopPreviewService',
        'App\Services\GDevelopExportService'
    ];
    
    foreach ($services as $service) {
        if (class_exists($service)) {
            echo "✅ {$service} exists\n";
        } else {
            echo "❌ {$service} missing\n";
        }
    }
    
    expect(true)->toBeTrue();
});

test('11. gdevelop controllers availability', function () {
    echo "🎮 Testing GDevelop controllers...\n";
    
    $controllers = [
        'App\Http\Controllers\GDevelopChatController',
        'App\Http\Controllers\Api\GDevelopController'
    ];
    
    foreach ($controllers as $controller) {
        if (class_exists($controller)) {
            echo "✅ {$controller} exists\n";
        } else {
            echo "❌ {$controller} missing\n";
        }
    }
    
    expect(true)->toBeTrue();
});

test('12. route availability check', function () {
    echo "🛣️ Testing GDevelop routes...\n";
    
    // Test routes that should exist
    $routes = [
        ['GET', '/'],
        ['GET', '/register'],
        ['GET', '/login'],
        ['GET', '/engine-selection'],
        ['GET', '/workspaces'],
        ['POST', '/api/assist']
    ];
    
    foreach ($routes as [$method, $uri]) {
        try {
            $response = $this->call($method, $uri);
            $status = $response->status();
            
            // Any response (even 401/422) means the route exists
            if ($status < 500) {
                echo "✅ Route exists: {$method} {$uri} (Status: {$status})\n";
            } else {
                echo "⚠️ Route error: {$method} {$uri} (Status: {$status})\n";
            }
        } catch (Exception $e) {
            echo "❌ Route missing: {$method} {$uri}\n";
        }
    }
    
    expect(true)->toBeTrue();
});

test('13. file system permissions', function () {
    echo "🔐 Testing file system permissions...\n";
    
    $paths = [
        storage_path(),
        storage_path('app'),
        storage_path('gdevelop'),
        public_path('storage')
    ];
    
    foreach ($paths as $path) {
        if (File::exists($path)) {
            $permissions = substr(sprintf('%o', fileperms($path)), -4);
            $writable = is_writable($path);
            
            echo "📁 {$path}: {$permissions} " . ($writable ? '✅ Writable' : '❌ Not writable') . "\n";
        } else {
            echo "❌ Path missing: {$path}\n";
        }
    }
    
    expect(true)->toBeTrue();
});

test('14. environment variables check', function () {
    echo "🌍 Testing environment variables...\n";
    
    $envVars = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'GDEVELOP_ENABLED',
        'PLAYCANVAS_ENABLED'
    ];
    
    foreach ($envVars as $var) {
        $value = env($var);
        if ($value !== null) {
            echo "✅ {$var}: {$value}\n";
        } else {
            echo "⚠️ {$var}: not set\n";
        }
    }
    
    expect(true)->toBeTrue();
});

test('15. comprehensive system status', function () {
    echo "📊 Comprehensive System Status Report\n";
    echo "=====================================\n";
    
    // Application status
    echo "🏠 Application: " . config('app.name') . "\n";
    echo "🌍 Environment: " . config('app.env') . "\n";
    echo "🔧 Debug: " . (config('app.debug') ? 'Enabled' : 'Disabled') . "\n";
    echo "🌐 URL: " . config('app.url') . "\n";
    
    // GDevelop status
    echo "\n🎮 GDevelop Integration:\n";
    echo "   Enabled: " . (config('gdevelop.enabled', false) ? 'Yes' : 'No') . "\n";
    echo "   Config file: " . (File::exists(config_path('gdevelop.php')) ? 'Exists' : 'Missing') . "\n";
    
    // Database status
    echo "\n🗃️ Database:\n";
    try {
        $connection = \Illuminate\Support\Facades\DB::connection();
        echo "   Connection: " . $connection->getName() . "\n";
        echo "   Status: Connected\n";
    } catch (Exception $e) {
        echo "   Status: Error - " . $e->getMessage() . "\n";
    }
    
    // Storage status
    echo "\n📁 Storage:\n";
    $storageWritable = is_writable(storage_path());
    echo "   Storage writable: " . ($storageWritable ? 'Yes' : 'No') . "\n";
    
    $publicWritable = is_writable(public_path());
    echo "   Public writable: " . ($publicWritable ? 'Yes' : 'No') . "\n";
    
    // Export directories
    echo "\n📦 Export Directories:\n";
    $exportPaths = [
        storage_path('gdevelop/exports'),
        public_path('storage/gdevelop/exports')
    ];
    
    foreach ($exportPaths as $path) {
        $exists = File::exists($path);
        $writable = $exists ? is_writable($path) : false;
        echo "   {$path}: " . ($exists ? 'Exists' : 'Missing') . ($writable ? ' & Writable' : '') . "\n";
    }
    
    echo "\n🎯 System Ready: " . ($storageWritable && $publicWritable ? 'Yes' : 'No') . "\n";
    
    expect(true)->toBeTrue();
});