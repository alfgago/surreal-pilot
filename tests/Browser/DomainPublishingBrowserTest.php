<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\Game;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DomainPublishingBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->user->companies()->attach($this->company->id, ['role' => 'admin']);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);
        
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Tower Defense Game',
            'preview_url' => 'http://localhost/preview/test-game'
        ]);
    }

    public function test_user_can_open_domain_publishing_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->waitFor('@game-' . $this->game->id)
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    ->assertSee('Custom Domain Publishing')
                    ->assertSee('Setup Wizard')
                    ->assertSee('Domain Status')
                    ->assertSee('Troubleshooting');
        });
    }

    public function test_user_can_setup_custom_domain_through_wizard()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Step 1: Enter domain
                    ->assertSee('Enter Your Custom Domain')
                    ->type('@domain-input', 'my-tower-defense.com')
                    ->assertEnabled('@continue-button')
                    ->click('@continue-button')
                    
                    // Should advance to DNS setup step
                    ->waitFor('@dns-configuration', 10)
                    ->assertSee('Configure DNS Settings')
                    ->assertSee('DNS Record Details')
                    ->assertSee('A Record')
                    ->assertSee('127.0.0.1') // Default server IP for testing
                    
                    // Check DNS instructions are displayed
                    ->assertSee('Step-by-Step Instructions')
                    ->assertSee('Log into your domain registrar')
                    ->assertSee('Common DNS Providers')
                    ->assertSee('Cloudflare')
                    ->assertSee('GoDaddy');
        });
    }

    public function test_user_can_copy_dns_configuration_values()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Setup domain first
                    ->type('@domain-input', 'copy-test.com')
                    ->click('@continue-button')
                    ->waitFor('@dns-configuration')
                    
                    // Test copy functionality
                    ->click('@copy-dns-type')
                    ->waitFor('@copy-success-type', 2)
                    ->assertSee('✓') // Check mark appears
                    
                    ->click('@copy-dns-value')
                    ->waitFor('@copy-success-value', 2)
                    ->assertSee('✓');
        });
    }

    public function test_user_can_verify_domain_configuration()
    {
        // Setup game with pending domain
        $this->game->update([
            'custom_domain' => 'pending-verification.com',
            'domain_status' => 'pending',
            'domain_config' => [
                'server_ip' => '127.0.0.1',
                'setup_date' => now()->toISOString()
            ]
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Should start at verification step for pending domain
                    ->assertSee('Verify Domain Configuration')
                    ->assertSee('Verification Pending')
                    ->assertSee('pending-verification.com')
                    
                    // Test manual verification
                    ->click('@verify-domain-button')
                    ->waitFor('@verification-result', 10)
                    
                    // Should show verification status
                    ->assertSeeIn('@verification-result', 'Verification');
        });
    }

    public function test_user_can_view_troubleshooting_guide()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Switch to troubleshooting tab
                    ->click('@troubleshooting-tab')
                    ->waitFor('@troubleshooting-guide')
                    ->assertSee('Domain Setup Troubleshooting')
                    ->assertSee('DNS Propagation Issues')
                    ->assertSee('Incorrect DNS Configuration')
                    ->assertSee('Domain Registrar Issues')
                    
                    // Test expanding troubleshooting sections
                    ->click('@troubleshooting-section-dns-propagation')
                    ->waitFor('@troubleshooting-steps-dns-propagation')
                    ->assertSee('Wait 5-30 minutes after making DNS changes')
                    ->assertSee('Check DNS propagation status using online tools')
                    
                    // Test external tool links
                    ->assertSee('whatsmydns.net')
                    ->assertSee('dnschecker.org');
        });
    }

    public function test_user_can_access_active_domain()
    {
        // Setup game with active domain
        $this->game->update([
            'custom_domain' => 'active-game.com',
            'domain_status' => 'active',
            'domain_config' => [
                'server_ip' => '127.0.0.1',
                'ssl_enabled' => false,
                'verified_at' => now()->toISOString()
            ]
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Should show active status
                    ->assertSee('Domain Successfully Configured!')
                    ->assertSee('Your Game is Live!')
                    ->assertSee('active-game.com')
                    
                    // Should have visit game button
                    ->assertPresent('@visit-game-button')
                    ->assertAttribute('@visit-game-button', 'href', 'http://active-game.com');
        });
    }

    public function test_user_can_remove_custom_domain()
    {
        // Setup game with active domain
        $this->game->update([
            'custom_domain' => 'to-be-removed.com',
            'domain_status' => 'active',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Should show remove domain button
                    ->assertPresent('@remove-domain-button')
                    ->click('@remove-domain-button')
                    
                    // Should close modal after successful removal
                    ->waitUntilMissing('@domain-publishing-modal', 5);
        });
    }

    public function test_domain_status_auto_updates()
    {
        // Setup game with pending domain
        $this->game->update([
            'custom_domain' => 'auto-update-test.com',
            'domain_status' => 'pending',
            'domain_config' => ['server_ip' => '127.0.0.1']
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Should show auto-checking indicator
                    ->assertSee('Auto-checking...')
                    ->assertSee('Verification Pending')
                    
                    // Wait for auto-check to complete (mocked in test)
                    ->waitFor('@verification-status', 35); // Auto-check runs every 30 seconds
        });
    }

    public function test_mobile_responsive_domain_publishing()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->resize(375, 667) // iPhone SE dimensions
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Modal should be responsive
                    ->assertPresent('@domain-publishing-modal')
                    ->assertSee('Custom Domain Setup')
                    
                    // Form elements should be properly sized
                    ->type('@domain-input', 'mobile-test.com')
                    ->assertEnabled('@continue-button')
                    
                    // Navigation tabs should work on mobile
                    ->click('@troubleshooting-tab')
                    ->waitFor('@troubleshooting-guide')
                    ->assertSee('Domain Setup Troubleshooting');
        });
    }

    public function test_domain_validation_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Test invalid domain formats
                    ->type('@domain-input', 'invalid..domain')
                    ->click('@continue-button')
                    ->waitFor('@error-message')
                    ->assertSee('Invalid domain format')
                    
                    // Test localhost rejection
                    ->clear('@domain-input')
                    ->type('@domain-input', 'localhost')
                    ->click('@continue-button')
                    ->waitFor('@error-message')
                    ->assertSee('Localhost and IP addresses are not allowed');
        });
    }

    public function test_dns_provider_specific_instructions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Setup domain to reach DNS configuration
                    ->type('@domain-input', 'provider-test.com')
                    ->click('@continue-button')
                    ->waitFor('@dns-configuration')
                    
                    // Check common providers are listed
                    ->assertSee('Common DNS Providers')
                    ->assertSee('Cloudflare')
                    ->assertSee('DNS > Records > Add record')
                    ->assertSee('GoDaddy')
                    ->assertSee('DNS Management > Add Record')
                    ->assertSee('Namecheap')
                    ->assertSee('Advanced DNS > Add New Record')
                    ->assertSee('Google Domains')
                    ->assertSee('DNS > Custom records');
        });
    }

    public function test_domain_publishing_workflow_completion()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/games')
                    ->click('@game-' . $this->game->id . '-publish-button')
                    ->waitFor('@domain-publishing-modal')
                    
                    // Complete full workflow
                    ->type('@domain-input', 'complete-workflow.com')
                    ->click('@continue-button')
                    ->waitFor('@dns-configuration')
                    ->click('@continue-to-verification')
                    ->waitFor('@verification-step')
                    ->assertSee('Verify Domain Configuration')
                    
                    // Modal should remain functional throughout
                    ->assertPresent('@domain-publishing-modal')
                    ->assertSee('complete-workflow.com');
        });
    }
}