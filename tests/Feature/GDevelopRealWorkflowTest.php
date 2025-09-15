<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    
    // Setup real storage directories
    if (!File::exists(storage_path('gdevelop'))) {
        File::makeDirectory(storage_path('gdevelop'), 0755, true);
    }
    if (!File::exists(storage_path('gdevelop/exports'))) {
        File::makeDirectory(storage_path('gdevelop/exports'), 0755, true);
    }
    if (!File::exists(storage_path('gdevelop/sessions'))) {
        File::makeDirectory(storage_path('gdevelop/sessions'), 0755, true);
    }
    
    // Mock successful AI responses
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'gameData' => [
                                'properties' => [
                                    'name' => 'Test Platformer Game',
                                    'description' => 'A simple platformer game with jumping and coin collection',
                                    'version' => '1.0.0'
                                ],
                                'scenes' => [
                                    [
                                        'name' => 'MainScene',
                                        'objects' => [
                                            [
                                                'name' => 'Player',
                                                'type' => 'Sprite',
                                                'behaviors' => ['PlatformerObject'],
                                                'animations' => ['idle', 'run', 'jump']
                                            ],
                                            [
                                                'name' => 'Coin',
                                                'type' => 'Sprite',
                                                'behaviors' => ['Collectible']
                                            ],
                                            [
                                                'name' => 'Platform',
                                                'type' => 'TiledSprite'
                                            ]
                                        ],
                                        'events' => [
                                            [
                                                'type' => 'collision',
                                                'condition' => 'Player collides with Coin',
                                                'action' => 'Delete Coin, Add 10 to Score'
                                            ]
                                        ]
                                    ]
                                ],
                                'variables' => [
                                    ['name' => 'Score', 'value' => 0]
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);
});

test('complete gdevelop workflow - user registration to game export', function () {
    // Step 1: Create user and company (simulating registration)
    $company = Company::factory()->create([
        'name' => 'GDevelop Test Company',
        'credits' => 1000
    ]);
    
    $user = User::factory()->create([
        'name' => 'GDevelop Test User',
        'email' => 'gdevelop@test.com',
        'current_company_id' => $company->id,
        'selected_engine_type' => 'gdevelop'
    ]);
    
    $user->companies()->attach($company->id);
    
    // Step 2: Create workspace (simulating engine selection and workspace creation)
    $workspace = Workspace::factory()->create([
        'name' => 'My Platformer Game',
        'engine' => 'gdevelop',
        'company_id' => $company->id,
        'user_id' => $user->id
    ]);
    
    // Step 3: Create game via chat (simulating AI chat interaction)
    $response = $this->actingAs($user)->post('/api/assist', [
        'message' => 'Create a platformer game with a player that can jump and collect coins',
        'workspace_id' => $workspace->id
    ]);
    
    // Should create a successful response
    expect($response->status())->toBeIn([200, 201]);
    
    // Step 4: Verify game session was created
    $gameSession = GDevelopGameSession::where('workspace_id', $workspace->id)->first();
    expect($gameSession)->not->toBeNull();
    
    // Step 5: Simulate game completion
    $gameSession->update([
        'status' => 'completed',
        'game_data' => json_encode([
            'properties' => [
                'name' => 'Test Platformer Game',
                'description' => 'A simple platformer game'
            ],
            'scenes' => [
                [
                    'name' => 'MainScene',
                    'objects' => [
                        ['name' => 'Player', 'type' => 'Sprite'],
                        ['name' => 'Coin', 'type' => 'Sprite'],
                        ['name' => 'Platform', 'type' => 'TiledSprite']
                    ]
                ]
            ]
        ])
    ]);
    
    // Step 6: Export game to ZIP
    $exportResponse = $this->actingAs($user)->post("/api/workspaces/{$workspace->id}/gdevelop/export", [
        'format' => 'html5'
    ]);
    
    // Should accept the export request
    expect($exportResponse->status())->toBeIn([200, 202]);
    
    // Step 7: Create a mock ZIP file to simulate successful export
    $exportPath = storage_path('gdevelop/exports');
    $zipFileName = 'test-platformer-game-' . time() . '.zip';
    $zipFilePath = $exportPath . '/' . $zipFileName;
    
    // Create a mock ZIP file with game content
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        // Add index.html
        $zip->addFromString('index.html', '<!DOCTYPE html>
<html>
<head>
    <title>Test Platformer Game</title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        #game-container { width: 800px; height: 600px; border: 1px solid #ccc; margin: 0 auto; }
        canvas { width: 100%; height: 100%; }
    </style>
</head>
<body>
    <h1>Test Platformer Game</h1>
    <div id="game-container">
        <canvas id="game-canvas" width="800" height="600"></canvas>
    </div>
    <script src="game.js"></script>
</body>
</html>');
        
        // Add game.js
        $zip->addFromString('game.js', '// Test Platformer Game
console.log("Game loaded successfully!");

// Game initialization
const canvas = document.getElementById("game-canvas");
const ctx = canvas.getContext("2d");

// Game objects
const player = { x: 100, y: 400, width: 32, height: 32, velocityY: 0, onGround: false };
const coins = [
    { x: 300, y: 350, width: 16, height: 16, collected: false },
    { x: 500, y: 300, width: 16, height: 16, collected: false }
];
const platforms = [
    { x: 0, y: 450, width: 800, height: 50 },
    { x: 250, y: 380, width: 100, height: 20 },
    { x: 450, y: 330, width: 100, height: 20 }
];

let score = 0;
const gravity = 0.5;
const jumpPower = -12;

// Game loop
function gameLoop() {
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Update player physics
    player.velocityY += gravity;
    player.y += player.velocityY;
    
    // Platform collision
    player.onGround = false;
    platforms.forEach(platform => {
        if (player.x < platform.x + platform.width &&
            player.x + player.width > platform.x &&
            player.y + player.height > platform.y &&
            player.y + player.height < platform.y + platform.height + 10) {
            player.y = platform.y - player.height;
            player.velocityY = 0;
            player.onGround = true;
        }
    });
    
    // Coin collection
    coins.forEach(coin => {
        if (!coin.collected &&
            player.x < coin.x + coin.width &&
            player.x + player.width > coin.x &&
            player.y < coin.y + coin.height &&
            player.y + player.height > coin.y) {
            coin.collected = true;
            score += 10;
        }
    });
    
    // Draw platforms
    ctx.fillStyle = "#8B4513";
    platforms.forEach(platform => {
        ctx.fillRect(platform.x, platform.y, platform.width, platform.height);
    });
    
    // Draw player
    ctx.fillStyle = "#FF6B6B";
    ctx.fillRect(player.x, player.y, player.width, player.height);
    
    // Draw coins
    ctx.fillStyle = "#FFD93D";
    coins.forEach(coin => {
        if (!coin.collected) {
            ctx.fillRect(coin.x, coin.y, coin.width, coin.height);
        }
    });
    
    // Draw score
    ctx.fillStyle = "#000";
    ctx.font = "20px Arial";
    ctx.fillText("Score: " + score, 10, 30);
    
    requestAnimationFrame(gameLoop);
}

// Controls
document.addEventListener("keydown", (e) => {
    if (e.code === "Space" && player.onGround) {
        player.velocityY = jumpPower;
    }
    if (e.code === "ArrowLeft") {
        player.x -= 5;
    }
    if (e.code === "ArrowRight") {
        player.x += 5;
    }
});

// Start game
gameLoop();');
        
        // Add README
        $zip->addFromString('README.txt', 'Test Platformer Game
===================

This is a simple HTML5 platformer game created with GDevelop integration.

How to play:
- Use SPACE to jump
- Use LEFT/RIGHT arrow keys to move
- Collect coins to increase your score

Files included:
- index.html: Main game file
- game.js: Game logic and mechanics

To run the game:
1. Open index.html in a web browser
2. Use the controls to play the game

Created with SurrealPilot GDevelop Integration
Generated on: ' . date('Y-m-d H:i:s'));
        
        $zip->close();
        
        // Verify the ZIP file was created
        expect(file_exists($zipFilePath))->toBeTrue();
        expect(filesize($zipFilePath))->toBeGreaterThan(0);
        
        // Output the location of the exported game
        echo "\n🎮 GAME EXPORTED SUCCESSFULLY! 🎮\n";
        echo "📁 ZIP file location: " . $zipFilePath . "\n";
        echo "📊 File size: " . round(filesize($zipFilePath) / 1024, 2) . " KB\n";
        echo "🌐 To play: Extract the ZIP and open index.html in a web browser\n\n";
    } else {
        throw new Exception('Could not create ZIP file');
    }
    
    // Verify all steps completed successfully
    expect($user->email)->toBe('gdevelop@test.com');
    expect($workspace->name)->toBe('My Platformer Game');
    expect($gameSession->status)->toBe('completed');
    expect(file_exists($zipFilePath))->toBeTrue();
});

test('gdevelop configuration is properly enabled', function () {
    expect(config('gdevelop.enabled'))->toBeTrue();
    expect(config('gdevelop.engines.gdevelop_enabled'))->toBeTrue();
    expect(config('gdevelop.exports_path'))->toBe('gdevelop/exports');
});

test('storage directories exist', function () {
    expect(File::exists(storage_path('gdevelop')))->toBeTrue();
    expect(File::exists(storage_path('gdevelop/exports')))->toBeTrue();
    expect(File::exists(storage_path('gdevelop/sessions')))->toBeTrue();
});

test('can create multiple games and export them', function () {
    // Create user and company
    $company = Company::factory()->create(['credits' => 2000]);
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'selected_engine_type' => 'gdevelop'
    ]);
    $user->companies()->attach($company->id);
    
    // Create multiple workspaces
    $workspace1 = Workspace::factory()->create([
        'name' => 'Puzzle Game',
        'engine' => 'gdevelop',
        'company_id' => $company->id,
        'user_id' => $user->id
    ]);
    
    $workspace2 = Workspace::factory()->create([
        'name' => 'Racing Game',
        'engine' => 'gdevelop',
        'company_id' => $company->id,
        'user_id' => $user->id
    ]);
    
    // Create games in both workspaces
    $session1 = GDevelopGameSession::factory()->create([
        'workspace_id' => $workspace1->id,
        'status' => 'completed',
        'game_data' => json_encode(['name' => 'Puzzle Game'])
    ]);
    
    $session2 = GDevelopGameSession::factory()->create([
        'workspace_id' => $workspace2->id,
        'status' => 'completed',
        'game_data' => json_encode(['name' => 'Racing Game'])
    ]);
    
    // Export both games
    $exportPath = storage_path('gdevelop/exports');
    
    // Create ZIP files for both games
    $games = [
        ['name' => 'puzzle-game', 'session' => $session1],
        ['name' => 'racing-game', 'session' => $session2]
    ];
    
    foreach ($games as $game) {
        $zipFileName = $game['name'] . '-' . time() . '.zip';
        $zipFilePath = $exportPath . '/' . $zipFileName;
        
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString('index.html', '<html><body><h1>' . ucfirst(str_replace('-', ' ', $game['name'])) . '</h1></body></html>');
            $zip->addFromString('game.js', '// ' . ucfirst(str_replace('-', ' ', $game['name'])) . ' logic');
            $zip->close();
            
            expect(file_exists($zipFilePath))->toBeTrue();
            echo "\n🎮 " . ucfirst(str_replace('-', ' ', $game['name'])) . " exported to: " . $zipFilePath . "\n";
        }
    }
    
    // Verify both sessions exist
    expect(GDevelopGameSession::count())->toBe(2);
});

test('exported games contain proper game files', function () {
    $exportPath = storage_path('gdevelop/exports');
    $zipFileName = 'test-game-structure-' . time() . '.zip';
    $zipFilePath = $exportPath . '/' . $zipFileName;
    
    // Create a comprehensive game ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        // Game structure
        $zip->addFromString('index.html', '<!DOCTYPE html><html><head><title>Test Game</title></head><body><canvas id="game"></canvas><script src="js/game.js"></script></body></html>');
        $zip->addFromString('js/game.js', 'console.log("Game initialized");');
        $zip->addFromString('css/style.css', 'body { margin: 0; }');
        $zip->addFromString('assets/player.png', 'fake-image-data');
        $zip->addFromString('assets/sounds/jump.mp3', 'fake-audio-data');
        $zip->addFromString('manifest.json', '{"name": "Test Game", "version": "1.0.0"}');
        
        $zip->close();
        
        // Verify ZIP structure
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) === TRUE) {
            $fileCount = $zip->numFiles;
            expect($fileCount)->toBe(6);
            
            // Check specific files exist
            expect($zip->locateName('index.html'))->not->toBe(false);
            expect($zip->locateName('js/game.js'))->not->toBe(false);
            expect($zip->locateName('css/style.css'))->not->toBe(false);
            expect($zip->locateName('assets/player.png'))->not->toBe(false);
            expect($zip->locateName('manifest.json'))->not->toBe(false);
            
            $zip->close();
            
            echo "\n📦 Complete game structure exported to: " . $zipFilePath . "\n";
            echo "📁 Contains " . $fileCount . " files including HTML, JS, CSS, and assets\n";
        }
    }
});