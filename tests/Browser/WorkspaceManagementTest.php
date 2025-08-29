<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

test('user can access engine selection page after login', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->assertPathIs('/engine-selection')
            ->assertSee('Choose Your Engine')
            ->assertPresent('[data-testid="unreal-engine-option"]')
            ->assertPresent('[data-testid="playcanvas-option"]');
    });
});

test('user can select unreal engine', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->assertPathIs('/engine-selection')
            ->click('[data-testid="unreal-engine-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->assertPathIs('/workspace-selection');
    });
});

test('user can select playcanvas engine', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->assertPathIs('/engine-selection')
            ->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->assertPathIs('/workspace-selection');
    });
});

test('user can access workspace selection page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Select an engine first
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->assertSee('Select Workspace')
            ->assertPresent('[data-testid="create-workspace-button"]');
    });
});

test('user can create new workspace', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to workspace selection
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="create-workspace-button"]')
            ->waitFor('input[name="name"]', 10)
            ->type('name', 'Test Workspace')
            ->press('Create Workspace')
            ->waitForLocation('/chat', 15)
            ->assertPathIs('/chat');
    });
    
    // Verify workspace was created
    expect($this->testUser->fresh()->workspaces()->where('name', 'Test Workspace')->exists())->toBeTrue();
});

test('user can switch between workspaces', function () {
    // Create a workspace first
    $workspace = $this->testUser->workspaces()->create([
        'name' => 'Switch Test Workspace',
        'engine' => 'playcanvas',
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat (main app)
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->testUser->workspaces->first()->id . '"]')
            ->waitForLocation('/chat', 15)
            ->assertPathIs('/chat');
            
        // Switch workspace using workspace switcher
        $browser->click('[data-testid="workspace-switcher"]')
            ->waitFor('[data-testid="workspace-option"]', 5)
            ->click('[data-testid="workspace-option"]:first-child')
            ->pause(2000); // Wait for workspace switch
    });
});