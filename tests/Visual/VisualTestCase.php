<?php

namespace Tests\Visual;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\File;

abstract class VisualTestCase extends DuskTestCase
{
    protected string $screenshotPath = 'tests/Visual/screenshots';
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure screenshot directory exists
        if (!File::exists($this->screenshotPath)) {
            File::makeDirectory($this->screenshotPath, 0755, true);
        }
    }
    
    /**
     * Take a screenshot with a descriptive name
     */
    protected function takeScreenshot(Browser $browser, string $name, string $description = ''): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "{$timestamp}_{$name}";
        
        $browser->screenshot($filename);
        
        // Log the screenshot with description
        $this->logScreenshot($filename, $description);
    }
    
    /**
     * Take a full page screenshot
     */
    protected function takeFullPageScreenshot(Browser $browser, string $name, string $description = ''): void
    {
        $browser->script('document.body.style.height = "auto"');
        $this->takeScreenshot($browser, $name . '_fullpage', $description);
    }
    
    /**
     * Log screenshot information
     */
    protected function logScreenshot(string $filename, string $description): void
    {
        $logFile = $this->screenshotPath . '/screenshot_log.md';
        $entry = "## {$filename}\n";
        $entry .= "**Time:** " . now()->format('Y-m-d H:i:s') . "\n";
        $entry .= "**Description:** {$description}\n";
        $entry .= "**File:** {$filename}.png\n\n";
        
        File::append($logFile, $entry);
    }
    
    /**
     * Wait for React app to be ready
     */
    protected function waitForReactApp(Browser $browser): Browser
    {
        return $browser->waitFor('#app', 10)
                      ->waitUntilMissing('.loading', 5);
    }
    
    /**
     * Wait for Inertia page to load
     */
    protected function waitForInertiaPage(Browser $browser): Browser
    {
        return $browser->waitFor('[data-page]', 10);
    }
    
    /**
     * Test responsive design at different breakpoints
     */
    protected function testResponsiveBreakpoints(Browser $browser, string $testName): void
    {
        $breakpoints = [
            'mobile' => [375, 667],
            'tablet' => [768, 1024],
            'desktop' => [1920, 1080],
        ];
        
        foreach ($breakpoints as $device => $dimensions) {
            $browser->resize($dimensions[0], $dimensions[1]);
            $browser->pause(1000); // Allow layout to adjust
            $this->takeScreenshot($browser, "{$testName}_{$device}", "Testing {$testName} on {$device} ({$dimensions[0]}x{$dimensions[1]})");
        }
    }
}