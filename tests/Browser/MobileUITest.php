<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MobileUITest extends DuskTestCase
{
    /**
     * Test mobile chat interface loads correctly
     */
    public function test_mobile_chat_interface_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->assertSee('SurrealPilot Mobile')
                    ->assertSee('Welcome to SurrealPilot Mobile!')
                    ->assertPresent('#mobile-message-input')
                    ->assertPresent('#mobile-send-btn')
                    ->assertPresent('#mobile-credit-badge');
        });
    }

    /**
     * Test mobile demo chooser modal functionality
     */
    public function test_mobile_demo_chooser_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->click('#mobile-menu-btn')
                    ->waitFor('#mobile-menu-panel')
                    ->click('#mobile-demos-btn')
                    ->waitFor('#mobile-demo-modal')
                    ->assertSee('Choose Demo Template')
                    ->assertPresent('#mobile-demo-list')
                    ->click('#mobile-demo-close')
                    ->waitUntilMissing('#mobile-demo-modal');
        });
    }

    /**
     * Test touch-friendly input and character counter
     */
    public function test_mobile_input_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->type('#mobile-message-input', 'Test message')
                    ->assertSeeIn('#mobile-char-counter', '12')
                    ->assertPresent('#mobile-send-btn:not([disabled])')
                    ->clear('#mobile-message-input')
                    ->assertSeeIn('#mobile-char-counter', '0');
        });
    }

    /**
     * Test smart suggestions functionality
     */
    public function test_mobile_smart_suggestions()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->type('#mobile-message-input', 'jump')
                    ->waitFor('#mobile-suggestions')
                    ->assertSee('double the jump height')
                    ->clear('#mobile-message-input')
                    ->waitUntilMissing('#mobile-suggestions');
        });
    }

    /**
     * Test mobile menu functionality
     */
    public function test_mobile_menu_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->click('#mobile-menu-btn')
                    ->waitFor('#mobile-menu-panel')
                    ->assertVisible('#mobile-menu-panel')
                    ->assertSee('Demo Templates')
                    ->assertSee('My Workspaces')
                    ->click('#mobile-menu-close')
                    ->waitUntilMissing('#mobile-menu-panel');
        });
    }

    /**
     * Test workspace actions visibility
     */
    public function test_workspace_actions_hidden_initially()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->assertMissing('#mobile-workspace-actions')
                    ->assertNotPresent('#mobile-preview-btn')
                    ->assertNotPresent('#mobile-publish-btn');
        });
    }

    /**
     * Test quick action buttons
     */
    public function test_mobile_quick_actions()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->assertSee('Jump Higher')
                    ->assertSee('Faster Enemies')
                    ->assertSee('More Effects')
                    ->assertSee('New Lighting')
                    ->click('.mobile-quick-action:first-child')
                    ->assertInputValue('#mobile-message-input', 'double the jump height');
        });
    }

    /**
     * Test responsive design in portrait mode
     */
    public function test_mobile_portrait_layout()
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone SE dimensions
                    ->visit('/mobile/chat')
                    ->assertSee('SurrealPilot')
                    ->assertPresent('.mobile-chat-container')
                    ->assertPresent('#mobile-message-input')
                    ->assertPresent('#mobile-send-btn');
        });
    }

    /**
     * Test responsive design in landscape mode
     */
    public function test_mobile_landscape_layout()
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(667, 375) // iPhone SE landscape
                    ->visit('/mobile/chat')
                    ->assertSee('SurrealPilot')
                    ->assertPresent('.landscape-compact')
                    ->assertPresent('#mobile-message-input')
                    ->assertPresent('#mobile-send-btn');
        });
    }

    /**
     * Test touch target sizes meet accessibility standards
     */
    public function test_touch_target_sizes()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->script([
                        'const touchTargets = document.querySelectorAll(".touch-target");',
                        'let allValid = true;',
                        'touchTargets.forEach(target => {',
                        '    const rect = target.getBoundingClientRect();',
                        '    if (rect.width < 44 || rect.height < 44) {',
                        '        allValid = false;',
                        '    }',
                        '});',
                        'return allValid;'
                    ]);
            
            $result = $browser->driver->executeScript('
                const touchTargets = document.querySelectorAll(".touch-target");
                let allValid = true;
                touchTargets.forEach(target => {
                    const rect = target.getBoundingClientRect();
                    if (rect.width < 44 || rect.height < 44) {
                        allValid = false;
                    }
                });
                return allValid;
            ');
            
            $this->assertTrue($result, 'All touch targets should be at least 44x44 pixels');
        });
    }

    /**
     * Test mobile preview modal functionality
     */
    public function test_mobile_preview_modal()
    {
        $this->browse(function (Browser $browser) {
            // First simulate having a workspace
            $browser->visit('/mobile/chat')
                    ->script([
                        'mobileChatInterface.currentWorkspace = {',
                        '    id: "test-workspace",',
                        '    name: "Test Game",',
                        '    preview_url: "https://example.com/preview"',
                        '};',
                        'mobileChatInterface.updateWorkspaceUI();'
                    ])
                    ->waitFor('#mobile-workspace-actions')
                    ->click('#mobile-preview-btn')
                    ->waitFor('#mobile-preview-modal')
                    ->assertSee('Game Preview')
                    ->assertPresent('#mobile-preview-frame')
                    ->click('#mobile-preview-close')
                    ->waitUntilMissing('#mobile-preview-modal');
        });
    }

    /**
     * Test credit badge updates
     */
    public function test_mobile_credit_badge_updates()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->waitFor('#mobile-credit-badge')
                    ->assertPresent('#mobile-credit-amount')
                    // Test that the badge shows some content (even if "Loading...")
                    ->assertDontSee('') // Badge should not be empty
                    ->assertPresent('#mobile-credit-badge.bg-green-900, #mobile-credit-badge.bg-yellow-900, #mobile-credit-badge.bg-red-900');
        });
    }

    /**
     * Test mobile typing indicator
     */
    public function test_mobile_typing_indicator()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->assertMissing('#mobile-typing-indicator')
                    ->script('mobileChatInterface.showTypingIndicator()')
                    ->waitFor('#mobile-typing-indicator')
                    ->assertSee('AI is thinking...')
                    ->script('mobileChatInterface.hideTypingIndicator()')
                    ->waitUntilMissing('#mobile-typing-indicator');
        });
    }

    /**
     * Test mobile message bubbles styling
     */
    public function test_mobile_message_bubbles()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->script('mobileChatInterface.addMessage("Test user message", "user")')
                    ->script('mobileChatInterface.addMessage("Test AI response", "ai")')
                    ->waitFor('.mobile-message-bubble')
                    ->assertPresent('.user-message')
                    ->assertPresent('.ai-message')
                    ->assertSee('Test user message')
                    ->assertSee('Test AI response');
        });
    }

    /**
     * Test PWA manifest and mobile optimization
     */
    public function test_pwa_manifest_exists()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/mobile/chat')
                    ->assertSourceHas('manifest.json')
                    ->assertSourceHas('viewport')
                    ->assertSourceHas('theme-color')
                    ->assertSourceHas('apple-mobile-web-app-capable');
        });
    }
}