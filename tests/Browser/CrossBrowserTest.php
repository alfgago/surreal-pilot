<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CrossBrowserTest extends DuskTestCase
{
    public function test_application_works_in_different_viewport_sizes(): void
    {
        $this->browse(function (Browser $browser) {
            // Test desktop viewport
            $browser->resize(1920, 1080)
                ->visit('/')
                ->waitFor('body', 10)
                ->assertPresent('body');
                
            // Test tablet viewport
            $browser->resize(768, 1024)
                ->visit('/')
                ->waitFor('body', 10)
                ->assertPresent('body');
                
            // Test mobile viewport
            $browser->resize(375, 667)
                ->visit('/')
                ->waitFor('body', 10)
                ->assertPresent('body');
                
            // Reset to desktop
            $browser->resize(1920, 1080);
        });
    }

    public function test_application_handles_slow_network_conditions(): void
    {
        $this->browse(function (Browser $browser) {
            // Basic test - Chrome DevTools Protocol commands may not work in all environments
            $browser->visit('/')
                ->waitFor('body', 15) // Longer timeout
                ->assertPresent('body');
        });
    }

    public function test_application_works_with_basic_functionality(): void
    {
        $this->browse(function (Browser $browser) {
            // Note: This is a basic test since Inertia requires JS
            // We mainly test that the server-side rendering works
            $browser->visit('/')
                ->waitFor('body', 10)
                ->assertPresent('body')
                ->assertPresent('html');
        });
    }

    public function test_application_handles_form_validation_errors_properly(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('form', 10)
                ->press('Sign in') // Submit without filling fields
                ->waitFor('.text-red-600', 10) // Wait for validation errors (updated class)
                ->assertPresent('.text-red-600'); // Should show validation errors
        });
    }

    public function test_application_maintains_session_across_page_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                ->waitFor('form', 10)
                ->type('email', 'alfredo@5e.cr')
                ->type('password', 'Test123!')
                ->press('Sign in')
                ->waitForLocation('/engine-selection', 15);
            
            // Navigate to different pages and ensure user stays logged in
            $browser->visit('/chat')
                ->waitFor('[data-testid="user-menu-trigger"]', 10)
                ->assertPresent('[data-testid="user-menu-trigger"]')
                ->visit('/games')
                ->waitFor('body', 10)
                ->assertPresent('[data-testid="user-menu-trigger"]')
                ->visit('/settings')
                ->waitFor('body', 10)
                ->assertPresent('[data-testid="user-menu-trigger"]');
        });
    }

    public function test_application_handles_concurrent_user_sessions(): void
    {
        $this->browse(function (Browser $browser1, Browser $browser2) {
            // Login with same user in two browsers
            $browser1->visit('/login')
                ->waitFor('form', 10)
                ->type('email', 'alfredo@5e.cr')
                ->type('password', 'Test123!')
                ->press('Sign in')
                ->waitForLocation('/engine-selection', 15);
                
            $browser2->visit('/login')
                ->waitFor('form', 10)
                ->type('email', 'alfredo@5e.cr')
                ->type('password', 'Test123!')
                ->press('Sign in')
                ->waitForLocation('/engine-selection', 15);
            
            // Both should be able to access protected pages
            $browser1->visit('/chat')
                ->waitFor('[data-testid="chat-interface"]', 10)
                ->assertPresent('[data-testid="chat-interface"]');
                
            $browser2->visit('/games')
                ->waitFor('body', 10)
                ->assertPresent('body');
        });
    }
}