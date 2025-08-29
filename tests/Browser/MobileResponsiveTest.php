<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

    test('mobile landing page responsive design', function () {
        $this->browse(function (Browser $browser) {
            // Test iPhone 12 Pro dimensions
            $browser->resize(390, 844)
                    ->visit('/')
                    ->waitFor('h1', 5);
            
            // Verify mobile navigation is present
            $browser->assertPresent('[data-testid="mobile-menu-button"]')
                    ->assertMissing('[data-testid="desktop-navigation"]');
            
            // Test tablet dimensions (iPad)
            $browser->resize(768, 1024);
            
            // Verify responsive layout adjustments
            $browser->assertPresent('nav')
                    ->assertVisible('main');
            
            // Test large mobile (iPhone 14 Pro Max)
            $browser->resize(430, 932);
            
            // Verify content is still accessible
            $browser->assertSee('SurrealPilot')
                    ->assertPresent('[data-testid="mobile-menu-button"]');
        });
});

test('mobile dashboard responsive design', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->resize(390, 844)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Verify mobile dashboard layout
            $browser->assertPresent('[data-testid="mobile-workspace-switcher"]')
                    ->assertPresent('[data-testid="mobile-user-menu"]');
            
            // Test workspace switcher on mobile
            $browser->click('[data-testid="mobile-workspace-switcher"]')
                    ->waitFor('[data-testid="workspace-dropdown"]', 2)
                    ->assertVisible('[data-testid="workspace-dropdown"]');
            
            // Test tablet layout
            $browser->resize(768, 1024);
            
            // Verify tablet-specific adjustments
            $browser->assertPresent('[data-testid="dashboard-content"]')
                    ->assertVisible('[data-testid="workspace-switcher"]');
        });
});

test('mobile chat interface responsive', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->resize(390, 844)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Verify mobile chat layout
            $browser->assertPresent('[data-testid="mobile-chat-header"]')
                    ->assertPresent('[data-testid="message-input"]')
                    ->assertPresent('[data-testid="mobile-conversation-toggle"]');
            
            // Test conversation sidebar toggle on mobile
            $browser->click('[data-testid="mobile-conversation-toggle"]')
                    ->waitFor('[data-testid="conversation-sidebar"]', 2)
                    ->assertVisible('[data-testid="conversation-sidebar"]');
            
            // Test message input on mobile
            $browser->type('[data-testid="message-input"]', 'Test mobile message')
                    ->assertValue('[data-testid="message-input"]', 'Test mobile message');
            
            // Verify send button is accessible
            $browser->assertPresent('[data-testid="send-button"]')
                    ->assertVisible('[data-testid="send-button"]');
        });
});

test('mobile games management responsive', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->resize(390, 844)
                    ->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            // Verify mobile games grid layout
            $browser->assertPresent('[data-testid="games-grid"]')
                    ->assertPresent('[data-testid="mobile-create-game-button"]');
            
            // Test game card responsiveness
            $gameCards = $browser->elements('[data-testid^="game-card-"]');
            if (count($gameCards) > 0) {
                $browser->assertVisible('[data-testid^="game-card-"]');
            }
            
            // Test create game button on mobile
            $browser->click('[data-testid="mobile-create-game-button"]')
                    ->waitFor('[data-testid="create-game-modal"]', 3)
                    ->assertVisible('[data-testid="create-game-modal"]');
        });
});

test('mobile forms responsive', function () {
        $this->browse(function (Browser $browser) {
            // Test login form on mobile
            $browser->resize(390, 844)
                    ->visit('/login')
                    ->waitFor('form', 5);
            
            // Verify form elements are properly sized
            $browser->assertPresent('input[name="email"]')
                    ->assertPresent('input[name="password"]')
                    ->assertPresent('button[type="submit"]');
            
            // Test form input accessibility
            $browser->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->assertValue('email', 'test@example.com');
            
            // Test registration form
            $browser->visit('/register')
                    ->waitFor('form', 5)
                    ->assertPresent('input[name="name"]')
                    ->assertPresent('input[name="email"]')
                    ->assertPresent('input[name="password"]')
                    ->assertPresent('input[name="password_confirmation"]');
        });
});

test('mobile navigation functionality', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->resize(390, 844)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Test mobile menu toggle
            $browser->click('[data-testid="mobile-menu-button"]')
                    ->waitFor('[data-testid="mobile-navigation"]', 2)
                    ->assertVisible('[data-testid="mobile-navigation"]');
            
            // Test navigation links
            $browser->click('[data-testid="mobile-nav-chat"]')
                    ->waitForLocation('/chat')
                    ->assertPathIs('/chat');
            
            // Navigate back and test games link
            $browser->click('[data-testid="mobile-menu-button"]')
                    ->waitFor('[data-testid="mobile-navigation"]', 2)
                    ->click('[data-testid="mobile-nav-games"]')
                    ->waitForLocation('/games')
                    ->assertPathIs('/games');
        });
});

test('touch interactions', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->resize(390, 844)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test touch-friendly button sizes (minimum 44px)
            $sendButton = $browser->element('[data-testid="send-button"]');
            $buttonSize = $browser->driver->executeScript('
                const button = arguments[0];
                const rect = button.getBoundingClientRect();
                return { width: rect.width, height: rect.height };
            ', $sendButton);
            
            $this->assertGreaterThanOrEqual(44, $buttonSize['width'], 'Send button width should be at least 44px for touch');
            $this->assertGreaterThanOrEqual(44, $buttonSize['height'], 'Send button height should be at least 44px for touch');
        });
});