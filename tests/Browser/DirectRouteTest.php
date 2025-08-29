<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class DirectRouteTest extends DuskTestCase
{
    public function test_games_route_works(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/games')
                   ->waitFor('#app', 15)
                   ->screenshot('games_route_test')
                   ->assertPresent('#app')
                   ->assertDontSee('Internal Server Error')
                   ->assertDontSee('Class "App\Services\GameStorageService" not found');
        });
    }
}