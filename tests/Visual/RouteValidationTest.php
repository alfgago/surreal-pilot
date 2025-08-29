<?php

namespace Tests\Visual;

use Tests\Visual\VisualTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Company;

class RouteValidationTest extends VisualTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }
    
    public function test_all_public_routes_render_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $publicRoutes = [
                '/' => 'Landing page',
                '/login' => 'Login page',
                '/register' => 'Registration page',
                '/privacy' => 'Privacy policy page',
                '/terms' => 'Terms of service page',
                '/support' => 'Support page',
            ];
            
            foreach ($publicRoutes as $route => $description) {
                $browser->visit($route)
                       ->waitFor('#app', 10)
                       ->takeScreenshot('route_public_' . str_replace('/', '_', $route), $description)
                       ->assertDontSee('404')
                       ->assertDontSee('500')
                       ->assertDontSee('Error');
            }
        });
    }
    
    public function test_authenticated_routes_require_login(): void
    {
        $this->browse(function (Browser $browser) {
            $protectedRoutes = [
                '/dashboard',
                '/chat',
                '/games',
                '/settings',
                '/profile',
                '/company/settings',
                '/company/billing',
            ];
            
            foreach ($protectedRoutes as $route) {
                $browser->visit($route)
                       ->takeScreenshot('route_protected_redirect_' . str_replace('/', '_', $route), "Redirect from protected route: {$route}")
                       ->assertPathIs('/login');
            }
        });
    }
    
    public function test_authenticated_user_navigation_flow(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            
            $authenticatedRoutes = [
                '/dashboard' => 'Dashboard page',
                '/engine-selection' => 'Engine selection page',
                '/workspace-selection' => 'Workspace selection page',
                '/games' => 'Games management page',
                '/games/create' => 'Create game page',
                '/templates' => 'Templates page',
                '/history' => 'History page',
                '/multiplayer' => 'Multiplayer page',
                '/settings' => 'Settings page',
                '/profile' => 'Profile page',
                '/company/settings' => 'Company settings page',
                '/company/billing' => 'Company billing page',
            ];
            
            foreach ($authenticatedRoutes as $route => $description) {
                $browser->visit($route)
                       ->waitFor('#app', 10)
                       ->takeScreenshot('route_auth_' . str_replace('/', '_', $route), $description)
                       ->assertDontSee('404')
                       ->assertDontSee('500')
                       ->assertDontSee('Unauthorized');
            }
        });
    }
    
    public function test_navigation_menu_functionality(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/dashboard')
                   ->waitFor('#app', 10)
                   ->takeScreenshot('nav_01_dashboard', 'Dashboard with navigation menu');
            
            // Test main navigation links
            $navLinks = [
                'chat' => '/chat',
                'games' => '/games',
                'templates' => '/templates',
                'history' => '/history',
                'multiplayer' => '/multiplayer',
            ];
            
            foreach ($navLinks as $linkText => $expectedPath) {
                if ($browser->element("[data-testid='nav-{$linkText}']")) {
                    $browser->click("[data-testid='nav-{$linkText}']")
                           ->waitFor('#app', 10)
                           ->takeScreenshot("nav_02_{$linkText}", "Navigation to {$linkText} page")
                           ->assertPathIs($expectedPath);
                }
            }
            
            // Test user menu
            if ($browser->element('[data-testid="user-menu"]')) {
                $browser->click('[data-testid="user-menu"]')
                       ->takeScreenshot('nav_03_user_menu', 'User menu dropdown opened')
                       ->click('[data-testid="user-menu-settings"]')
                       ->waitFor('#app', 10)
                       ->takeScreenshot('nav_04_settings_from_menu', 'Settings page from user menu')
                       ->assertPathIs('/settings');
            }
        });
    }
    
    public function test_error_page_handling(): void
    {
        $this->browse(function (Browser $browser) {
            // Test 404 page
            $browser->visit('/nonexistent-route')
                   ->takeScreenshot('error_01_404_page', '404 error page')
                   ->assertSee('404');
            
            // Test invalid game ID
            $browser->visit('/games/999999')
                   ->takeScreenshot('error_02_invalid_game', 'Invalid game ID error')
                   ->assertDontSee('500'); // Should handle gracefully
        });
    }
    
    public function test_mobile_navigation(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 667) // Mobile viewport
                   ->loginAs($user)
                   ->visit('/dashboard')
                   ->waitFor('#app', 10)
                   ->takeScreenshot('mobile_nav_01_dashboard', 'Mobile dashboard view');
            
            // Test mobile-specific routes
            $mobileRoutes = [
                '/mobile/chat' => 'Mobile chat interface',
                '/mobile/tutorials' => 'Mobile tutorials page',
            ];
            
            foreach ($mobileRoutes as $route => $description) {
                $browser->visit($route)
                       ->waitFor('#app', 10)
                       ->takeScreenshot('mobile_nav_02_' . str_replace('/', '_', $route), $description)
                       ->assertDontSee('404');
            }
        });
    }
}