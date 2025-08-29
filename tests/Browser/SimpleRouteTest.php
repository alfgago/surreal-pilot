<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class SimpleRouteTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_games_route_redirects_without_workspace(): void
    {
        // Create a user with company but no workspace
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/games')
                   ->screenshot('games_redirect_test')
                   ->assertPathIs('/workspace-selection'); // Should redirect here
        });
    }

    public function test_dashboard_works(): void
    {
        // Create a user with company
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/dashboard')
                   ->waitFor('#app', 15)
                   ->screenshot('dashboard_test')
                   ->assertPresent('#app');
        });
    }
}