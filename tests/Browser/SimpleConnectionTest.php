<?php

use Laravel\Dusk\Browser;

test('can connect to application', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->pause(2000) // Wait 2 seconds
            ->dump(); // This will show us what's actually on the page
    });
});