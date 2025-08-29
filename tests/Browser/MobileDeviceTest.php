<?php

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;



    /**
     * Test various mobile device viewports
     */
    test('iphone se compatibility', function () {
        $this->browse(function (Browser $browser) {
            // iPhone SE (320x568)
            $browser->resize(320, 568)
                    ->loginAs($this->testUser)
                    ->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Verify mobile layout works on smallest screen
            $browser->assertPresent('[data-testid="mobile-menu-button"]')
                    ->assertMissing('[data-testid="desktop-navigation"]');
            
            // Test navigation functionality
            $browser->click('[data-testid="mobile-menu-button"]')
                    ->waitFor('[data-testid="mobile-navigation"]', 2)
                    ->assertVisible('[data-testid="mobile-navigation"]');
            
            // Test chat interface on small screen
            $browser->click('[data-testid="mobile-nav-chat"]')
                    ->waitForLocation('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5)
                    ->assertPresent('[data-testid="message-input"]')
                    ->assertPresent('[data-testid="send-button"]');
        });
});

test('iphone 12 pro compatibility', function () {
        $this->browse(function (Browser $browser) {
            // iPhone 12 Pro (390x844)
            $browser->resize(390, 844)
                    ->loginAs($this->testUser)
                    ->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            // Test games grid on iPhone 12 Pro
            $browser->assertPresent('[data-testid="games-grid"]')
                    ->assertPresent('[data-testid="mobile-create-game-button"]');
            
            // Test game creation modal
            $browser->click('[data-testid="mobile-create-game-button"]')
                    ->waitFor('[data-testid="create-game-modal"]', 3)
                    ->assertVisible('[data-testid="create-game-modal"]');
            
            // Test form inputs are properly sized
            $browser->assertPresent('input[name="name"]')
                    ->type('name', 'Mobile Test Game')
                    ->assertValue('name', 'Mobile Test Game');
        });
});

test('iphone 14 pro max compatibility', function () {
        $this->browse(function (Browser $browser) {
            // iPhone 14 Pro Max (430x932)
            $browser->resize(430, 932)
                    ->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test chat interface on large mobile screen
            $browser->assertPresent('[data-testid="message-input"]')
                    ->assertPresent('[data-testid="conversation-sidebar"]');
            
            // Test message sending
            $browser->type('[data-testid="message-input"]', 'Test message on iPhone 14 Pro Max')
                    ->click('[data-testid="send-button"]')
                    ->waitFor('[data-testid="message-Test message on iPhone 14 Pro Max"]', 5);
            
            // Verify message appears correctly
            $browser->assertSee('Test message on iPhone 14 Pro Max');
        });
});

test('ipad compatibility', function () {
        $this->browse(function (Browser $browser) {
            // iPad (768x1024)
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
            
            // Test games interface on tablet
            $browser->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5)
                    ->assertPresent('[data-testid="games-grid"]');
        });
});

test('ipad landscape compatibility', function () {
        $this->browse(function (Browser $browser) {
            // iPad Landscape (1024x768)
            $browser->resize(1024, 768)
                    ->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test chat layout in landscape
            $browser->assertPresent('[data-testid="conversation-sidebar"]')
                    ->assertPresent('[data-testid="message-input"]')
                    ->assertVisible('[data-testid="conversation-sidebar"]');
            
            // Test that sidebar is visible in landscape mode
            $sidebarWidth = $browser->driver->executeScript('
                const sidebar = document.querySelector("[data-testid=\"conversation-sidebar\"]");
                return sidebar ? sidebar.offsetWidth : 0;
            ');
            
            $this->assertGreaterThan(200, $sidebarWidth, 'Sidebar should be visible in landscape mode');
        });
});

test('android phone compatibility', function () {
        $this->browse(function (Browser $browser) {
            // Typical Android phone (360x640)
            $browser->resize(360, 640)
                    ->loginAs($this->testUser)
                    ->visit('/settings')
                    ->waitFor('[data-testid="settings-content"]', 5);
            
            // Test settings page on Android
            $browser->assertPresent('[data-testid="settings-content"]')
                    ->assertPresent('[data-testid="mobile-menu-button"]');
            
            // Test form interactions
            if ($browser->element('input[name="name"]')) {
                $browser->clear('name')
                        ->type('name', 'Android Test User')
                        ->assertValue('name', 'Android Test User');
            }
        });
});

test('touch target sizes', function () {
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
                        $this->assertGreaterThanOrEqual(44, $size['width'], 
                            "Touch target $selector width should be at least 44px");
                        $this->assertGreaterThanOrEqual(44, $size['height'], 
                            "Touch target $selector height should be at least 44px");
                    }
                }
            }
        });
});

test('mobile form usability', function () {
        $this->browse(function (Browser $browser) {
            $browser->resize(390, 844)
                    ->visit('/register')
                    ->waitFor('form', 5);
            
            // Test form field spacing and usability
            $formFields = ['name', 'email', 'password', 'password_confirmation'];
            
            foreach ($formFields as $field) {
                $browser->assertPresent("input[name=\"$field\"]");
                
                // Test field height for mobile usability
                $fieldHeight = $browser->driver->executeScript("
                    const field = document.querySelector('input[name=\"$field\"]');
                    return field ? field.offsetHeight : 0;
                ");
                
                $this->assertGreaterThanOrEqual(44, $fieldHeight, 
                    "Form field $field should be at least 44px tall for mobile");
            }
            
            // Test form submission button
            $submitButton = $browser->element('button[type="submit"]');
            if ($submitButton) {
                $buttonSize = $browser->driver->executeScript('
                    const button = arguments[0];
                    const rect = button.getBoundingClientRect();
                    return { width: rect.width, height: rect.height };
                ', $submitButton);
                
                $this->assertGreaterThanOrEqual(44, $buttonSize['height'], 
                    'Submit button should be at least 44px tall');
            }
        });
});

test('mobile scrolling behavior', function () {
        $this->browse(function (Browser $browser) {
            $browser->resize(390, 844)
                    ->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Test that page doesn't have horizontal scroll
            $hasHorizontalScroll = $browser->driver->executeScript('
                return document.body.scrollWidth > window.innerWidth;
            ');
            
            $this->assertFalse($hasHorizontalScroll, 
                'Page should not have horizontal scroll on mobile');
            
            // Test vertical scrolling works properly
            $browser->driver->executeScript('window.scrollTo(0, 100);');
            
            $scrollPosition = $browser->driver->executeScript('return window.pageYOffset;');
            $this->assertGreaterThan(0, $scrollPosition, 'Vertical scrolling should work');
        });
});

test('mobile orientation changes', function () {
        $this->browse(function (Browser $browser) {
            // Portrait mode
            $browser->resize(390, 844)
                    ->loginAs($this->testUser)
                    ->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            $browser->assertPresent('[data-testid="games-grid"]');
            
            // Landscape mode (swap dimensions)
            $browser->resize(844, 390)
                    ->pause(500); // Allow layout to adjust
            
            // Verify layout still works in landscape
            $browser->assertPresent('[data-testid="games-grid"]')
                    ->assertVisible('[data-testid="games-grid"]');
            
            // Check that content doesn't overflow
            $hasHorizontalScroll = $browser->driver->executeScript('
                return document.body.scrollWidth > window.innerWidth;
            ');
            
            $this->assertFalse($hasHorizontalScroll, 
                'Page should not overflow in landscape mode');
        });
});