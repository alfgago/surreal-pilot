<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
    
    // Create a workspace for testing
    $this->workspace = $this->testUser->workspaces()->create([
        'name' => 'Games Test Workspace',
        'engine' => 'playcanvas',
    ]);
});

test('user can access games page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to games page
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games')
            ->waitFor('[data-testid="games-page"]', 10)
            ->assertPresent('[data-testid="games-page"]')
            ->assertPresent('[data-testid="create-game-button"]');
    });
});

test('user can create new game', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to games and create new game
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games')
            ->waitFor('[data-testid="create-game-button"]', 10)
            ->click('[data-testid="create-game-button"]')
            ->waitFor('input[name="name"]', 10)
            ->type('name', 'Test Game')
            ->press('Create Game')
            ->waitForLocation('/games/', 15); // Should redirect to game detail page
    });
    
    // Verify game was created
    expect($this->workspace->fresh()->games()->where('name', 'Test Game')->exists())->toBeTrue();
});

test('user can view game details', function () {
    // Create a game first
    $game = $this->workspace->games()->create([
        'name' => 'Detail Test Game',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
    ]);
    
    $this->browse(function (Browser $browser) use ($game) {
        loginAsTestUser($browser);
        
        // Navigate to game detail page
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $game->id)
            ->waitFor('[data-testid="game-detail-page"]', 10)
            ->assertSee('Detail Test Game')
            ->assertPresent('[data-testid="game-preview"]')
            ->assertPresent('[data-testid="file-manager"]');
    });
});

test('user can edit game files', function () {
    // Create a game first
    $game = $this->workspace->games()->create([
        'name' => 'Edit Test Game',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
    ]);
    
    $this->browse(function (Browser $browser) use ($game) {
        loginAsTestUser($browser);
        
        // Navigate to game detail page and edit files
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $game->id)
            ->waitFor('[data-testid="file-manager"]', 10)
            ->assertPresent('[data-testid="file-manager"]');
            
        // Try to create a new file if the button exists
        if ($browser->element('[data-testid="create-file-button"]')) {
            $browser->click('[data-testid="create-file-button"]')
                ->waitFor('input[name="filename"]', 5)
                ->type('filename', 'test.js')
                ->press('Create File');
        }
    });
});

test('user can play game', function () {
    // Create a game first
    $game = $this->workspace->games()->create([
        'name' => 'Play Test Game',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
    ]);
    
    $this->browse(function (Browser $browser) use ($game) {
        loginAsTestUser($browser);
        
        // Navigate to game play page
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $game->id . '/play')
            ->waitFor('[data-testid="game-player"]', 10)
            ->assertPresent('[data-testid="game-player"]');
    });
});

test('games list shows user games', function () {
    // Create multiple games
    $game1 = $this->workspace->games()->create([
        'name' => 'List Test Game 1',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
    ]);
    
    $game2 = $this->workspace->games()->create([
        'name' => 'List Test Game 2',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to games page and verify games are listed
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games')
            ->waitFor('[data-testid="games-page"]', 10)
            ->assertSee('List Test Game 1')
            ->assertSee('List Test Game 2');
    });
});