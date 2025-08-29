<?php

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;



    test('keyboard navigation accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->waitFor('form', 5);
            
            // Test tab navigation through form elements
            $browser->keys('body', ['{tab}']) // Focus email input
                    ->assertFocused('input[name="email"]')
                    ->keys('body', ['{tab}']) // Focus password input
                    ->assertFocused('input[name="password"]')
                    ->keys('body', ['{tab}']) // Focus submit button
                    ->assertFocused('button[type="submit"]');
            
            // Test form submission with Enter key
            $browser->type('email', 'alfredo@5e.cr')
                    ->type('password', 'Test123!')
                    ->keys('input[name="password"]', ['{enter}'])
                    ->waitForLocation('/dashboard');
        });
});

test('screen reader compatibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Check for proper ARIA labels
            $browser->assertAttribute('[data-testid="workspace-switcher"]', 'aria-label', 'Workspace switcher')
                    ->assertAttribute('[data-testid="user-menu"]', 'aria-label', 'User menu');
            
            // Check for proper heading structure
            $browser->assertPresent('h1')
                    ->assertPresent('[role="main"]');
            
            // Verify navigation has proper ARIA attributes
            $browser->assertAttribute('nav', 'role', 'navigation')
                    ->assertAttribute('nav', 'aria-label');
        });
});

test('form accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                    ->waitFor('form', 5);
            
            // Check for proper form labels
            $browser->assertPresent('label[for="name"]')
                    ->assertPresent('label[for="email"]')
                    ->assertPresent('label[for="password"]')
                    ->assertPresent('label[for="password_confirmation"]');
            
            // Check for proper error message association
            $browser->type('email', 'invalid-email')
                    ->click('button[type="submit"]')
                    ->waitFor('.text-red-500', 3);
            
            // Verify error messages have proper ARIA attributes
            $errorElements = $browser->elements('.text-red-500');
            foreach ($errorElements as $element) {
                $this->assertNotEmpty($element->getAttribute('id'));
            }
        });
});

test('color contrast and visual accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Check for focus indicators
            $browser->click('[data-testid="workspace-switcher"]')
                    ->waitFor('[data-testid="workspace-dropdown"]', 2);
            
            // Verify focus is visible
            $focusedElement = $browser->driver->executeScript('return document.activeElement;');
            $this->assertNotNull($focusedElement);
            
            // Check for proper button states
            $browser->assertPresent('button:not([disabled])')
                    ->assertMissing('button[disabled]:not([aria-disabled="true"])');
        });
});

test('chat interface accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Check message input accessibility
            $browser->assertAttribute('[data-testid="message-input"]', 'aria-label', 'Type your message')
                    ->assertAttribute('[data-testid="send-button"]', 'aria-label', 'Send message');
            
            // Check conversation list accessibility
            $browser->assertAttribute('[data-testid="conversation-sidebar"]', 'role', 'complementary')
                    ->assertAttribute('[data-testid="conversation-sidebar"]', 'aria-label', 'Conversation history');
            
            // Test keyboard navigation in chat
            $browser->keys('[data-testid="message-input"]', ['{tab}'])
                    ->assertFocused('[data-testid="send-button"]');
            
            // Send a message and check message accessibility
            $browser->type('[data-testid="message-input"]', 'Accessibility test message')
                    ->click('[data-testid="send-button"]')
                    ->waitFor('[data-testid="message-Accessibility test message"]', 5);
            
            // Verify message has proper ARIA attributes
            $browser->assertAttribute('[data-testid="message-Accessibility test message"]', 'role', 'article')
                    ->assertAttribute('[data-testid="message-Accessibility test message"]', 'aria-label');
        });
});

test('games interface accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            // Check games grid accessibility
            $browser->assertAttribute('[data-testid="games-grid"]', 'role', 'grid')
                    ->assertAttribute('[data-testid="games-grid"]', 'aria-label', 'Games library');
            
            // Check create game button accessibility
            $browser->assertAttribute('[data-testid="create-game-button"]', 'aria-label', 'Create new game');
            
            // Test game card accessibility
            $gameCards = $browser->elements('[data-testid^="game-card-"]');
            if (count($gameCards) > 0) {
                $firstCard = $gameCards[0];
                $this->assertNotEmpty($firstCard->getAttribute('aria-label'));
                $this->assertEquals('gridcell', $firstCard->getAttribute('role'));
            }
        });
});

test('modal accessibility', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            // Open create game modal
            $browser->click('[data-testid="create-game-button"]')
                    ->waitFor('[data-testid="create-game-modal"]', 3);
            
            // Check modal accessibility attributes
            $browser->assertAttribute('[data-testid="create-game-modal"]', 'role', 'dialog')
                    ->assertAttribute('[data-testid="create-game-modal"]', 'aria-modal', 'true')
                    ->assertAttribute('[data-testid="create-game-modal"]', 'aria-labelledby');
            
            // Test focus trap in modal
            $browser->keys('body', ['{tab}'])
                    ->assertFocused('[data-testid="create-game-modal"] input:first-of-type');
            
            // Test escape key closes modal
            $browser->keys('body', ['{escape}'])
                    ->waitUntilMissing('[data-testid="create-game-modal"]', 3);
        });
});

test('skip navigation links', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Check for skip navigation link
            $browser->assertPresent('a[href="#main-content"]')
                    ->assertSee('Skip to main content');
            
            // Test skip link functionality
            $browser->click('a[href="#main-content"]')
                    ->assertFocused('#main-content');
        });
});

test('responsive accessibility', function () {
        $this->browse(function (Browser $browser) {
            // Test mobile accessibility
            $browser->resize(390, 844)
                    ->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Check mobile menu accessibility
            $browser->assertAttribute('[data-testid="mobile-menu-button"]', 'aria-label', 'Open navigation menu')
                    ->assertAttribute('[data-testid="mobile-menu-button"]', 'aria-expanded', 'false');
            
            // Open mobile menu and check accessibility
            $browser->click('[data-testid="mobile-menu-button"]')
                    ->waitFor('[data-testid="mobile-navigation"]', 2)
                    ->assertAttribute('[data-testid="mobile-menu-button"]', 'aria-expanded', 'true')
                    ->assertAttribute('[data-testid="mobile-navigation"]', 'role', 'navigation');
        });
});