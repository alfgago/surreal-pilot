<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AIThinkingDisplayBrowserTest extends DuskTestCase
{
    /**
     * Test that the AI thinking display component loads and functions correctly.
     */
    public function test_ai_thinking_display_component_functionality()
    {
        // Create a test user and company
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/ai-thinking-test')
                    ->waitFor('[data-testid="ai-thinking-test"]', 10)
                    ->assertSee('AI Thinking Display Test')
                    ->assertSee('Simulate AI Thinking')
                    ->click('button:contains("Simulate AI Thinking")')
                    ->waitFor('.animate-pulse', 5)
                    ->assertSee('Processing...')
                    ->waitFor('[data-testid="thinking-display"]', 10)
                    ->assertSee('AI Thinking Process')
                    ->assertSee('Initial Analysis')
                    ->pause(5000) // Wait for the full simulation
                    ->assertSee('Game Design Planning')
                    ->assertSee('Analyzing Tower Defense Game Request')
                    ->click('button:contains("Hide")')
                    ->assertDontSee('AI Thinking Process')
                    ->click('button:contains("Show")')
                    ->assertSee('AI Thinking Process');
        });
    }

    /**
     * Test mobile responsiveness of the AI thinking display.
     */
    public function test_ai_thinking_display_mobile_responsiveness()
    {
        // Create a test user and company
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->resize(375, 667) // iPhone SE dimensions
                    ->visit('/ai-thinking-test')
                    ->waitFor('[data-testid="ai-thinking-test"]', 10)
                    ->assertSee('AI Thinking Display Test')
                    ->click('button:contains("Simulate AI Thinking")')
                    ->waitFor('.animate-pulse', 5)
                    ->pause(5000) // Wait for simulation to complete
                    ->assertSee('AI Thinking Process')
                    ->assertVisible('[data-testid="thinking-display"]')
                    // Test that the component is responsive and doesn't overflow
                    ->assertScript('document.querySelector("[data-testid=thinking-display]").scrollWidth <= window.innerWidth');
        });
    }

    /**
     * Test expandable/collapsible thinking sections.
     */
    public function test_thinking_sections_expand_collapse()
    {
        // Create a test user and company
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/ai-thinking-test')
                    ->waitFor('[data-testid="ai-thinking-test"]', 10)
                    ->click('button:contains("Simulate AI Thinking")')
                    ->pause(5000) // Wait for simulation to complete
                    ->waitFor('[data-testid="thinking-display"]', 10)
                    // Test expand all functionality
                    ->click('button:contains("Expand All")')
                    ->pause(1000)
                    // Test collapse all functionality
                    ->click('button:contains("Collapse All")')
                    ->pause(1000)
                    // Test individual section toggle
                    ->click('button:contains("Initial Analysis")')
                    ->pause(500);
        });
    }
}