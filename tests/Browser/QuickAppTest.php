<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class QuickAppTest extends DuskTestCase
{
    public function test_application_homepage_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('homepage_test')
                   ->assertPresent('#app');
        });
    }
}