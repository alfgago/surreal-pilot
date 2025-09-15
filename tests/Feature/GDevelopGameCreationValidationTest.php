<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->user->companies()->attach($this->company);
    
    $this->workspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    Storage::fake('gdevelop');
});

describe('GDevelop Game Creation Validation', function () {
    test('can create tower defense game session with proper structure', function () {
        // Create a tower defense game session
        $towerDefenseGame = [
            'properties' => [
                'name' => 'Tower Defense Game',
                'description' => 'A tower defense game with multiple tower types',
                'author' => 'Test User',
                'version' => '1.0.0'
            ],
            'objects' => [
                [
                    'name' => 'BasicTower',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'TopDownMovement'],
                        ['type' => 'Shooter']
                    ],
                    'variables' => [
                        ['name' => 'damage', 'value' => 10],
                        ['name' => 'range', 'value' => 100],
                        ['name' => 'fireRate', 'value' => 1.0]
                    ]
                ],
                [
                    'name' => 'SplashTower',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'TopDownMovement'],
                        ['type' => 'Shooter']
                    ],
                    'variables' => [
                        ['name' => 'damage', 'value' => 15],
                        ['name' => 'range', 'value' => 80],
                        ['name' => 'splashRadius', 'value' => 50]
                    ]
                ],
                [
                    'name' => 'FreezeTower',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'TopDownMovement'],
                        ['type' => 'Shooter']
                    ],
                    'variables' => [
                        ['name' => 'damage', 'value' => 5],
                        ['name' => 'range', 'value' => 120],
                        ['name' => 'slowEffect', 'value' => 0.5]
                    ]
                ],
                [
                    'name' => 'BasicEnemy',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'TopDownMovement'],
                        ['type' => 'Health']
                    ],
                    'variables' => [
                        ['name' => 'health', 'value' => 50],
                        ['name' => 'speed', 'value' => 60],
                        ['name' => 'reward', 'value' => 10]
                    ]
                ]
            ],
            'layouts' => [
                [
                    'name' => 'GameLevel',
                    'title' => 'Tower Defense Level',
                    'objects' => ['BasicTower', 'SplashTower', 'FreezeTower', 'BasicEnemy'],
                    'layers' => [
                        ['name' => 'Background'],
                        ['name' => 'Game'],
                        ['name' => 'UI']
                    ]
                ]
            ],
            'variables' => [
                ['name' => 'score', 'value' => 0],
                ['name' => 'wave', 'value' => 1],
                ['name' => 'health', 'value' => 20],
                ['name' => 'currency', 'value' => 100]
            ]
        ];
        
        $session = GDevelopGameSession::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'session_id' => 'tower_defense_test',
            'game_title' => 'Tower Defense Game',
            'game_json' => $towerDefenseGame,
            'assets_manifest' => [],
            'version' => 1,
            'status' => 'active'
        ]);
        
        expect($session)->not->toBeNull();
        expect($session->game_json['objects'])->toHaveCount(4); // 3 towers + 1 enemy
        expect($session->game_json['variables'])->toHaveCount(4); // score, wave, health, currency
        
        // Verify tower types exist
        $objects = collect($session->game_json['objects']);
        expect($objects->where('name', 'BasicTower')->count())->toBe(1);
        expect($objects->where('name', 'SplashTower')->count())->toBe(1);
        expect($objects->where('name', 'FreezeTower')->count())->toBe(1);
        expect($objects->where('name', 'BasicEnemy')->count())->toBe(1);
        
        // Test first modification - enhance towers
        $modifiedGame = $session->game_json;
        
        // Update basic tower fire rate
        foreach ($modifiedGame['objects'] as &$object) {
            if ($object['name'] === 'BasicTower') {
                foreach ($object['variables'] as &$variable) {
                    if ($variable['name'] === 'fireRate') {
                        $variable['value'] = 2.0; // Double fire rate
                    }
                }
            }
            if ($object['name'] === 'SplashTower') {
                foreach ($object['variables'] as &$variable) {
                    if ($variable['name'] === 'splashRadius') {
                        $variable['value'] = 75; // Increase splash radius
                    }
                }
            }
        }
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 2
        ]);
        
        expect($session->version)->toBe(2);
        
        // Test second modification - add enemy variety
        $modifiedGame = $session->game_json;
        
        // Add fast enemy
        $modifiedGame['objects'][] = [
            'name' => 'FastEnemy',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'TopDownMovement'],
                ['type' => 'Health']
            ],
            'variables' => [
                ['name' => 'health', 'value' => 25],
                ['name' => 'speed', 'value' => 120],
                ['name' => 'reward', 'value' => 15]
            ]
        ];
        
        // Add armored enemy
        $modifiedGame['objects'][] = [
            'name' => 'ArmoredEnemy',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'TopDownMovement'],
                ['type' => 'Health']
            ],
            'variables' => [
                ['name' => 'health', 'value' => 100],
                ['name' => 'speed', 'value' => 30],
                ['name' => 'armor', 'value' => 5],
                ['name' => 'reward', 'value' => 25]
            ]
        ];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 3
        ]);
        
        expect($session->version)->toBe(3);
        expect(count($session->game_json['objects']))->toBe(6); // 3 towers + 3 enemies
        
        // Test third modification - add wave system
        $modifiedGame = $session->game_json;
        
        // Add wave-related variables
        $modifiedGame['variables'][] = ['name' => 'currentWave', 'value' => 1];
        $modifiedGame['variables'][] = ['name' => 'totalWaves', 'value' => 10];
        $modifiedGame['variables'][] = ['name' => 'enemiesPerWave', 'value' => 5];
        $modifiedGame['variables'][] = ['name' => 'waveMultiplier', 'value' => 1.2];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 4
        ]);
        
        expect($session->version)->toBe(4);
        expect(count($session->game_json['variables']))->toBe(8); // Original 4 + 4 wave variables
        
        // Verify conversation tracking
        $conversationHistory = [
            [
                'role' => 'user',
                'content' => 'Create a tower defense game with 3 different tower types',
                'timestamp' => now()->toISOString()
            ],
            [
                'role' => 'assistant',
                'content' => 'I\'ve created a tower defense game with BasicTower, SplashTower, and FreezeTower',
                'thinking_process' => 'Created three distinct tower types with different capabilities',
                'timestamp' => now()->toISOString()
            ],
            [
                'role' => 'user',
                'content' => 'Make the basic tower shoot faster and increase splash radius',
                'timestamp' => now()->toISOString()
            ],
            [
                'role' => 'assistant',
                'content' => 'Updated BasicTower fire rate to 2.0 and SplashTower radius to 75',
                'thinking_process' => 'Modified existing tower properties to enhance gameplay',
                'timestamp' => now()->toISOString()
            ],
            [
                'role' => 'user',
                'content' => 'Add fast and armored enemy types',
                'timestamp' => now()->toISOString()
            ],
            [
                'role' => 'assistant',
                'content' => 'Added FastEnemy with high speed/low health and ArmoredEnemy with high health/low speed',
                'thinking_process' => 'Created enemy variety to add strategic depth',
                'timestamp' => now()->toISOString()
            ]
        ];
        
        $session->update([
            'conversation_history' => json_encode($conversationHistory)
        ]);
        
        $storedConversation = json_decode($session->conversation_history, true);
        expect($storedConversation)->toHaveCount(6);
        expect($storedConversation[0]['role'])->toBe('user');
        expect($storedConversation[1]['role'])->toBe('assistant');
        expect($storedConversation[1])->toHaveKey('thinking_process');
    });
    
    test('can create platformer game with physics modifications', function () {
        $platformerGame = [
            'properties' => [
                'name' => 'Platformer Game',
                'description' => 'A 2D platformer with physics and controls',
                'author' => 'Test User',
                'version' => '1.0.0'
            ],
            'objects' => [
                [
                    'name' => 'Player',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'PlatformerObject'],
                        ['type' => 'Health']
                    ],
                    'variables' => [
                        ['name' => 'jumpHeight', 'value' => 300],
                        ['name' => 'speed', 'value' => 200],
                        ['name' => 'health', 'value' => 3],
                        ['name' => 'hasDoubleJump', 'value' => false]
                    ]
                ],
                [
                    'name' => 'Platform',
                    'type' => 'TiledSprite',
                    'behaviors' => [
                        ['type' => 'Platform']
                    ]
                ],
                [
                    'name' => 'Coin',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'Collectible']
                    ],
                    'variables' => [
                        ['name' => 'value', 'value' => 10]
                    ]
                ],
                [
                    'name' => 'Enemy',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'PlatformerObject'],
                        ['type' => 'Health']
                    ],
                    'variables' => [
                        ['name' => 'health', 'value' => 1],
                        ['name' => 'speed', 'value' => 50]
                    ]
                ]
            ],
            'layouts' => [
                [
                    'name' => 'Level1',
                    'title' => 'First Level',
                    'objects' => ['Player', 'Platform', 'Coin', 'Enemy']
                ]
            ],
            'variables' => [
                ['name' => 'score', 'value' => 0],
                ['name' => 'lives', 'value' => 3],
                ['name' => 'level', 'value' => 1]
            ]
        ];
        
        $session = GDevelopGameSession::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'session_id' => 'platformer_test',
            'game_title' => 'Platformer Game',
            'game_json' => $platformerGame,
            'assets_manifest' => [],
            'version' => 1,
            'status' => 'active'
        ]);
        
        // Test physics modification - double jump
        $modifiedGame = $session->game_json;
        foreach ($modifiedGame['objects'] as &$object) {
            if ($object['name'] === 'Player') {
                // Increase jump height by 50%
                foreach ($object['variables'] as &$variable) {
                    if ($variable['name'] === 'jumpHeight') {
                        $variable['value'] = 450; // 300 * 1.5
                    }
                    if ($variable['name'] === 'hasDoubleJump') {
                        $variable['value'] = true;
                    }
                }
                // Add double jump variables
                $object['variables'][] = ['name' => 'doubleJumpUsed', 'value' => false];
            }
        }
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 2
        ]);
        
        // Test wall jumping mechanics
        $modifiedGame = $session->game_json;
        foreach ($modifiedGame['objects'] as &$object) {
            if ($object['name'] === 'Player') {
                $object['variables'][] = ['name' => 'canWallJump', 'value' => true];
                $object['variables'][] = ['name' => 'wallSlideSpeed', 'value' => 50];
                $object['variables'][] = ['name' => 'wallJumpForce', 'value' => 400];
            }
        }
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 3
        ]);
        
        // Test multiple levels
        $modifiedGame = $session->game_json;
        $modifiedGame['layouts'][] = [
            'name' => 'Level2',
            'title' => 'Second Level',
            'objects' => ['Player', 'Platform', 'Coin', 'Enemy', 'MovingPlatform']
        ];
        $modifiedGame['layouts'][] = [
            'name' => 'Level3',
            'title' => 'Third Level',
            'objects' => ['Player', 'Platform', 'Coin', 'Enemy', 'MovingPlatform']
        ];
        
        // Add moving platform object
        $modifiedGame['objects'][] = [
            'name' => 'MovingPlatform',
            'type' => 'TiledSprite',
            'behaviors' => [
                ['type' => 'Platform'],
                ['type' => 'PathMovement']
            ],
            'variables' => [
                ['name' => 'speed', 'value' => 100],
                ['name' => 'direction', 'value' => 1]
            ]
        ];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 4
        ]);
        
        expect($session->version)->toBe(4);
        expect(count($session->game_json['layouts']))->toBe(3);
        expect(count($session->game_json['objects']))->toBe(5); // Player, Platform, Coin, Enemy, MovingPlatform
        
        // Verify player has all physics enhancements
        $player = collect($session->game_json['objects'])->where('name', 'Player')->first();
        $playerVars = collect($player['variables']);
        expect($playerVars->where('name', 'hasDoubleJump')->first()['value'])->toBe(true);
        expect($playerVars->where('name', 'canWallJump')->first()['value'])->toBe(true);
        expect($playerVars->where('name', 'jumpHeight')->first()['value'])->toBe(450);
    });
    
    test('can create puzzle game with logic systems', function () {
        $puzzleGame = [
            'properties' => [
                'name' => 'Match-3 Puzzle',
                'description' => 'A match-3 puzzle game with special gems',
                'author' => 'Test User',
                'version' => '1.0.0'
            ],
            'objects' => [
                [
                    'name' => 'Gem',
                    'type' => 'Sprite',
                    'behaviors' => [
                        ['type' => 'Draggable']
                    ],
                    'variables' => [
                        ['name' => 'color', 'value' => 'red'],
                        ['name' => 'gridX', 'value' => 0],
                        ['name' => 'gridY', 'value' => 0]
                    ]
                ],
                [
                    'name' => 'Grid',
                    'type' => 'Sprite',
                    'variables' => [
                        ['name' => 'width', 'value' => 8],
                        ['name' => 'height', 'value' => 8]
                    ]
                ]
            ],
            'layouts' => [
                [
                    'name' => 'PuzzleLevel',
                    'title' => 'Match-3 Level',
                    'objects' => ['Gem', 'Grid']
                ]
            ],
            'variables' => [
                ['name' => 'score', 'value' => 0],
                ['name' => 'moves', 'value' => 30],
                ['name' => 'level', 'value' => 1]
            ]
        ];
        
        $session = GDevelopGameSession::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'session_id' => 'puzzle_test',
            'game_title' => 'Match-3 Puzzle',
            'game_json' => $puzzleGame,
            'assets_manifest' => [],
            'version' => 1,
            'status' => 'active'
        ]);
        
        // Test special gems addition
        $modifiedGame = $session->game_json;
        
        // Add bomb gem
        $modifiedGame['objects'][] = [
            'name' => 'BombGem',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'Draggable'],
                ['type' => 'Explosive']
            ],
            'variables' => [
                ['name' => 'explosionRadius', 'value' => 3],
                ['name' => 'gridX', 'value' => 0],
                ['name' => 'gridY', 'value' => 0]
            ]
        ];
        
        // Add line gem
        $modifiedGame['objects'][] = [
            'name' => 'LineGem',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'Draggable'],
                ['type' => 'LineClearer']
            ],
            'variables' => [
                ['name' => 'direction', 'value' => 'horizontal'],
                ['name' => 'gridX', 'value' => 0],
                ['name' => 'gridY', 'value' => 0]
            ]
        ];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 2
        ]);
        
        // Test combo system
        $modifiedGame = $session->game_json;
        $modifiedGame['variables'][] = ['name' => 'combo', 'value' => 0];
        $modifiedGame['variables'][] = ['name' => 'comboMultiplier', 'value' => 1.0];
        $modifiedGame['variables'][] = ['name' => 'maxCombo', 'value' => 0];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 3
        ]);
        
        // Test locked gems and obstacles
        $modifiedGame = $session->game_json;
        
        // Add locked gem
        $modifiedGame['objects'][] = [
            'name' => 'LockedGem',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'Locked']
            ],
            'variables' => [
                ['name' => 'locksRemaining', 'value' => 2],
                ['name' => 'gridX', 'value' => 0],
                ['name' => 'gridY', 'value' => 0]
            ]
        ];
        
        // Add ice block
        $modifiedGame['objects'][] = [
            'name' => 'IceBlock',
            'type' => 'Sprite',
            'behaviors' => [
                ['type' => 'Obstacle']
            ],
            'variables' => [
                ['name' => 'health', 'value' => 1],
                ['name' => 'gridX', 'value' => 0],
                ['name' => 'gridY', 'value' => 0]
            ]
        ];
        
        // Add hint system variables
        $modifiedGame['variables'][] = ['name' => 'hintsAvailable', 'value' => 3];
        $modifiedGame['variables'][] = ['name' => 'hintCooldown', 'value' => 0];
        
        $session->update([
            'game_json' => $modifiedGame,
            'version' => 4
        ]);
        
        expect($session->version)->toBe(4);
        expect(count($session->game_json['objects']))->toBe(6); // Gem, Grid, BombGem, LineGem, LockedGem, IceBlock
        expect(count($session->game_json['variables']))->toBe(8); // Original 3 + 5 new variables
        
        // Verify special gems exist
        $objects = collect($session->game_json['objects']);
        expect($objects->where('name', 'BombGem')->count())->toBe(1);
        expect($objects->where('name', 'LineGem')->count())->toBe(1);
        expect($objects->where('name', 'LockedGem')->count())->toBe(1);
        expect($objects->where('name', 'IceBlock')->count())->toBe(1);
        
        // Verify combo system variables
        $variables = collect($session->game_json['variables']);
        expect($variables->where('name', 'combo')->count())->toBe(1);
        expect($variables->where('name', 'hintsAvailable')->count())->toBe(1);
    });
    
    test('validates game session persistence and recovery', function () {
        // Create initial session
        $gameData = [
            'properties' => ['name' => 'Test Game'],
            'objects' => [['name' => 'TestObject']],
            'layouts' => [['name' => 'TestLayout']],
            'variables' => [['name' => 'testVar', 'value' => 0]]
        ];
        
        $session = GDevelopGameSession::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'session_id' => 'persistence_test',
            'game_title' => 'Persistence Test Game',
            'game_json' => $gameData,
            'assets_manifest' => ['sprite1.png', 'sound1.wav'],
            'version' => 1,
            'status' => 'active',
            'preview_url' => 'http://localhost:3000/preview/persistence_test',
            'export_url' => null
        ]);
        
        // Simulate multiple modifications
        for ($i = 2; $i <= 5; $i++) {
            $modifiedData = $session->game_json;
            $modifiedData['objects'][] = ['name' => "TestObject{$i}"];
            $modifiedData['variables'][] = ['name' => "testVar{$i}", 'value' => $i];
            
            $session->update([
                'game_json' => $modifiedData,
                'version' => $i
            ]);
        }
        
        // Verify final state
        expect($session->version)->toBe(5);
        expect(count($session->game_json['objects']))->toBe(5);
        expect(count($session->game_json['variables']))->toBe(5);
        
        // Test session recovery
        $recoveredSession = GDevelopGameSession::where('session_id', 'persistence_test')->first();
        expect($recoveredSession)->not->toBeNull();
        expect($recoveredSession->version)->toBe(5);
        expect($recoveredSession->game_title)->toBe('Persistence Test Game');
        expect($recoveredSession->status)->toBe('active');
        
        // Test session cleanup
        $recoveredSession->update(['status' => 'archived']);
        expect($recoveredSession->fresh()->status)->toBe('archived');
    });
});