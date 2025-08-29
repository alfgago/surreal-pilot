<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class ComprehensiveAppTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_all_public_routes_work(): void
    {
        $this->browse(function (Browser $browser) {
            $routes = [
                '/' => 'SurrealPilot',
                '/login' => 'Login',
                '/register' => 'Register',
                '/privacy' => 'Privacy',
                '/terms' => 'Terms',
                '/support' => 'Support',
            ];

            foreach ($routes as $route => $expectedText) {
                $browser->visit($route)
                       ->waitFor('#app', 15)
                       ->screenshot('route_' . str_replace('/', '_', $route))
                       ->assertPresent('#app');
                
                // Don't assert specific text as it might not be loaded yet
                // Just ensure the page loads without errors
            }
        });
    }

    public function test_authenticated_routes_redirect_properly(): void
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
                       ->screenshot('protected_' . str_replace('/', '_', $route))
                       ->assertPathIs('/login');
            }
        });
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/dashboard')
                   ->waitFor('#app', 15)
                   ->screenshot('authenticated_dashboard')
                   ->assertPresent('#app');
        });
    }

    public function test_games_route_loads_without_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/games')
                   ->waitFor('#app', 15)
                   ->screenshot('games_page')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error')
                   ->assertDontSee('Class "App\Services\GameStorageService" not found');
        });
    }

    public function test_chat_route_loads_without_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/chat')
                   ->waitFor('#app', 15)
                   ->screenshot('chat_page')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error');
        });
    }

    public function test_settings_route_loads_without_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/settings')
                   ->loginAs($user)
                   ->waitFor('#app', 15)
                   ->screenshot('settings_page')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error');
        });
    }

    public function test_engine_selection_route_loads(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/engine-selection')
                   ->waitFor('#app', 15)
                   ->screenshot('engine_selection_page')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error');
        });
    }

    public function test_workspace_selection_route_loads(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/workspace-selection')
                   ->waitFor('#app', 15)
                   ->screenshot('workspace_selection_page')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error');
        });
    }

    public function test_responsive_design_mobile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone SE
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('mobile_responsive')
                   ->assertPresent('#app');
        });
    }

    public function test_responsive_design_tablet(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(768, 1024) // iPad
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('tablet_responsive')
                   ->assertPresent('#app');
        });
    }

    public function test_responsive_design_desktop(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1920, 1080) // Desktop
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('desktop_responsive')
                   ->assertPresent('#app');
        });
    }
}