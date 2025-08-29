<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class GamesRouteTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_games_route_works(): void
    {
        // Create a user with company and workspace
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        // Create a workspace for the company
        $workspace = \App\Models\Workspace::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Workspace',
            'engine_type' => 'playcanvas',
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            // Set up session with workspace
            session(['selected_workspace_id' => $workspace->id]);
            
            $browser->loginAs($user)
                   ->visit('/games')
                   ->waitFor('#app', 15)
                   ->screenshot('games_route_test')
                   ->assertPresent('#app');
        });
    }
}