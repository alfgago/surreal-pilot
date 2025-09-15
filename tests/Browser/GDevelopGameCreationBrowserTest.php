<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->user->companies()->attach($this->company);
    
    $this->workspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
        'engine_type' => 'gdevelop'
    ]);
});

describe('Tower Defense Game Browser Testing', function () {
    test('user can create and iterate on tower defense game through browser interface', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10)
                ->assertSee('GDevelop Chat')
                ->type('@chat-input', 'Create a tower defense game with 3 different tower types: basic shooter, splash damage, and freeze tower. Add enemies that spawn from the left.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('tower defense game')
                ->assertSee('Preview')
                ->click('@preview-button')
                ->waitFor('@game-preview', 10)
                ->assertPresent('@game-iframe')
                ->within('@game-iframe', function ($iframe) {
                    $iframe->waitForText('Loading', 5)
                        ->waitUntilMissing('Loading', 15);
                });

            // First iteration - modify tower properties
            $browser->type('@chat-input', 'Make the basic tower shoot twice as fast and increase the splash damage radius of the splash tower')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('updated')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Second iteration - add enemy variety
            $browser->type('@chat-input', 'Add flying enemies that can only be targeted by the freeze tower, and make ground enemies move in a zigzag pattern')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('flying enemies')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Third iteration - add game mechanics
            $browser->type('@chat-input', 'Add a wave system with 5 waves, each wave spawning more enemies. Add a health system for the base and a score counter.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('wave system')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test export functionality
            $browser->click('@export-button')
                ->waitFor('@export-modal', 5)
                ->check('@include-assets')
                ->select('@compression-level', 'standard')
                ->click('@start-export')
                ->waitFor('@export-progress', 5)
                ->waitFor('@download-ready', 30)
                ->assertSee('Download')
                ->click('@download-link');

            // Verify game session persistence
            $browser->refresh()
                ->waitFor('@gdevelop-chat-interface', 10)
                ->assertSee('Continue previous session')
                ->click('@continue-session')
                ->waitFor('@chat-history', 5)
                ->assertSeeIn('@chat-history', 'tower defense game')
                ->assertSeeIn('@chat-history', 'wave system');
        });
    });
});

describe('Platformer Game Browser Testing', function () {
    test('user can create platformer game with physics and controls testing', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10)
                ->type('@chat-input', 'Create a 2D platformer game with a player character that can jump and run. Add platforms, collectible coins, enemies, and a goal flag.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('platformer game')
                ->click('@preview-button')
                ->waitFor('@game-preview', 10)
                ->assertPresent('@game-iframe');

            // Test physics modifications
            $browser->type('@chat-input', 'Make the player jump higher and add double jump ability. Also add moving platforms that go up and down.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('double jump')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test controls modification
            $browser->type('@chat-input', 'Add wall jumping ability where the player can jump off walls, and make the player slide down walls slowly.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('wall jumping')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test level design
            $browser->type('@chat-input', 'Create 3 different levels with increasing difficulty. Level 1 should be easy with basic platforms, Level 2 should add moving platforms and more enemies, Level 3 should have complex jumping puzzles.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('3 different levels')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test mobile optimization
            $browser->type('@chat-input', 'Optimize this game for mobile devices with touch controls and on-screen buttons for jumping and moving.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('mobile')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test export with mobile optimization
            $browser->click('@export-button')
                ->waitFor('@export-modal', 5)
                ->check('@optimize-mobile')
                ->click('@start-export')
                ->waitFor('@download-ready', 30)
                ->click('@download-link');
        });
    });
});

describe('Puzzle Game Browser Testing', function () {
    test('user can create puzzle game with logic and interaction systems', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10)
                ->type('@chat-input', 'Create a match-3 puzzle game where players swap adjacent gems to create lines of 3 or more matching colors. Add different colored gems and a grid system.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('match-3 puzzle')
                ->click('@preview-button')
                ->waitFor('@game-preview', 10)
                ->assertPresent('@game-iframe');

            // Test logic system - special gems
            $browser->type('@chat-input', 'Add special gems: bomb gems that clear surrounding gems in a 3x3 area, and line gems that clear entire rows or columns when matched.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('special gems')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test interaction system - combo and scoring
            $browser->type('@chat-input', 'Add a combo system that gives bonus points for consecutive matches without moving, and add a score display with level progression.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('combo system')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test advanced mechanics
            $browser->type('@chat-input', 'Add locked gems that require multiple matches to unlock, and add a moves limit for each level instead of a timer. Show remaining moves on screen.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('locked gems')
                ->assertSee('moves limit')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test power-ups and obstacles
            $browser->type('@chat-input', 'Add power-ups that players can earn: shuffle board, extra moves, and hint system. Also add stone blocks that cannot be moved or matched.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('power-ups')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test export functionality
            $browser->click('@export-button')
                ->waitFor('@export-modal', 5)
                ->select('@compression-level', 'maximum')
                ->click('@start-export')
                ->waitFor('@download-ready', 30)
                ->click('@download-link');
        });
    });
});

describe('Hybrid Game Browser Testing', function () {
    test('user can create complex hybrid game combining multiple genres', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10)
                ->type('@chat-input', 'Create a unique game that combines tower defense with platformer elements. The player can run around the map to manually place towers and collect resources while enemies attack along paths.')
                ->click('@send-message')
                ->waitFor('@chat-response', 20)
                ->assertSee('tower defense')
                ->assertSee('platformer')
                ->click('@preview-button')
                ->waitFor('@game-preview', 10);

            // Add puzzle elements
            $browser->type('@chat-input', 'Add puzzle mini-games that the player must solve to unlock new tower types. Make these puzzles appear as interactive objects on the map.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('puzzle mini-games')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Add RPG elements
            $browser->type('@chat-input', 'Add RPG elements: player leveling system, skill trees for different abilities (tower building, resource gathering, combat), and equipment that affects player stats.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->assertSee('RPG elements')
                ->assertSee('skill trees')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Add multiplayer-style features
            $browser->type('@chat-input', 'Add leaderboards, achievements system, and daily challenges. Include statistics tracking for games played, towers built, and enemies defeated.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('leaderboards')
                ->assertSee('achievements')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test comprehensive export
            $browser->click('@export-button')
                ->waitFor('@export-modal', 5)
                ->check('@include-assets')
                ->check('@optimize-mobile')
                ->select('@compression-level', 'standard')
                ->click('@start-export')
                ->waitFor('@export-progress', 5)
                ->waitUntilMissing('@export-progress', 45)
                ->waitFor('@download-ready', 5)
                ->assertSee('Export completed')
                ->click('@download-link');

            // Verify complex game session data
            $browser->visit('/api/gdevelop/sessions')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'session_id',
                            'game_title',
                            'version',
                            'complexity_score',
                            'interaction_count'
                        ]
                    ]
                ]);
        });
    });
});

describe('Mobile Responsiveness Testing', function () {
    test('games work correctly on mobile viewport', function () {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone SE size
                ->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10)
                ->assertVisible('@mobile-chat-toggle')
                ->type('@chat-input', 'Create a simple tap-to-jump endless runner game optimized for mobile touch controls.')
                ->click('@send-message')
                ->waitFor('@chat-response', 15)
                ->click('@preview-button')
                ->waitFor('@game-preview', 10)
                ->assertPresent('@mobile-game-controls')
                ->tap('@mobile-jump-button')
                ->assertVisible('@game-iframe');

            // Test mobile-specific modifications
            $browser->type('@chat-input', 'Add swipe gestures for left and right movement, and make all UI elements larger for easier touch interaction.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('swipe gestures')
                ->click('@preview-button')
                ->waitFor('@game-preview-updated', 10);

            // Test landscape orientation
            $browser->resize(667, 375) // Landscape
                ->refresh()
                ->waitFor('@gdevelop-chat-interface', 10)
                ->assertVisible('@landscape-layout')
                ->click('@preview-button')
                ->waitFor('@game-preview', 10)
                ->assertPresent('@landscape-game-controls');
        });
    });
});

describe('Performance and Error Handling Testing', function () {
    test('handles complex games and error scenarios gracefully', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit("/workspaces/{$this->workspace->id}")
                ->waitFor('@gdevelop-chat-interface', 10);

            // Test very complex game request
            $complexRequest = 'Create an extremely complex game with 50 different object types, 10 levels, multiple game modes (story, endless, puzzle), advanced AI enemies with pathfinding, particle effects, sound system, save/load functionality, and multiplayer support.';
            
            $browser->type('@chat-input', $complexRequest)
                ->click('@send-message')
                ->waitFor('@chat-response', 30)
                ->assertDontSee('Error')
                ->assertSee('complex game');

            // Test invalid request handling
            $browser->type('@chat-input', 'Delete all game objects and make the game completely empty with no functionality.')
                ->click('@send-message')
                ->waitFor('@chat-response', 10)
                ->assertSee('cannot')
                ->assertDontSee('Error 500');

            // Test network interruption simulation
            $browser->type('@chat-input', 'Add a simple scoring system.')
                ->click('@send-message')
                ->waitFor('@loading-indicator', 2)
                ->refresh() // Simulate network interruption
                ->waitFor('@gdevelop-chat-interface', 10)
                ->assertSee('Session recovered')
                ->assertVisible('@continue-session');

            // Test export with large game
            $browser->click('@export-button')
                ->waitFor('@export-modal', 5)
                ->click('@start-export')
                ->waitFor('@export-progress', 5)
                ->waitUntilMissing('@export-progress', 60)
                ->assertSee('Export completed');
        });
    });
});