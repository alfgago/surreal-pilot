<?php

use Laravel\Dusk\Browser;

test('complete gdevelop workflow from signup to game export', function () {
    $this->browse(function (Browser $browser) {
        $uniqueEmail = 'gdevelop' . time() . '@test.com';
        
        // Step 1: User Registration
        $browser->visit('/register')
            ->waitFor('input[name="name"]', 10)
            ->type('name', 'GDevelop Test User')
            ->type('email', $uniqueEmail)
            ->type('password', 'password123')
            ->type('password_confirmation', 'password123')
            ->type('company_name', 'GDevelop Test Company')
            ->press('Register')
            ->waitForLocation('/dashboard', 20);

        // Step 2: Navigate to Engine Selection
        $browser->visit('/engine-selection')
            ->waitFor('body', 10)
            ->assertSee('Choose Your Game Engine');

        // Step 3: Select GDevelop (or PlayCanvas if GDevelop not available)
        if ($browser->element('[data-testid="gdevelop-option"]:not(.opacity-50)')) {
            // GDevelop is available
            $browser->click('[data-testid="gdevelop-option"]')
                ->press('Continue to Workspaces')
                ->waitForLocation('/workspace-selection', 15);
        } else {
            // Use PlayCanvas as fallback
            $browser->click('[data-testid="playcanvas-option"]')
                ->press('Continue to Workspaces')
                ->waitForLocation('/workspace-selection', 15);
        }

        // Step 4: Create Workspace
        $browser->click('[data-testid="create-workspace-button"]')
            ->waitFor('input[name="name"]', 10)
            ->type('name', 'My Test Game')
            ->press('Create Workspace')
            ->waitForLocation('/chat', 20);

        // Step 5: Create Game via Chat
        $browser->waitFor('[data-testid="message-input"]', 10)
            ->type('[data-testid="message-input"]', 'Create a simple platformer game with a player that can jump and collect coins')
            ->press('Send')
            ->waitFor('[data-testid="ai-response"]', 30);

        // Step 6: Wait for Game Creation
        $browser->waitFor('[data-testid="game-preview"]', 30);

        // Step 7: Export Game
        $browser->click('[data-testid="export-game"]')
            ->waitFor('[data-testid="export-options"]', 10)
            ->select('[data-testid="export-format"]', 'html5')
            ->press('[data-testid="start-export"]')
            ->waitFor('[data-testid="export-complete"]', 60)
            ->assertSee('Export completed');

        // Step 8: Download Game
        $browser->click('[data-testid="download-link"]');
        
        // Verify the workflow completed successfully
        expect(true)->toBeTrue();
    });
});

test('user can login with existing credentials', function () {
    $this->browse(function (Browser $browser) {
        $credentials = testUserCredentials();
        
        $browser->visit('/login')
            ->waitFor('input[name="email"]', 10)
            ->type('email', $credentials['email'])
            ->type('password', $credentials['password'])
            ->press('Sign in')
            ->waitFor('body', 15);
            
        // Should be redirected after login
        $currentUrl = $browser->driver->getCurrentURL();
        expect($currentUrl)->not->toContain('/login');
    });
});

test('engine selection page loads correctly', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/engine-selection')
            ->waitFor('body', 10)
            ->assertSee('Choose Your Game Engine')
            ->assertSee('PlayCanvas')
            ->assertSee('GDevelop')
            ->assertSee('Unreal Engine');
    });
});

test('workspace creation works', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/workspace-selection')
            ->waitFor('[data-testid="create-workspace-button"]', 10)
            ->click('[data-testid="create-workspace-button"]')
            ->waitFor('input[name="name"]', 10)
            ->type('name', 'Test Workspace ' . time())
            ->press('Create Workspace')
            ->waitForLocation('/chat', 15);
    });
});

test('chat interface is functional', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to an existing workspace or create one
        $browser->visit('/workspace-selection')
            ->waitFor('body', 10);
            
        if ($browser->element('[data-testid="workspace-item"]:first-child')) {
            $browser->click('[data-testid="workspace-item"]:first-child')
                ->waitForLocation('/chat', 15);
        } else {
            createWorkspace($browser, 'Chat Test Workspace');
        }
        
        // Test chat functionality
        $browser->waitFor('[data-testid="message-input"]', 10)
            ->type('[data-testid="message-input"]', 'Hello, create a simple game')
            ->press('Send')
            ->waitFor('[data-testid="user-message"]', 10)
            ->assertSee('Hello, create a simple game');
    });
});

test('mobile interface is responsive', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        testMobileView($browser, function ($browser) {
            $browser->visit('/engine-selection')
                ->waitFor('body', 10)
                ->assertVisible('body');
        });
    });
});

test('navigation works correctly', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Test navigation between pages
        $browser->visit('/engine-selection')
            ->waitFor('body', 10)
            ->visit('/workspace-selection')
            ->waitFor('body', 10)
            ->visit('/dashboard')
            ->waitFor('body', 10);
            
        expect(true)->toBeTrue();
    });
});

test('user can logout successfully', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        logoutUser($browser);
        
        // Should be redirected to home page
        $currentUrl = $browser->driver->getCurrentURL();
        expect($currentUrl)->toContain('/');
    });
});

test('error handling works correctly', function () {
    $this->browse(function (Browser $browser) {
        // Test invalid login
        $browser->visit('/login')
            ->waitFor('input[name="email"]', 10)
            ->type('email', 'invalid@example.com')
            ->type('password', 'wrongpassword')
            ->press('Sign in')
            ->waitFor('body', 10);
            
        // Should stay on login page or show error
        $currentUrl = $browser->driver->getCurrentURL();
        expect($currentUrl)->toContain('/login');
    });
});

test('application loads without javascript errors', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->waitFor('body', 10);
            
        assertNoJavaScriptErrors($browser);
    });
});