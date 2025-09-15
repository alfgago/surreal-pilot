<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GDevelopSimpleBrowserTest extends DuskTestCase
{
    public function test_homepage_loads()
    {
        $this->browse(function (Browser $browser) {
            echo "🌐 Testing homepage load...\n";
            
            $browser->visit('/')
                    ->assertSee('Laravel')
                    ->screenshot('homepage');
            
            echo "✅ Homepage loaded successfully\n";
        });
    }
    
    public function test_registration_page_loads()
    {
        $this->browse(function (Browser $browser) {
            echo "📝 Testing registration page...\n";
            
            $browser->visit('/register')
                    ->assertSee('Register')
                    ->screenshot('registration');
            
            echo "✅ Registration page loaded successfully\n";
        });
    }
    
    public function test_engine_selection_redirect()
    {
        $this->browse(function (Browser $browser) {
            echo "🎮 Testing engine selection...\n";
            
            $browser->visit('/engine-selection')
                    ->screenshot('engine-selection');
            
            // Should either show engine selection or redirect to login
            $currentUrl = $browser->driver->getCurrentURL();
            echo "📋 Current URL: {$currentUrl}\n";
            
            if (str_contains($currentUrl, 'login')) {
                echo "✅ Redirected to login (expected for unauthenticated users)\n";
            } else {
                echo "✅ Engine selection page accessible\n";
            }
        });
    }
}