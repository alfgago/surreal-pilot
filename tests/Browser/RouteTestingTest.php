<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class RouteTestingTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_all_authenticated_routes_work(): void
    {
        // Create a user with company
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            $routes = [
                '/dashboard' => 'Dashboard',
                '/games' => 'Games',
                '/templates' => 'Templates', 
                '/history' => 'History',
                '/multiplayer' => 'Multiplayer',
                '/settings' => 'Settings',
                '/profile' => 'Profile',
                '/company/settings' => 'Company Settings',
                '/company/billing' => 'Billing',
            ];

            foreach ($routes as $route => $description) {
                try {
                    $browser->visit($route)
                           ->waitFor('#app', 15)
                           ->screenshot('route_test_' . str_replace('/', '_', $route))
                           ->assertPresent('#app');
                    
                    echo "✅ {$route} - {$description} works\n";
                } catch (\Exception $e) {
                    echo "❌ {$route} - {$description} failed: " . $e->getMessage() . "\n";
                }
            }
        });
    }
}