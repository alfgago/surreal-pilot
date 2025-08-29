<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

test('landing page loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
            $startTime = microtime(true);
            
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            $loadTime = microtime(true) - $startTime;
            
            $this->assertLessThan(2.0, $loadTime, "Landing page took {$loadTime}s to load, should be under 2s");
            
            // Verify critical content is loaded
            $browser->assertSee('SurrealPilot')
                    ->assertPresent('nav')
                    ->assertPresent('main');
        });
});

test('dashboard loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser);
            
            $startTime = microtime(true);
            
            $browser->visit('/dashboard')
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            $loadTime = microtime(true) - $startTime;
            
            $this->assertLessThan(2.0, $loadTime, "Dashboard took {$loadTime}s to load, should be under 2s");
            
            // Verify dashboard components are loaded
            $browser->assertPresent('[data-testid="workspace-switcher"]')
                    ->assertPresent('[data-testid="user-menu"]');
        });
});

test('chat page loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser);
            
            $startTime = microtime(true);
            
            $browser->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            $loadTime = microtime(true) - $startTime;
            
            $this->assertLessThan(2.0, $loadTime, "Chat page took {$loadTime}s to load, should be under 2s");
            
            // Verify chat components are loaded
            $browser->assertPresent('[data-testid="message-input"]')
                    ->assertPresent('[data-testid="conversation-sidebar"]');
        });
});

test('games page loads under 2 seconds', function () {
    $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser);
            
            $startTime = microtime(true);
            
            $browser->visit('/games')
                    ->waitFor('[data-testid="games-grid"]', 5);
            
            $loadTime = microtime(true) - $startTime;
            
            $this->assertLessThan(2.0, $loadTime, "Games page took {$loadTime}s to load, should be under 2s");
            
            // Verify games components are loaded
            $browser->assertPresent('[data-testid="create-game-button"]');
        });
});

test('page resources optimization', function () {
    $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitFor('h1', 5);
            
            // Check for performance optimization indicators
            $performanceEntries = $browser->driver->executeScript('
                return performance.getEntriesByType("navigation")[0];
            ');
            
            // Verify DNS lookup time is reasonable
            $dnsTime = $performanceEntries['domainLookupEnd'] - $performanceEntries['domainLookupStart'];
            $this->assertLessThan(100, $dnsTime, "DNS lookup took {$dnsTime}ms, should be under 100ms");
            
            // Verify DOM content loaded time
            $domContentLoaded = $performanceEntries['domContentLoadedEventEnd'] - $performanceEntries['navigationStart'];
            $this->assertLessThan(1500, $domContentLoaded, "DOM content loaded in {$domContentLoaded}ms, should be under 1.5s");
        });
});

test('asset loading performance', function () {
    $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                    ->loginAs($this->testUser)
                    ->waitFor('[data-testid="dashboard-content"]', 5);
            
            // Check resource loading times
            $resourceEntries = $browser->driver->executeScript('
                return performance.getEntriesByType("resource").map(entry => ({
                    name: entry.name,
                    duration: entry.duration,
                    size: entry.transferSize
                }));
            ');
            
            foreach ($resourceEntries as $resource) {
                // CSS and JS files should load quickly
                if (str_contains($resource['name'], '.css') || str_contains($resource['name'], '.js')) {
                    $this->assertLessThan(1000, $resource['duration'], 
                        "Resource {$resource['name']} took {$resource['duration']}ms to load");
                }
            }
        });
});