<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class GDevelopMobileOptimizationBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        config(['gdevelop.enabled' => true]);
    }

    public function test_user_can_enable_mobile_optimization_for_gdevelop_games()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    ->assertSee('Mobile Optimization')
                    
                    // Enable mobile optimization
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->waitFor('[data-testid="mobile-optimization-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    ->assertSee('Enabled')
                    
                    // Configure mobile settings
                    ->select('[data-testid="target-device-select"]', 'mobile')
                    ->select('[data-testid="control-scheme-select"]', 'touch_direct')
                    ->select('[data-testid="orientation-select"]', 'portrait')
                    ->click('[data-testid="touch-controls-toggle"]')
                    ->click('[data-testid="responsive-ui-toggle"]')
                    
                    // Create a mobile-optimized game
                    ->type('[data-testid="chat-input"]', 'Create a simple puzzle game for mobile')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="preview-button"]', 30)
                    
                    // Verify mobile controls appear
                    ->assertSee('Mobile Controls')
                    ->assertSee('Touch & Gesture Area');
        });
    }

    public function test_mobile_preview_adapts_to_different_screen_sizes()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        // Create a game session with mobile optimization
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'game_json' => [
                'properties' => [
                    'name' => 'Mobile Test Game',
                    'orientation' => 'portrait',
                    'adaptGameResolutionAtRuntime' => true,
                    'mobileViewport' => [
                        'width' => 'device-width',
                        'initialScale' => 1.0,
                        'userScalable' => false
                    ]
                ],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace, $session) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Generate preview
                    ->click('[data-testid="preview-button"]')
                    ->waitFor('[data-testid="game-preview"]', 15)
                    
                    // Test desktop view
                    ->assertSee('Game Preview')
                    ->click('[data-testid="view-mode-toggle"]')
                    
                    // Switch to mobile view
                    ->waitFor('[data-testid="mobile-preview"]', 5)
                    ->assertPresent('[data-testid="mobile-preview"]')
                    
                    // Verify mobile dimensions are applied
                    ->with('[data-testid="mobile-preview"]', function ($preview) {
                        $preview->assertAttribute('iframe', 'style', function ($style) {
                            return str_contains($style, 'touch-action: manipulation');
                        });
                    });
        });
    }

    public function test_mobile_controls_respond_to_touch_interactions()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Enable mobile optimization
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    ->select('[data-testid="control-scheme-select"]', 'virtual_dpad')
                    
                    // Create a platformer game
                    ->type('[data-testid="chat-input"]', 'Create a platformer game')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="mobile-controls"]', 30)
                    
                    // Test virtual D-pad controls
                    ->assertPresent('[data-testid="dpad-up"]')
                    ->assertPresent('[data-testid="dpad-down"]')
                    ->assertPresent('[data-testid="dpad-left"]')
                    ->assertPresent('[data-testid="dpad-right"]')
                    ->assertPresent('[data-testid="action-button-1"]')
                    ->assertPresent('[data-testid="action-button-2"]')
                    
                    // Test button interactions
                    ->click('[data-testid="dpad-up"]')
                    ->pause(100) // Brief pause to simulate button press
                    
                    // Verify haptic feedback toggle works
                    ->click('[data-testid="haptic-toggle"]')
                    ->assertSee('Disable haptic feedback');
        });
    }

    public function test_mobile_game_exports_include_mobile_optimizations()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Enable mobile optimization
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    ->select('[data-testid="target-device-select"]', 'mobile')
                    
                    // Create a mobile game
                    ->type('[data-testid="chat-input"]', 'Create a mobile puzzle game')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="export-button"]', 30)
                    
                    // Configure export options for mobile
                    ->click('[data-testid="export-button"]')
                    ->waitFor('[data-testid="export-options"]', 5)
                    ->check('[data-testid="mobile-optimization-export"]')
                    ->select('[data-testid="export-format"]', 'html5')
                    
                    // Start export
                    ->click('[data-testid="start-export"]')
                    ->waitFor('[data-testid="export-complete"]', 60)
                    ->assertSee('Export Complete')
                    
                    // Verify download link is available
                    ->assertPresent('[data-testid="download-link"]');
        });
    }

    public function test_mobile_preview_performance_monitoring()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Enable mobile optimization
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    
                    // Create a game
                    ->type('[data-testid="chat-input"]', 'Create a simple arcade game')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="preview-button"]', 30)
                    
                    // Generate preview
                    ->click('[data-testid="preview-button"]')
                    ->waitFor('[data-testid="game-preview"]', 15)
                    
                    // Switch to mobile view
                    ->click('[data-testid="view-mode-toggle"]')
                    ->waitFor('[data-testid="performance-info"]', 10)
                    
                    // Verify performance metrics are shown
                    ->assertSee('Performance')
                    ->assertSee('Load Time:')
                    ->assertSee('View Mode: mobile')
                    
                    // Check mobile-specific performance indicators
                    ->assertPresent('[data-testid="mobile-performance-badge"]');
        });
    }

    public function test_mobile_orientation_change_handling()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Enable mobile optimization with responsive UI
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    ->click('[data-testid="responsive-ui-toggle"]')
                    
                    // Create a game
                    ->type('[data-testid="chat-input"]', 'Create a mobile-friendly puzzle game')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="preview-button"]', 30)
                    
                    // Generate preview
                    ->click('[data-testid="preview-button"]')
                    ->waitFor('[data-testid="game-preview"]', 15)
                    
                    // Switch to mobile view
                    ->click('[data-testid="view-mode-toggle"]')
                    
                    // Simulate orientation change by resizing browser
                    ->resize(667, 375) // Landscape
                    ->pause(1000)
                    ->assertPresent('[data-testid="landscape-indicator"]')
                    
                    ->resize(375, 667) // Portrait
                    ->pause(1000)
                    ->assertPresent('[data-testid="portrait-indicator"]');
        });
    }

    public function test_mobile_controls_hide_and_show_functionality()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'owner']);
        $user->update(['current_company_id' => $company->id]);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine' => 'gdevelop'
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                    ->visit("/workspaces/{$workspace->id}/chat")
                    ->waitFor('[data-testid="gdevelop-chat-interface"]', 10)
                    
                    // Enable mobile optimization
                    ->click('[data-testid="mobile-settings-toggle"]')
                    ->click('[data-testid="mobile-optimization-toggle"]')
                    ->click('[data-testid="touch-controls-toggle"]')
                    
                    // Create a game
                    ->type('[data-testid="chat-input"]', 'Create a simple game')
                    ->click('[data-testid="send-message"]')
                    ->waitFor('[data-testid="mobile-controls"]', 30)
                    
                    // Verify controls are visible
                    ->assertPresent('[data-testid="mobile-controls"]')
                    ->assertSee('Mobile Controls')
                    
                    // Hide controls
                    ->click('[data-testid="hide-controls"]')
                    ->waitUntilMissing('[data-testid="mobile-controls-panel"]')
                    ->assertPresent('[data-testid="show-controls-button"]')
                    
                    // Show controls again
                    ->click('[data-testid="show-controls-button"]')
                    ->waitFor('[data-testid="mobile-controls-panel"]')
                    ->assertSee('Mobile Controls');
        });
    }
}