<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class BasicAppTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_landing_page_loads_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                   ->waitFor('#app', 10)
                   ->screenshot('landing_page')
                   ->assertSee('SurrealPilot');
        });
    }

    public function test_login_page_loads_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                   ->waitFor('#app', 10)
                   ->screenshot('login_page')
                   ->assertSee('Login');
        });
    }

    public function test_register_page_loads_correctly(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                   ->waitFor('#app', 10)
                   ->screenshot('register_page')
                   ->assertSee('Register');
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
                   ->waitFor('#app', 10)
                   ->screenshot('dashboard_authenticated')
                   ->assertSee('Dashboard');
        });
    }

    public function test_protected_routes_redirect_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $protectedRoutes = ['/dashboard', '/chat', '/games', '/settings'];
            
            foreach ($protectedRoutes as $route) {
                $browser->visit($route)
                       ->screenshot('redirect_' . str_replace('/', '_', $route))
                       ->assertPathIs('/login');
            }
        });
    }

    public function test_responsive_design_mobile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone SE size
                   ->visit('/')
                   ->waitFor('#app', 10)
                   ->screenshot('mobile_landing')
                   ->assertSee('SurrealPilot');
        });
    }

    public function test_responsive_design_tablet(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(768, 1024) // iPad size
                   ->visit('/')
                   ->waitFor('#app', 10)
                   ->screenshot('tablet_landing')
                   ->assertSee('SurrealPilot');
        });
    }

    public function test_responsive_design_desktop(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1920, 1080) // Desktop size
                   ->visit('/')
                   ->waitFor('#app', 10)
                   ->screenshot('desktop_landing')
                   ->assertSee('SurrealPilot');
        });
    }
}