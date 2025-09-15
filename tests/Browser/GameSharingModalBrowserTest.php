<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Game;
use App\Models\Workspace;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class GameSharingModalBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'credits' => 1000,
        ]);
        
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Workspace',
            'engine_type' => 'playcanvas',
        ]);
        
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Tower Defense Game',
            'description' => 'A test game for sharing functionality',
            'engine_type' => 'playcanvas',
            'status' => 'active',
            'metadata' => [
                'version' => '1.0.0',
                'build_status' => 'success',
            ],
        ]);
    }

    public function test_can_open_game_sharing_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->waitFor('[data-testid="share-game-button"]')
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->assertSee('Share Game')
                ->assertSee($this->game->title)
                ->assertSee('PlayCanvas');
        });
    }

    public function test_can_create_shareable_link()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->assertSee('No public share link exists yet')
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                ->assertPresent('[data-testid="share-url-input"]')
                ->assertSee('Shareable link created successfully!');
        });
    }

    public function test_can_copy_share_link_to_clipboard()
    {
        // First create a share link
        $this->game->update([
            'sharing_settings' => [
                'allowEmbedding' => true,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ],
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                ->click('[data-testid="copy-share-url-button"]')
                ->waitFor('[data-testid="copy-success-icon"]', 3)
                ->assertPresent('[data-testid="copy-success-icon"]');
        });
    }

    public function test_can_navigate_between_tabs()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Test Share Link tab (default)
                ->assertSee('Public Share Link')
                
                // Test Embed tab
                ->click('[data-testid="embed-tab"]')
                ->waitFor('[data-testid="embed-content"]')
                ->assertSee('Embed Code')
                
                // Test Social tab
                ->click('[data-testid="social-tab"]')
                ->waitFor('[data-testid="social-content"]')
                ->assertSee('Social Sharing')
                
                // Test Settings tab
                ->click('[data-testid="settings-tab"]')
                ->waitFor('[data-testid="settings-content"]')
                ->assertSee('Sharing Settings');
        });
    }

    public function test_can_configure_sharing_settings()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="settings-tab"]')
                ->waitFor('[data-testid="settings-content"]')
                
                // Toggle embedding setting
                ->click('[data-testid="allow-embedding-switch"]')
                
                // Toggle controls setting
                ->click('[data-testid="show-controls-switch"]')
                
                // Toggle info setting
                ->click('[data-testid="show-info-switch"]')
                
                // Change expiration days
                ->clear('[data-testid="expiration-days-input"]')
                ->type('[data-testid="expiration-days-input"]', '60')
                
                // Save settings
                ->click('[data-testid="save-settings-button"]')
                ->waitFor('[data-testid="success-message"]', 5)
                ->assertSee('Sharing settings updated successfully!');
        });
    }

    public function test_embed_code_generation()
    {
        // Create a game with sharing enabled
        $this->game->update([
            'sharing_settings' => [
                'allowEmbedding' => true,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ],
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Create share link first
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                
                // Navigate to embed tab
                ->click('[data-testid="embed-tab"]')
                ->waitFor('[data-testid="embed-content"]')
                ->assertSee('Embed Code')
                ->assertPresent('[data-testid="embed-code-block"]')
                ->assertSee('<iframe')
                
                // Test preview mode switching
                ->click('[data-testid="mobile-preview-button"]')
                ->assertSee('width="375"')
                ->click('[data-testid="desktop-preview-button"]')
                ->assertSee('width="800"')
                
                // Test copy embed code
                ->click('[data-testid="copy-embed-code-button"]')
                ->waitFor('[data-testid="copy-success-icon"]', 3);
        });
    }

    public function test_social_sharing_links()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Create share link first
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                
                // Navigate to social tab
                ->click('[data-testid="social-tab"]')
                ->waitFor('[data-testid="social-content"]')
                ->assertSee('Social Sharing')
                
                // Check social platform buttons
                ->assertPresent('[data-testid="twitter-share-button"]')
                ->assertPresent('[data-testid="facebook-share-button"]')
                ->assertPresent('[data-testid="linkedin-share-button"]')
                ->assertPresent('[data-testid="whatsapp-share-button"]')
                ->assertPresent('[data-testid="email-share-button"]')
                
                // Verify Twitter link contains correct URL structure
                ->assertAttribute('[data-testid="twitter-share-button"]', 'href', function ($href) {
                    return str_contains($href, 'twitter.com/intent/tweet');
                });
        });
    }

    public function test_can_revoke_share_link()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Create share link first
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                
                // Revoke the link
                ->click('[data-testid="revoke-link-button"]')
                ->waitFor('[data-testid="success-message"]', 5)
                ->assertSee('Share link revoked successfully!')
                ->assertSee('No public share link exists yet');
        });
    }

    public function test_displays_sharing_statistics()
    {
        // Create a game with existing sharing data
        $this->game->update([
            'sharing_settings' => [
                'allowEmbedding' => true,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ],
            'metadata' => array_merge($this->game->metadata ?? [], [
                'sharing_stats' => [
                    'total_plays' => 42,
                    'last_played' => now()->subHours(2)->toISOString(),
                ],
            ]),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Create share link to display stats
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="sharing-stats"]', 10)
                ->assertSee('42')
                ->assertSee('Total Plays')
                ->assertSee('Recent')
                ->assertSee('Last Played');
        });
    }

    public function test_handles_embedding_disabled_state()
    {
        // Create a game with embedding disabled
        $this->game->update([
            'sharing_settings' => [
                'allowEmbedding' => false,
                'showControls' => true,
                'showInfo' => true,
                'expirationDays' => 30,
            ],
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Create share link first
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="share-url-input"]', 10)
                
                // Navigate to embed tab
                ->click('[data-testid="embed-tab"]')
                ->waitFor('[data-testid="embed-content"]')
                ->assertSee('Embedding is disabled in sharing settings')
                ->assertDontSee('<iframe');
        });
    }

    public function test_mobile_responsive_design()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->resize(375, 667) // Mobile viewport
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                
                // Modal should be responsive
                ->assertPresent('[data-testid="game-sharing-modal"]')
                ->assertSee('Share Game')
                
                // Tabs should be accessible on mobile
                ->click('[data-testid="settings-tab"]')
                ->waitFor('[data-testid="settings-content"]')
                ->assertSee('Sharing Settings')
                
                // Settings should be mobile-friendly
                ->assertPresent('[data-testid="allow-embedding-switch"]')
                ->assertPresent('[data-testid="expiration-days-input"]');
        });
    }

    public function test_error_handling_for_failed_operations()
    {
        $this->browse(function (Browser $browser) {
            // Mock a scenario where sharing fails (e.g., insufficient credits)
            $this->company->update(['credits' => 0]);
            
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="create-share-link-button"]')
                ->waitFor('[data-testid="error-message"]', 10)
                ->assertSee('error'); // Should show some error message
        });
    }

    public function test_can_close_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->assertSee('Share Game')
                
                // Close via close button
                ->click('[data-testid="close-modal-button"]')
                ->waitUntilMissing('[data-testid="game-sharing-modal"]')
                ->assertDontSee('Share Game');
        });
    }

    public function test_preserves_settings_between_modal_opens()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/games/' . $this->game->id)
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="settings-tab"]')
                ->waitFor('[data-testid="settings-content"]')
                
                // Change settings
                ->click('[data-testid="allow-embedding-switch"]')
                ->clear('[data-testid="expiration-days-input"]')
                ->type('[data-testid="expiration-days-input"]', '90')
                ->click('[data-testid="save-settings-button"]')
                ->waitFor('[data-testid="success-message"]', 5)
                
                // Close and reopen modal
                ->click('[data-testid="close-modal-button"]')
                ->waitUntilMissing('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="share-game-button"]')
                ->waitFor('[data-testid="game-sharing-modal"]')
                ->click('[data-testid="settings-tab"]')
                ->waitFor('[data-testid="settings-content"]')
                
                // Verify settings are preserved
                ->assertInputValue('[data-testid="expiration-days-input"]', '90');
        });
    }
}