<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GDevelopCompleteWorkflowBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable GDevelop for testing
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        // Create test user and company
        $this->company = Company::factory()->create([
            'credits' => 1000
        ]);

        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id
        ]);

        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
    }

    /** @test */
    public function user_can_create_gdevelop_workspace_and_develop_game_through_complete_workflow()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/workspaces')
                ->assertSee('Workspaces')
                ->assertSee('Create your first workspace');

            // Step 1: Create GDevelop workspace
            $browser->click('@create-workspace-button')
                ->waitForRoute('workspaces.create')
                ->assertSee('Create Workspace')
                ->assertSee('GDevelop');

            // Select GDevelop engine
            $browser->click('[data-engine="gdevelop"]')
                ->assertSee('No-code game development')
                ->type('name', 'Test GDevelop Game')
                ->type('description', 'A test game created through browser automation')
                ->click('@create-workspace-submit')
                ->waitForRoute('chat')
                ->assertSee('Test GDevelop Game')
                ->assertSee('GDevelop');

            // Step 2: Create initial game through chat
            $browser->waitFor('@chat-input')
                ->type('@chat-input', 'Create a simple platformer game with a blue player character that can jump and move left and right. Add some platforms to jump on.')
                ->click('@send-message')
                ->waitFor('@ai-response', 30)
                ->assertSee('Game created successfully')
                ->assertSee('Preview');

            // Step 3: Test game preview
            $browser->click('@preview-button')
                ->waitFor('@game-preview-iframe', 15)
                ->assertPresent('@game-preview-iframe')
                ->assertSee('Preview');

            // Verify iframe loads game content
            $browser->withinFrame('@game-preview-iframe', function (Browser $frame) {
                $frame->waitFor('canvas', 10)
                    ->assertPresent('canvas');
            });

            // Step 4: Modify game through chat
            $browser->type('@chat-input', 'Add red enemies that move back and forth on the platforms. Make them respawn when the player touches them.')
                ->click('@send-message')
                ->waitFor('@ai-response', 30)
                ->assertSee('Game modified successfully');

            // Step 5: Test mobile optimization
            $browser->click('@mobile-settings-toggle')
                ->waitFor('@mobile-options')
                ->click('@enable-mobile-optimization')
                ->select('@target-device', 'mobile')
                ->select('@control-scheme', 'touch_direct')
                ->select('@orientation', 'portrait')
                ->click('@apply-mobile-settings');

            // Step 6: Test export functionality
            $browser->click('@export-button')
                ->waitFor('@export-options')
                ->check('@include-assets')
                ->select('@compression-level', 'standard')
                ->click('@start-export')
                ->waitFor('@export-progress', 5)
                ->assertSee('Exporting');

            // Wait for export completion
            $browser->waitUntil('document.querySelector("@download-link")', 30)
                ->assertPresent('@download-link')
                ->assertSee('Download');

            // Step 7: Test game session management
            $browser->click('@game-actions-menu')
                ->click('@session-info')
                ->assertSee('Session ID')
                ->assertSee('Last Modified')
                ->assertSee('Assets');

            // Step 8: Test error handling
            $browser->type('@chat-input', 'Create an impossible game that breaks the laws of physics and reality')
                ->click('@send-message')
                ->waitFor('@ai-response', 30);

            // Should handle gracefully without crashing
            $browser->assertDontSee('Fatal error')
                ->assertDontSee('Exception');

            // Step 9: Test refresh functionality
            $browser->click('@refresh-preview')
                ->waitFor('@preview-loading', 5)
                ->waitUntilMissing('@preview-loading', 10)
                ->assertPresent('@game-preview-iframe');

            // Step 10: Test fullscreen preview
            $browser->click('@fullscreen-preview');
            
            // Switch to new tab/window
            $browser->driver->getWindowHandles();
            $windows = $browser->driver->getWindowHandles();
            if (count($windows) > 1) {
                $browser->driver->switchTo()->window(end($windows));
                $browser->assertPresent('canvas')
                    ->driver->close();
                $browser->driver->switchTo()->window($windows[0]);
            }
        });
    }

    /** @test */
    public function user_can_create_multiple_game_types_through_chat()
    {
        $this->browse(function (Browser $browser) {
            // Create workspace first
            $workspace = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $this->user->id,
                'engine_type' => 'gdevelop',
                'name' => 'Multi-Game Test Workspace'
            ]);

            $browser->loginAs($this->user)
                ->visit("/chat?workspace={$workspace->id}")
                ->waitFor('@chat-input');

            // Test different game types
            $gameTypes = [
                [
                    'message' => 'Create a tower defense game with 3 different tower types and waves of enemies',
                    'expectedElements' => ['tower', 'enemy', 'wave']
                ],
                [
                    'message' => 'Now create a puzzle game with blocks that the player can move around to solve levels',
                    'expectedElements' => ['puzzle', 'block', 'level']
                ],
                [
                    'message' => 'Create an arcade-style space shooter with the player ship shooting at asteroids',
                    'expectedElements' => ['space', 'shooter', 'asteroid']
                ]
            ];

            foreach ($gameTypes as $index => $gameType) {
                $browser->type('@chat-input', $gameType['message'])
                    ->click('@send-message')
                    ->waitFor('@ai-response', 30)
                    ->assertSee('successfully');

                // Test preview for each game type
                $browser->click('@preview-button')
                    ->waitFor('@game-preview-iframe', 15)
                    ->assertPresent('@game-preview-iframe');

                // Verify game loads
                $browser->withinFrame('@game-preview-iframe', function (Browser $frame) {
                    $frame->waitFor('canvas', 10)
                        ->assertPresent('canvas');
                });

                // Clear for next iteration if not the last one
                if ($index < count($gameTypes) - 1) {
                    $browser->type('@chat-input', 'Start a completely new game, ignore the previous one')
                        ->click('@send-message')
                        ->waitFor('@ai-response', 30);
                }
            }
        });
    }

    /** @test */
    public function mobile_interface_works_correctly_on_touch_devices()
    {
        $this->browse(function (Browser $browser) {
            $workspace = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $this->user->id,
                'engine_type' => 'gdevelop',
                'name' => 'Mobile Test Workspace'
            ]);

            // Simulate mobile viewport
            $browser->resize(375, 667) // iPhone 6/7/8 size
                ->loginAs($this->user)
                ->visit("/chat?workspace={$workspace->id}")
                ->waitFor('@chat-input');

            // Create mobile-optimized game
            $browser->type('@chat-input', 'Create a mobile-friendly puzzle game with large touch-friendly buttons')
                ->click('@send-message')
                ->waitFor('@ai-response', 30)
                ->assertSee('successfully');

            // Verify mobile controls appear
            $browser->assertPresent('@mobile-controls')
                ->assertPresent('@mobile-settings')
                ->assertSee('Mobile Optimization');

            // Test mobile settings
            $browser->click('@mobile-settings-toggle')
                ->waitFor('@mobile-options')
                ->assertChecked('@mobile-optimized')
                ->assertSee('Touch Controls')
                ->assertSee('Responsive UI');

            // Test touch controls
            $browser->assertPresent('@virtual-controls')
                ->click('@touch-control-up')
                ->click('@touch-control-left')
                ->click('@touch-control-right');

            // Test preview on mobile
            $browser->click('@preview-button')
                ->waitFor('@game-preview-iframe', 15)
                ->assertPresent('@game-preview-iframe');

            // Verify mobile-responsive preview
            $browser->withinFrame('@game-preview-iframe', function (Browser $frame) {
                $frame->waitFor('canvas', 10)
                    ->assertPresent('canvas');
                
                // Check if canvas adapts to mobile viewport
                $canvasWidth = $frame->script('return document.querySelector("canvas").offsetWidth;')[0];
                $this->assertLessThanOrEqual(375, $canvasWidth);
            });

            // Test export with mobile optimization
            $browser->click('@export-button')
                ->waitFor('@export-options')
                ->assertChecked('@mobile-optimized')
                ->click('@start-export')
                ->waitFor('@export-progress', 5);
        });
    }

    /** @test */
    public function error_handling_displays_user_friendly_messages()
    {
        $this->browse(function (Browser $browser) {
            $workspace = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $this->user->id,
                'engine_type' => 'gdevelop',
                'name' => 'Error Test Workspace'
            ]);

            $browser->loginAs($this->user)
                ->visit("/chat?workspace={$workspace->id}")
                ->waitFor('@chat-input');

            // Test with insufficient credits
            $this->company->update(['credits' => 1]);

            $browser->type('@chat-input', 'Create a very complex RPG game with multiple systems')
                ->click('@send-message')
                ->waitFor('@error-message', 10)
                ->assertSee('Insufficient credits')
                ->assertDontSee('Fatal error')
                ->assertDontSee('Exception');

            // Restore credits
            $this->company->update(['credits' => 1000]);

            // Test with malformed request
            $browser->type('@chat-input', '')
                ->click('@send-message')
                ->waitFor('@validation-error', 5)
                ->assertSee('message is required')
                ->assertDontSee('Fatal error');

            // Test network error simulation
            $browser->script('
                window.originalFetch = window.fetch;
                window.fetch = () => Promise.reject(new Error("Network error"));
            ');

            $browser->type('@chat-input', 'Create a simple game')
                ->click('@send-message')
                ->waitFor('@network-error', 10)
                ->assertSee('network')
                ->assertDontSee('Fatal error');

            // Restore fetch
            $browser->script('window.fetch = window.originalFetch;');
        });
    }

    /** @test */
    public function performance_is_acceptable_for_user_interactions()
    {
        $this->browse(function (Browser $browser) {
            $workspace = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $this->user->id,
                'engine_type' => 'gdevelop',
                'name' => 'Performance Test Workspace'
            ]);

            $browser->loginAs($this->user)
                ->visit("/chat?workspace={$workspace->id}")
                ->waitFor('@chat-input');

            // Measure chat response time
            $startTime = microtime(true);
            
            $browser->type('@chat-input', 'Create a simple arcade game')
                ->click('@send-message')
                ->waitFor('@ai-response', 30);
            
            $chatResponseTime = microtime(true) - $startTime;
            $this->assertLessThan(30, $chatResponseTime, 'Chat response should be under 30 seconds');

            // Measure preview generation time
            $startTime = microtime(true);
            
            $browser->click('@preview-button')
                ->waitFor('@game-preview-iframe', 15);
            
            $previewTime = microtime(true) - $startTime;
            $this->assertLessThan(15, $previewTime, 'Preview should load within 15 seconds');

            // Test UI responsiveness
            $browser->click('@mobile-settings-toggle')
                ->waitFor('@mobile-options', 2) // Should be nearly instant
                ->click('@export-button')
                ->waitFor('@export-options', 2); // Should be nearly instant

            // Verify no UI freezing during operations
            $browser->type('@chat-input', 'Add more features to the game')
                ->click('@send-message');
            
            // UI should remain responsive while processing
            $browser->click('@mobile-settings-toggle')
                ->assertPresent('@mobile-options');
        });
    }

    /** @test */
    public function accessibility_features_work_correctly()
    {
        $this->browse(function (Browser $browser) {
            $workspace = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $this->user->id,
                'engine_type' => 'gdevelop',
                'name' => 'Accessibility Test Workspace'
            ]);

            $browser->loginAs($this->user)
                ->visit("/chat?workspace={$workspace->id}")
                ->waitFor('@chat-input');

            // Test keyboard navigation
            $browser->keys('@chat-input', 'Create a simple game')
                ->keys('@chat-input', '{enter}') // Should send message
                ->waitFor('@ai-response', 30);

            // Test ARIA labels and roles
            $browser->assertAttribute('@chat-input', 'aria-label', 'Chat message input')
                ->assertAttribute('@send-message', 'aria-label', 'Send message')
                ->assertAttribute('@preview-button', 'aria-label', 'Preview game');

            // Test focus management
            $browser->click('@preview-button')
                ->waitFor('@game-preview-iframe', 15)
                ->assertFocused('@game-preview-iframe');

            // Test screen reader compatibility
            $browser->assertPresent('[role="main"]')
                ->assertPresent('[role="button"]')
                ->assertPresent('[aria-live="polite"]'); // For status updates
        });
    }
}