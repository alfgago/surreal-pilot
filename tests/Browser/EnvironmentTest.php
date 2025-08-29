<?php

use Laravel\Dusk\Browser;

test('check environment', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->pause(1000);
        
        // Check what environment we're running in
        $env = app()->environment();
        $this->assertEquals('dusk.local', $env);
        
        // Check if NativePHP class exists
        $nativeExists = class_exists(\Native\Laravel\Facades\Window::class);
        dump("Environment: $env");
        dump("NativePHP exists: " . ($nativeExists ? 'yes' : 'no'));
        dump("Running in console: " . (app()->runningInConsole() ? 'yes' : 'no'));
        dump("Testing environment: " . (app()->environment('testing') ? 'yes' : 'no'));
    });
});