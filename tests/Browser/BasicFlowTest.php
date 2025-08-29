<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BasicFlowTest extends DuskTestCase
{
    public function test_application_pages_load_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            // Test landing page
            $browser->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_01_landing')
                   ->assertPresent('#app');

            // Test login page
            $browser->visit('/login')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_02_login')
                   ->assertPresent('#app');

            // Test register page
            $browser->visit('/register')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_03_register')
                   ->assertPresent('#app');

            // Test public pages
            $browser->visit('/privacy')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_04_privacy')
                   ->assertPresent('#app');

            $browser->visit('/terms')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_05_terms')
                   ->assertPresent('#app');

            $browser->visit('/support')
                   ->waitFor('#app', 15)
                   ->screenshot('basic_06_support')
                   ->assertPresent('#app');
        });
    }

    public function test_responsive_breakpoints(): void
    {
        $this->browse(function (Browser $browser) {
            $breakpoints = [
                'mobile' => [375, 667],
                'tablet' => [768, 1024],
                'desktop' => [1920, 1080],
            ];

            foreach ($breakpoints as $device => $dimensions) {
                $browser->resize($dimensions[0], $dimensions[1])
                       ->visit('/')
                       ->waitFor('#app', 15)
                       ->screenshot("responsive_{$device}_landing")
                       ->assertPresent('#app');
            }
        });
    }

    public function test_navigation_without_authentication(): void
    {
        $this->browse(function (Browser $browser) {
            // Test that protected routes redirect to login
            $protectedRoutes = ['/dashboard', '/chat', '/games', '/settings'];
            
            foreach ($protectedRoutes as $route) {
                $browser->visit($route)
                       ->screenshot('redirect_' . str_replace('/', '_', $route))
                       ->assertPathIs('/login');
            }
        });
    }
}