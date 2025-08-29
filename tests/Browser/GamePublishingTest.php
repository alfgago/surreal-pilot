<?php

use App\Models\User;
use App\Models\Game;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
    
    // Create a workspace for testing
    $this->workspace = $this->testUser->workspaces()->create([
        'name' => 'Publishing Test Workspace',
        'engine' => 'playcanvas',
    ]);
    
    // Create a game for testing
    $this->game = $this->workspace->games()->create([
        'name' => 'Publishing Test Game',
        'engine' => 'playcanvas',
        'user_id' => $this->testUser->id,
        'build_status' => 'success',
    ]);
});

test('user can access game publishing page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game detail page
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="game-detail-page"]', 10)
            ->assertPresent('[data-testid="game-publishing"]')
            ->assertSee('Publishing');
    });
});

test('user can build game for publishing', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and start build
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="build-game-button"]', 10)
            ->click('[data-testid="build-game-button"]')
            ->waitFor('[data-testid="build-modal"]', 10)
            ->assertPresent('[data-testid="build-modal"]')
            ->check('minify')
            ->check('optimize_assets')
            ->press('Start Build')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Build started');
    });
});

test('user can publish game after successful build', function () {
    // Ensure game has successful build
    $this->game->update(['build_status' => 'success']);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and publish
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="publish-game-button"]', 10)
            ->click('[data-testid="publish-game-button"]')
            ->waitFor('[data-testid="publish-modal"]', 10)
            ->assertPresent('[data-testid="publish-modal"]')
            ->check('is_public')
            ->check('allow_embedding')
            ->check('show_controls')
            ->press('Publish Game')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Game published');
    });
});

test('published game shows sharing options', function () {
    // Set game as published
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'test-share-token',
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and check sharing options
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="sharing-options"]', 10)
            ->assertPresent('[data-testid="sharing-options"]')
            ->assertPresent('[data-testid="share-url"]')
            ->assertPresent('[data-testid="embed-code"]')
            ->assertSee('Share URL')
            ->assertSee('Embed Code');
    });
});

test('user can copy share URL', function () {
    // Set game as published
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'test-share-token',
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and copy share URL
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="copy-share-url"]', 10)
            ->click('[data-testid="copy-share-url"]')
            ->waitFor('[data-testid="success-message"]', 5)
            ->assertSee('URL copied');
    });
});

test('user can copy embed code', function () {
    // Set game as published with embedding enabled
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'test-embed-token',
        'sharing_settings' => ['allow_embedding' => true],
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and copy embed code
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="copy-embed-code"]', 10)
            ->click('[data-testid="copy-embed-code"]')
            ->waitFor('[data-testid="success-message"]', 5)
            ->assertSee('Embed code copied');
    });
});

test('user can unpublish game', function () {
    // Set game as published
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'test-unpublish-token',
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and unpublish
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="unpublish-game-button"]', 10)
            ->click('[data-testid="unpublish-game-button"]')
            ->waitFor('[data-testid="confirm-modal"]', 10)
            ->assertPresent('[data-testid="confirm-modal"]')
            ->assertSee('Unpublish Game')
            ->press('Unpublish')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Game unpublished');
    });
});

test('user can view build history', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and view build history
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="build-history"]', 10)
            ->assertPresent('[data-testid="build-history"]')
            ->assertSee('Build History');
    });
});

test('user can view game analytics', function () {
    // Set game as published to have analytics
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'play_count' => 42,
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to game and view analytics
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="game-analytics"]', 10)
            ->assertPresent('[data-testid="game-analytics"]')
            ->assertSee('Analytics')
            ->assertSee('42'); // Play count
    });
});

test('shared game page loads correctly', function () {
    // Set game as published
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'public-share-token',
    ]);
    
    $this->browse(function (Browser $browser) {
        // Visit shared game page (no login required)
        $browser->visit('/games/shared/public-share-token')
            ->waitFor('[data-testid="shared-game-page"]', 10)
            ->assertPresent('[data-testid="shared-game-page"]')
            ->assertPresent('[data-testid="game-player"]')
            ->assertSee('Publishing Test Game');
    });
});

test('embedded game page loads correctly', function () {
    // Set game as published with embedding enabled
    $this->game->update([
        'status' => 'published',
        'is_public' => true,
        'share_token' => 'embed-token',
        'sharing_settings' => ['allow_embedding' => true],
    ]);
    
    $this->browse(function (Browser $browser) {
        // Visit embedded game page (no login required)
        $browser->visit('/games/embed/embed-token')
            ->waitFor('[data-testid="embedded-game"]', 10)
            ->assertPresent('[data-testid="embedded-game"]')
            ->assertPresent('[data-testid="game-player"]');
    });
});

test('game publishing is responsive on mobile', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->resize(375, 667) // iPhone SE size
            ->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/games/' . $this->game->id)
            ->waitFor('[data-testid="game-detail-page"]', 10)
            ->assertPresent('[data-testid="game-publishing"]')
            ->resize(1920, 1080); // Reset to desktop
    });
});