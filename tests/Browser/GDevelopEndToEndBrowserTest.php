<?php

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    Config::set('gdevelop.engines.playcanvas_enabled', false);
});

test('complete user workflow from registration to game export via browser', function () {
    // Create test user and company
    $company = Company::factory()->create([
        'name' => 'Test Game Studio',
        'credits' => 1000
    ]);
    
    $user = User::factory()->create([
        'name' => 'Test Developer',
        'email' => 'developer@testgamestudio.com',
        'password' => Hash::make('password123'),
        'current_company_id' => $company->id
    ]);
    
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $this->browse(function ($browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/workspaces')
            ->assertSee('Workspaces')
            ->waitFor('@create-workspace-button', 10)
            ->click('@create-workspace-button')
            ->waitForRoute('workspaces.create')
            ->assertSee('Create Workspace')
            ->assertSee('GDevelop');
        
        // Select GDevelop engine
        $browser->click('[data-engine="gdevelop"]')
            ->assertSee('No-code game development')
            ->type('name', 'My Test Game Project')
            ->type('description', 'A comprehensive test of the GDevelop integration')
            ->click('@create-workspace-submit')
            ->waitForRoute('chat', 30)
            ->assertSee('My Test Game Project')
            ->assertSee('GDevelop');
        
        // Create initial game through chat
        $browser->waitFor('@chat-input', 10)
            ->type('@chat-input', 'Create a simple platformer game with a blue player character that can jump and move left and right. Add some platforms to jump on.')
            ->click('@send-message')
            ->waitFor('@ai-response', 60) // Allow more time for AI response
            ->assertSee('Game created successfully')
            ->assertSee('Preview');
        
        // Test game preview
        $browser->click('@preview-button')
            ->waitFor('@game-preview-iframe', 30)
            ->assertPresent('@game-preview-iframe')
            ->assertSee('Preview');
        
        // Verify iframe loads game content
        $browser->withinFrame('@game-preview-iframe', function ($frame) {
            $frame->waitFor('canvas', 20)
                ->assertPresent('canvas');
        });
        
        // Modify game through chat
        $browser->type('@chat-input', 'Add red enemies that move back and forth on the platforms. Make them respawn when the player touches them.')
            ->click('@send-message')
            ->waitFor('@ai-response', 60)
            ->assertSee('Game modified successfully');
        
        // Test export functionality
        $browser->click('@export-button')
            ->waitFor('@export-options', 10)
            ->check('@include-assets')
            ->select('@compression-level', 'standard')
            ->click('@start-export')
            ->waitFor('@export-progress', 10)
            ->assertSee('Exporting');
        
        // Wait for export completion (with timeout)
        $browser->waitUntil('document.querySelector("@download-link")', 60)
            ->assertPresent('@download-link')
            ->assertSee('Download');
        
        // Test refresh functionality
        $browser->click('@refresh-preview')
            ->waitFor('@preview-loading', 10)
            ->waitUntilMissing('@preview-loading', 20)
            ->assertPresent('@game-preview-iframe');
    });
});

test('mobile interface works correctly on touch devices', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = \App\Models\Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Mobile Test Workspace'
    ]);
    
    $this->browse(function ($browser) use ($user, $workspace) {
        // Simulate mobile viewport
        $browser->resize(375, 667) // iPhone 6/7/8 size
            ->loginAs($user)
            ->visit("/chat?workspace={$workspace->id}")
            ->waitFor('@chat-input', 10);
        
        // Create mobile-optimized game
        $browser->type('@chat-input', 'Create a mobile-friendly puzzle game with large touch-friendly buttons')
            ->click('@send-message')
            ->waitFor('@ai-response', 60)
            ->assertSee('successfully');
        
        // Verify mobile controls appear
        $browser->assertPresent('@mobile-controls')
            ->assertPresent('@mobile-settings')
            ->assertSee('Mobile Optimization');
        
        // Test mobile settings
        $browser->click('@mobile-settings-toggle')
            ->waitFor('@mobile-options', 5)
            ->assertChecked('@mobile-optimized')
            ->assertSee('Touch Controls')
            ->assertSee('Responsive UI');
        
        // Test preview on mobile
        $browser->click('@preview-button')
            ->waitFor('@game-preview-iframe', 30)
            ->assertPresent('@game-preview-iframe');
        
        // Verify mobile-responsive preview
        $browser->withinFrame('@game-preview-iframe', function ($frame) {
            $frame->waitFor('canvas', 20)
                ->assertPresent('canvas');
            
            // Check if canvas adapts to mobile viewport
            $canvasWidth = $frame->script('return document.querySelector("canvas").offsetWidth;')[0];
            expect($canvasWidth)->toBeLessThanOrEqual(375);
        });
    });
});

test('error handling displays user friendly messages', function () {
    $company = Company::factory()->create(['credits' => 1]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = \App\Models\Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Error Test Workspace'
    ]);
    
    $this->browse(function ($browser) use ($user, $workspace, $company) {
        $browser->loginAs($user)
            ->visit("/chat?workspace={$workspace->id}")
            ->waitFor('@chat-input', 10);
        
        // Test with insufficient credits
        $browser->type('@chat-input', 'Create a very complex RPG game with multiple systems')
            ->click('@send-message')
            ->waitFor('@error-message', 20)
            ->assertSee('Insufficient credits')
            ->assertDontSee('Fatal error')
            ->assertDontSee('Exception');
        
        // Restore credits for next test
        $company->update(['credits' => 1000]);
        
        // Test with empty message
        $browser->type('@chat-input', '')
            ->click('@send-message')
            ->waitFor('@validation-error', 10)
            ->assertSee('message is required')
            ->assertDontSee('Fatal error');
    });
});

test('performance is acceptable for user interactions', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = \App\Models\Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Performance Test Workspace'
    ]);
    
    $this->browse(function ($browser) use ($user, $workspace) {
        $browser->loginAs($user)
            ->visit("/chat?workspace={$workspace->id}")
            ->waitFor('@chat-input', 10);
        
        // Measure chat response time
        $startTime = microtime(true);
        
        $browser->type('@chat-input', 'Create a simple arcade game')
            ->click('@send-message')
            ->waitFor('@ai-response', 60);
        
        $chatResponseTime = microtime(true) - $startTime;
        expect($chatResponseTime)->toBeLessThan(60); // Should be under 60 seconds
        
        // Measure preview generation time
        $startTime = microtime(true);
        
        $browser->click('@preview-button')
            ->waitFor('@game-preview-iframe', 30);
        
        $previewTime = microtime(true) - $startTime;
        expect($previewTime)->toBeLessThan(30); // Should load within 30 seconds
        
        // Test UI responsiveness
        $browser->click('@mobile-settings-toggle')
            ->waitFor('@mobile-options', 5) // Should be nearly instant
            ->click('@export-button')
            ->waitFor('@export-options', 5); // Should be nearly instant
    });
});

test('accessibility features work correctly', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = \App\Models\Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Accessibility Test Workspace'
    ]);
    
    $this->browse(function ($browser) use ($user, $workspace) {
        $browser->loginAs($user)
            ->visit("/chat?workspace={$workspace->id}")
            ->waitFor('@chat-input', 10);
        
        // Test keyboard navigation
        $browser->keys('@chat-input', 'Create a simple game')
            ->keys('@chat-input', '{enter}') // Should send message
            ->waitFor('@ai-response', 60);
        
        // Test ARIA labels and roles
        $browser->assertAttribute('@chat-input', 'aria-label', 'Chat message input')
            ->assertAttribute('@send-message', 'aria-label', 'Send message')
            ->assertAttribute('@preview-button', 'aria-label', 'Preview game');
        
        // Test focus management
        $browser->click('@preview-button')
            ->waitFor('@game-preview-iframe', 30)
            ->assertFocused('@game-preview-iframe');
        
        // Test screen reader compatibility
        $browser->assertPresent('[role="main"]')
            ->assertPresent('[role="button"]')
            ->assertPresent('[aria-live="polite"]'); // For status updates
    });
});

test('cross browser compatibility works', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = \App\Models\Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Cross Browser Test Workspace'
    ]);
    
    $this->browse(function ($browser) use ($user, $workspace) {
        $browser->loginAs($user)
            ->visit("/chat?workspace={$workspace->id}")
            ->waitFor('@chat-input', 10);
        
        // Create game
        $browser->type('@chat-input', 'Create a simple arcade game')
            ->click('@send-message')
            ->waitFor('@ai-response', 60);
        
        // Test preview with different user agents
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        ];
        
        foreach ($userAgents as $userAgent) {
            $browser->driver->executeScript("Object.defineProperty(navigator, 'userAgent', {get: function(){return '{$userAgent}';}});");
            
            $browser->click('@preview-button')
                ->waitFor('@game-preview-iframe', 30)
                ->assertPresent('@game-preview-iframe');
            
            // Verify game loads in different browsers
            $browser->withinFrame('@game-preview-iframe', function ($frame) {
                $frame->waitFor('canvas', 20)
                    ->assertPresent('canvas');
            });
        }
    });
});