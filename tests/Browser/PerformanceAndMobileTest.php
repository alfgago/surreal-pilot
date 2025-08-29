<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

test('landing page loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
        $startTime = microtime(true);
        
        $browser->visit('/')
                ->waitFor('h1', 5);
        
        $loadTime = microtime(true) - $startTime;
        
        expect($loadTime)->toBeLessThan(2.0);
        
        // Verify critical content is loaded
        $browser->assertSee('SurrealPilot')
                ->assertPresent('nav')
                ->assertPresent('main');
    });
});

test('dashboard loads under 2 seconds for authenticated user', function () {
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->testUser);
        
        $startTime = microtime(true);
        
        $browser->visit('/dashboard')
                ->waitFor('[data-testid="dashboard-content"]', 5);
        
        $loadTime = microtime(true) - $startTime;
        
        expect($loadTime)->toBeLessThan(2.0);
        
        // Verify dashboard components are loaded
        $browser->assertPresent('[data-testid="workspace-switcher"]')
                ->assertPresent('[data-testid="user-menu"]');
    });
});

test('chat page loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->testUser);
        
        $startTime = microtime(true);
        
        $browser->visit('/chat')
                ->waitFor('[data-testid="chat-interface"]', 5);
        
        $loadTime = microtime(true) - $startTime;
        
        expect($loadTime)->toBeLessThan(2.0);
        
        // Verify chat components are loaded
        $browser->assertPresent('[data-testid="message-input"]')
                ->assertPresent('[data-testid="conversation-sidebar"]');
    });
});

test('mobile responsive design on iPhone 12 Pro', function () {
    $this->browse(function (Browser $browser) {
        // iPhone 12 Pro dimensions
        $browser->resize(390, 844)
                ->loginAs($this->testUser)
                ->visit('/dashboard')
                ->waitFor('[data-testid="dashboard-content"]', 5);
        
        // Verify mobile navigation is present
        $browser->assertPresent('[data-testid="mobile-menu-button"]')
                ->assertMissing('[data-testid="desktop-navigation"]');
        
        // Test mobile menu functionality
        $browser->click('[data-testid="mobile-menu-button"]')
                ->waitFor('[data-testid="mobile-navigation"]', 2)
                ->assertVisible('[data-testid="mobile-navigation"]');
    });
});

test('mobile chat interface responsive design', function () {
    $this->browse(function (Browser $browser) {
        $browser->resize(390, 844)
                ->loginAs($this->testUser)
                ->visit('/chat')
                ->waitFor('[data-testid="chat-interface"]', 5);
        
        // Verify mobile chat layout
        $browser->assertPresent('[data-testid="message-input"]')
                ->assertPresent('[data-testid="send-button"]');
        
        // Test message input on mobile
        $browser->type('[data-testid="message-input"]', 'Test mobile message')
                ->assertValue('[data-testid="message-input"]', 'Test mobile message');
        
        // Verify send button is accessible
        $browser->assertPresent('[data-testid="send-button"]')
                ->assertVisible('[data-testid="send-button"]');
    });
});

test('tablet responsive design on iPad', function () {
    $this->browse(function (Browser $browser) {
        // iPad dimensions
        $browser->resize(768, 1024)
                ->loginAs($this->testUser)
                ->visit('/dashboard')
                ->waitFor('[data-testid="dashboard-content"]', 5);
        
        // iPad should show tablet layout
        $browser->assertPresent('[data-testid="workspace-switcher"]')
                ->assertPresent('[data-testid="user-menu"]');
        
        // Test workspace switching on tablet
        $browser->click('[data-testid="workspace-switcher"]')
                ->waitFor('[data-testid="workspace-dropdown"]', 2)
                ->assertVisible('[data-testid="workspace-dropdown"]');
    });
});

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

test('screen reader compatibility with ARIA labels', function () {
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
        $browser->assertAttribute('nav', 'role', 'navigation');
    });
});

test('touch target sizes meet accessibility standards', function () {
    $this->browse(function (Browser $browser) {
        $browser->resize(390, 844)
                ->loginAs($this->testUser)
                ->visit('/dashboard')
                ->waitFor('[data-testid="dashboard-content"]', 5);
        
        // Test that interactive elements meet minimum touch target size (44px)
        $touchTargets = [
            '[data-testid="mobile-menu-button"]',
            '[data-testid="mobile-workspace-switcher"]',
            '[data-testid="mobile-user-menu"]'
        ];
        
        foreach ($touchTargets as $selector) {
            if ($browser->element($selector)) {
                $size = $browser->driver->executeScript("
                    const element = document.querySelector('$selector');
                    if (!element) return null;
                    const rect = element.getBoundingClientRect();
                    return { width: rect.width, height: rect.height };
                ");
                
                if ($size) {
                    expect($size['width'])->toBeGreaterThanOrEqual(44);
                    expect($size['height'])->toBeGreaterThanOrEqual(44);
                }
            }
        }
    });
});

test('cross browser JavaScript features support', function () {
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->testUser)
                ->visit('/chat')
                ->waitFor('[data-testid="chat-interface"]', 5);
        
        // Test ES6+ features support
        $es6Support = $browser->driver->executeScript('
            try {
                // Test arrow functions
                const test = () => true;
                
                // Test async/await
                const asyncTest = async () => await Promise.resolve(true);
                
                // Test destructuring
                const { length } = [1, 2, 3];
                
                // Test template literals
                const template = `test ${length}`;
                
                return true;
            } catch (e) {
                return false;
            }
        ');
        
        expect($es6Support)->toBe(true);
    });
});

test('WebSocket and real-time features support', function () {
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->testUser)
                ->visit('/chat')
                ->waitFor('[data-testid="chat-interface"]', 5);
        
        // Test WebSocket support
        $websocketSupport = $browser->driver->executeScript('
            return typeof WebSocket !== "undefined";
        ');
        
        expect($websocketSupport)->toBe(true);
        
        // Test Server-Sent Events support
        $sseSupport = $browser->driver->executeScript('
            return typeof EventSource !== "undefined";
        ');
        
        expect($sseSupport)->toBe(true);
    });
});

test('CSS Grid and Flexbox support', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->waitFor('h1', 5);
        
        // Test CSS Grid support
        $gridSupport = $browser->driver->executeScript('
            return CSS.supports("display", "grid");
        ');
        expect($gridSupport)->toBe(true);
        
        // Test CSS Flexbox support
        $flexSupport = $browser->driver->executeScript('
            return CSS.supports("display", "flex");
        ');
        expect($flexSupport)->toBe(true);
    });
});

test('responsive design works across different viewports', function () {
    $this->browse(function (Browser $browser) {
        // Test various viewport sizes
        $viewports = [
            ['width' => 320, 'height' => 568],  // iPhone SE
            ['width' => 390, 'height' => 844],  // iPhone 12
            ['width' => 768, 'height' => 1024], // iPad
            ['width' => 1280, 'height' => 720], // Desktop
        ];
        
        foreach ($viewports as $viewport) {
            $browser->resize($viewport['width'], $viewport['height'])
                    ->visit('/')
                    ->waitFor('h1', 5);
            
            // Verify layout doesn't break
            $browser->assertPresent('nav')
                    ->assertPresent('main')
                    ->assertVisible('h1');
            
            // Check for horizontal scrollbar (should not exist)
            $hasHorizontalScroll = $browser->driver->executeScript('
                return document.body.scrollWidth > window.innerWidth;
            ');
            
            expect($hasHorizontalScroll)->toBe(false);
        }
    });
});

test('page performance metrics are within acceptable ranges', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->waitFor('h1', 5);
        
        // Check for performance optimization indicators
        $performanceEntries = $browser->driver->executeScript('
            return performance.getEntriesByType("navigation")[0];
        ');
        
        // Verify DNS lookup time is reasonable
        $dnsTime = $performanceEntries['domainLookupEnd'] - $performanceEntries['domainLookupStart'];
        expect($dnsTime)->toBeLessThan(100);
        
        // Verify DOM content loaded time
        $domContentLoaded = $performanceEntries['domContentLoadedEventEnd'] - $performanceEntries['navigationStart'];
        expect($domContentLoaded)->toBeLessThan(1500);
    });
});