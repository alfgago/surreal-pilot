<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleAppTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_application_loads_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('app_loaded')
                   ->assertPresent('#app');
        });
    }

    public function test_login_page_accessible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                   ->waitFor('#app', 15)
                   ->screenshot('login_accessible')
                   ->assertPresent('#app');
        });
    }

    public function test_register_page_accessible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                   ->waitFor('#app', 15)
                   ->screenshot('register_accessible')
                   ->assertPresent('#app');
        });
    }
}