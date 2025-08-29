<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

test('landing page loads successfully', function () {
    $this->browse(function (Browser $browser) {
        $startTime = microtime(true);
        
        $browser->visit('/')
                ->waitFor('body', 10);
        
        $loadTime = microtime(true) - $startTime;
        
        // Verify page loads (even if it's the desktop view)
        $browser->assertPresent('body');
        
        // Log performance for monitoring
        echo "\nLanding page load time: " . round($loadTime * 1000, 2) . "ms\n";
        
        // Performance should be reasonable (under 5 seconds for initial load)
        expect($loadTime)->toBeLessThan(5.0);
    });
});

test('mobile viewport renders correctly', function () {
    $this->browse(function (Browser $browser) {
        // Test iPhone 12 Pro dimensions
        $browser->resize(390, 844)
                ->visit('/')
                ->waitFor('body', 10);
        
        // Verify page renders without horizontal scroll
        $hasHorizontalScroll = $browser->driver->executeScript('
            return document.body.scrollWidth > window.innerWidth;
        ');
        
        expect($hasHorizontalScroll)->toBe(false);
        
        // Verify content is visible
        $browser->assertPresent('body')
                ->assertVisible('body');
    });
});

test('tablet viewport renders correctly', function () {
    $this->browse(function (Browser $browser) {
        // Test iPad dimensions
        $browser->resize(768, 1024)
                ->visit('/')
                ->waitFor('body', 10);
        
        // Verify page renders without horizontal scroll
        $hasHorizontalScroll = $browser->driver->executeScript('
            return document.body.scrollWidth > window.innerWidth;
        ');
        
        expect($hasHorizontalScroll)->toBe(false);
        
        // Verify content is visible
        $browser->assertPresent('body')
                ->assertVisible('body');
    });
});

test('basic JavaScript features work', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->waitFor('body', 10);
        
        // Test basic JavaScript functionality
        $jsWorking = $browser->driver->executeScript('
            try {
                // Test basic JS features
                const test = () => true;
                const result = test();
                return result === true;
            } catch (e) {
                return false;
            }
        ');
        
        expect($jsWorking)->toBe(true);
    });
});

test('CSS features are supported', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->waitFor('body', 10);
        
        // Test CSS Grid support
        $gridSupport = $browser->driver->executeScript('
            return CSS && CSS.supports && CSS.supports("display", "grid");
        ');
        
        // Test CSS Flexbox support  
        $flexSupport = $browser->driver->executeScript('
            return CSS && CSS.supports && CSS.supports("display", "flex");
        ');
        
        expect($gridSupport)->toBe(true);
        expect($flexSupport)->toBe(true);
    });
});

test('performance metrics are reasonable', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
                ->waitFor('body', 10);
        
        // Get basic performance metrics
        $performanceData = $browser->driver->executeScript('
            if (!performance || !performance.getEntriesByType) {
                return null;
            }
            
            const navigation = performance.getEntriesByType("navigation")[0];
            if (!navigation) {
                return null;
            }
            
            return {
                domContentLoaded: navigation.domContentLoadedEventEnd - navigation.navigationStart,
                loadComplete: navigation.loadEventEnd - navigation.navigationStart,
                dnsLookup: navigation.domainLookupEnd - navigation.domainLookupStart
            };
        ');
        
        if ($performanceData) {
            echo "\nPerformance Metrics:\n";
            echo "DOM Content Loaded: " . round($performanceData['domContentLoaded'], 2) . "ms\n";
            echo "Load Complete: " . round($performanceData['loadComplete'], 2) . "ms\n";
            echo "DNS Lookup: " . round($performanceData['dnsLookup'], 2) . "ms\n";
            
            // Basic performance expectations
            expect($performanceData['domContentLoaded'])->toBeLessThan(3000); // 3 seconds
            expect($performanceData['dnsLookup'])->toBeLessThan(200); // 200ms
        }
    });
});

test('responsive breakpoints work correctly', function () {
    $this->browse(function (Browser $browser) {
        $viewports = [
            ['width' => 320, 'height' => 568, 'name' => 'iPhone SE'],
            ['width' => 390, 'height' => 844, 'name' => 'iPhone 12'],
            ['width' => 768, 'height' => 1024, 'name' => 'iPad'],
            ['width' => 1280, 'height' => 720, 'name' => 'Desktop'],
        ];
        
        foreach ($viewports as $viewport) {
            $browser->resize($viewport['width'], $viewport['height'])
                    ->visit('/')
                    ->waitFor('body', 10);
            
            // Check for horizontal scrollbar
            $hasHorizontalScroll = $browser->driver->executeScript('
                return document.body.scrollWidth > window.innerWidth;
            ');
            
            expect($hasHorizontalScroll)->toBe(false, 
                "Horizontal scroll detected on {$viewport['name']} ({$viewport['width']}x{$viewport['height']})");
            
            // Verify content is visible
            $browser->assertPresent('body')
                    ->assertVisible('body');
        }
    });
});