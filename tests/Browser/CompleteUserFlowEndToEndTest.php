<?php

use App\Models\User;
use App\Models\Game;
use Laravel\Dusk\Browser;

test('complete user flow - test core application functionality', function () {
    $this->browse(function (Browser $browser) {
        // Step 1: Test homepage loads
        $browser->visit('/')
                ->waitFor('#app', 15)
                ->assertSee('SurrealPilot');
        
        // Step 2: Test login functionality
        $browser->visit('/login')
                ->waitFor('#app', 15)
                ->assertPresent('#app');
        
        // Step 3: Login with test user and navigate through key pages
        if ($browser->element('input[name="email"]')) {
            $browser->type('email', 'alfredo@5e.cr')
                    ->type('password', 'Test123!')
                    ->press('Sign in')
                    ->waitForLocation('/engine-selection', 20);
            
            // Test authenticated pages
            $browser->assertPathIs('/engine-selection')
                    ->waitFor('#app', 10)
                    ->assertPresent('#app');
            
            // Test games page
            $browser->visit('/games')
                    ->waitFor('#app', 15)
                    ->assertPresent('#app');
        }
        
        // Final verification
        $browser->visit('/')
                ->waitFor('#app', 15)
                ->assertPresent('#app')
                ->assertSee('SurrealPilot');
    });
    
    // Verify database state
    expect(User::count())->toBeGreaterThan(0);
});