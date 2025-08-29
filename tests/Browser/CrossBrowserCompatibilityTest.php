<?php

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;



    test('chrome compatibility', function () {
        $this->browse(function (Browser $browser) {
            // Test core functionality in Chrome
            $browser->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Test JavaScript features
            $browser->assertPresent('[data-testid="workspace-switcher"]')
                    ->click('[data-testid="workspace-switcher"]')
                    ->waitFor('[data-testid="workspace-dropdown"]', 2)
                    ->assertVisible('[data-testid="workspace-dropdown"]');
            
            // Test CSS Grid and Flexbox support
            $gridSupport = $browser->driver->executeScript('
                return CSS.supports("display", "grid");
            ');
            $this->assertTrue($gridSupport, 'CSS Grid should be supported');
            
            $flexSupport = $browser->driver->executeScript('
                return CSS.supports("display", "flex");
            ');
            $this->assertTrue($flexSupport, 'CSS Flexbox should be supported');
        });
});

test('modern javascript features', function () {
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
            
            $this->assertTrue($es6Support, 'Modern JavaScript features should be supported');
        });
});

test('css custom properties support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Test CSS custom properties (variables) support
            $cssVarsSupport = $browser->driver->executeScript('
                return CSS.supports("color", "var(--test-color)");
            ');
            
            $this->assertTrue($cssVarsSupport, 'CSS custom properties should be supported');
            
            // Test CSS Grid support
            $cssGridSupport = $browser->driver->executeScript('
                return CSS.supports("display", "grid");
            ');
            
            $this->assertTrue($cssGridSupport, 'CSS Grid should be supported');
        });
});

test('websocket support', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test WebSocket support
            $websocketSupport = $browser->driver->executeScript('
                return typeof WebSocket !== "undefined";
            ');
            
            $this->assertTrue($websocketSupport, 'WebSocket should be supported');
            
            // Test Server-Sent Events support
            $sseSupport = $browser->driver->executeScript('
                return typeof EventSource !== "undefined";
            ');
            
            $this->assertTrue($sseSupport, 'Server-Sent Events should be supported');
        });
});

test('local storage support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Test localStorage support
            $localStorageSupport = $browser->driver->executeScript('
                try {
                    localStorage.setItem("test", "value");
                    const value = localStorage.getItem("test");
                    localStorage.removeItem("test");
                    return value === "value";
                } catch (e) {
                    return false;
                }
            ');
            
            $this->assertTrue($localStorageSupport, 'localStorage should be supported');
            
            // Test sessionStorage support
            $sessionStorageSupport = $browser->driver->executeScript('
                try {
                    sessionStorage.setItem("test", "value");
                    const value = sessionStorage.getItem("test");
                    sessionStorage.removeItem("test");
                    return value === "value";
                } catch (e) {
                    return false;
                }
            ');
            
            $this->assertTrue($sessionStorageSupport, 'sessionStorage should be supported');
        });
});

test('form validation api support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                    ->waitFor('form', 5);
            
            // Test HTML5 form validation API
            $formValidationSupport = $browser->driver->executeScript('
                const input = document.querySelector("input[type=email]");
                return typeof input.checkValidity === "function";
            ');
            
            $this->assertTrue($formValidationSupport, 'HTML5 form validation API should be supported');
            
            // Test custom validity
            $customValiditySupport = $browser->driver->executeScript('
                const input = document.querySelector("input[type=email]");
                return typeof input.setCustomValidity === "function";
            ');
            
            $this->assertTrue($customValiditySupport, 'Custom validity API should be supported');
        });
});

test('fetch api support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Test Fetch API support
            $fetchSupport = $browser->driver->executeScript('
                return typeof fetch === "function";
            ');
            
            $this->assertTrue($fetchSupport, 'Fetch API should be supported');
            
            // Test Promise support
            $promiseSupport = $browser->driver->executeScript('
                return typeof Promise === "function";
            ');
            
            $this->assertTrue($promiseSupport, 'Promise should be supported');
        });
});

test('responsive design compatibility', function () {
        $this->browse(function (Browser $browser) {
            // Test various viewport sizes
            $viewports = [
                ['width' => 320, 'height' => 568],  // iPhone SE
                ['width' => 375, 'height' => 667],  // iPhone 8
                ['width' => 390, 'height' => 844],  // iPhone 12
                ['width' => 768, 'height' => 1024], // iPad
                ['width' => 1024, 'height' => 768], // iPad Landscape
                ['width' => 1280, 'height' => 720], // Desktop
                ['width' => 1920, 'height' => 1080], // Full HD
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
                
                $this->assertFalse($hasHorizontalScroll, 
                    "Horizontal scroll detected at {$viewport['width']}x{$viewport['height']}");
            }
        });
});

test('touch events support', function () {
        $this->browse(function (Browser $browser) {
            $browser->resize(390, 844) // Mobile viewport
                    ->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test touch events support
            $touchSupport = $browser->driver->executeScript('
                return "ontouchstart" in window || navigator.maxTouchPoints > 0;
            ');
            
            // Note: This might be false in desktop browsers, which is expected
            // We're mainly checking that the code doesn't break
            
            // Test touch-friendly interactions
            $browser->click('[data-testid="mobile-conversation-toggle"]')
                    ->waitFor('[data-testid="conversation-sidebar"]', 2)
                    ->assertVisible('[data-testid="conversation-sidebar"]');
        });
});

test('performance api support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Test Performance API support
            $performanceSupport = $browser->driver->executeScript('
                return typeof performance !== "undefined" && 
                       typeof performance.now === "function";
            ');
            
            $this->assertTrue($performanceSupport, 'Performance API should be supported');
            
            // Test Navigation Timing API
            $navigationTimingSupport = $browser->driver->executeScript('
                return typeof performance.getEntriesByType === "function";
            ');
            
            $this->assertTrue($navigationTimingSupport, 'Navigation Timing API should be supported');
        });
});

test('intersection observer support', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Test Intersection Observer API support
            $intersectionObserverSupport = $browser->driver->executeScript('
                return typeof IntersectionObserver === "function";
            ');
            
            $this->assertTrue($intersectionObserverSupport, 'Intersection Observer API should be supported');
        });
});