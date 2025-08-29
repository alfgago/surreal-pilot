<?php

use Tests\Visual\VisualTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Company;

uses(VisualTestCase::class);

describe('Comprehensive Application Flow', function () {
    beforeEach(function () {
        $this->artisan('migrate:fresh --seed');
    });
    
    test('complete user journey from landing to chat', function () {
        $this->browse(function (Browser $browser) {
            // 1. Landing Page Test
            $browser->visit('/')
                   ->waitForReactApp()
                   ->takeScreenshot('01_landing_page', 'Initial landing page for unauthenticated users')
                   ->assertSee('SurrealPilot')
                   ->testResponsiveBreakpoints('landing_page');
            
            // 2. Registration Flow
            $browser->clickLink('Register')
                   ->waitForInertiaPage()
                   ->takeScreenshot('02_register_page', 'User registration form')
                   ->type('name', 'Test User')
                   ->type('email', 'test@example.com')
                   ->type('password', 'password123')
                   ->type('password_confirmation', 'password123')
                   ->type('company_name', 'Test Company')
                   ->takeScreenshot('03_register_filled', 'Registration form filled out')
                   ->press('Register')
                   ->waitForInertiaPage();
            
            // 3. Engine Selection
            $browser->waitFor('[data-testid="engine-selection"]', 10)
                   ->takeScreenshot('04_engine_selection', 'Engine selection page after registration')
                   ->testResponsiveBreakpoints('engine_selection')
                   ->click('[data-testid="playcanvas-option"]')
                   ->takeScreenshot('05_engine_selected', 'PlayCanvas engine selected')
                   ->press('Continue')
                   ->waitForInertiaPage();
            
            // 4. Workspace Selection
            $browser->waitFor('[data-testid="workspace-selection"]', 10)
                   ->takeScreenshot('06_workspace_selection', 'Workspace selection page')
                   ->testResponsiveBreakpoints('workspace_selection')
                   ->press('Create New Workspace')
                   ->waitFor('[data-testid="workspace-form"]')
                   ->takeScreenshot('07_workspace_form', 'New workspace creation form')
                   ->type('name', 'My First Game')
                   ->type('description', 'A test game project')
                   ->select('template', 'basic-3d')
                   ->takeScreenshot('08_workspace_form_filled', 'Workspace form completed')
                   ->press('Create Workspace')
                   ->waitForInertiaPage();
            
            // 5. Chat Interface
            $browser->waitFor('[data-testid="chat-interface"]', 15)
                   ->takeScreenshot('09_chat_interface', 'Main chat interface loaded')
                   ->testResponsiveBreakpoints('chat_interface')
                   ->assertSee('Welcome to SurrealPilot')
                   ->type('[data-testid="message-input"]', 'Hello, can you help me create a simple cube?')
                   ->takeScreenshot('10_chat_message_typed', 'User message typed in chat')
                   ->press('Send')
                   ->waitFor('[data-testid="ai-response"]', 30)
                   ->takeScreenshot('11_chat_response', 'AI response received')
                   ->takeFullPageScreenshot('12_chat_full_conversation', 'Complete chat conversation view');
            
            // 6. Navigation Testing
            $browser->click('[data-testid="nav-games"]')
                   ->waitForInertiaPage()
                   ->takeScreenshot('13_games_page', 'Games management page')
                   ->testResponsiveBreakpoints('games_page')
                   ->click('[data-testid="nav-settings"]')
                   ->waitForInertiaPage()
                   ->takeScreenshot('14_settings_page', 'User settings page')
                   ->testResponsiveBreakpoints('settings_page');
            
            // 7. Profile Management
            $browser->click('[data-testid="nav-profile"]')
                   ->waitForInertiaPage()
                   ->takeScreenshot('15_profile_page', 'User profile page')
                   ->type('name', 'Updated Test User')
                   ->takeScreenshot('16_profile_updated', 'Profile form with updated information')
                   ->press('Save Changes')
                   ->waitFor('[data-testid="success-message"]')
                   ->takeScreenshot('17_profile_saved', 'Profile successfully updated');
            
            // 8. Company Settings
            $browser->visit('/company/settings')
                   ->waitForInertiaPage()
                   ->takeScreenshot('18_company_settings', 'Company settings page')
                   ->testResponsiveBreakpoints('company_settings');
            
            // 9. Billing Page
            $browser->visit('/company/billing')
                   ->waitForInertiaPage()
                   ->takeScreenshot('19_billing_page', 'Company billing page')
                   ->testResponsiveBreakpoints('billing_page');
            
            // 10. Mobile-specific Testing
            $browser->resize(375, 667) // iPhone SE size
                   ->visit('/mobile/chat')
                   ->waitForReactApp()
                   ->takeScreenshot('20_mobile_chat', 'Mobile-optimized chat interface')
                   ->visit('/mobile/tutorials')
                   ->waitForReactApp()
                   ->takeScreenshot('21_mobile_tutorials', 'Mobile tutorials page');
        });
    });
    
    test('error handling and edge cases', function () {
        $this->browse(function (Browser $browser) {
            // Test 404 page
            $browser->visit('/nonexistent-page')
                   ->takeScreenshot('error_404', '404 error page')
                   ->assertSee('404');
            
            // Test unauthorized access
            $browser->visit('/chat')
                   ->takeScreenshot('error_unauthorized', 'Unauthorized access redirect')
                   ->assertPathIs('/login');
            
            // Test invalid login
            $browser->visit('/login')
                   ->waitForInertiaPage()
                   ->type('email', 'invalid@example.com')
                   ->type('password', 'wrongpassword')
                   ->takeScreenshot('error_invalid_login', 'Invalid login attempt')
                   ->press('Login')
                   ->waitFor('[data-testid="error-message"]')
                   ->takeScreenshot('error_login_failed', 'Login error message displayed');
        });
    });
    
    test('accessibility and performance', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/dashboard')
                   ->waitForInertiaPage()
                   ->takeScreenshot('accessibility_dashboard', 'Dashboard for accessibility testing');
            
            // Test keyboard navigation
            $browser->keys('body', ['{tab}', '{tab}', '{tab}'])
                   ->takeScreenshot('accessibility_keyboard_nav', 'Keyboard navigation focus states');
            
            // Test high contrast mode (simulate)
            $browser->script('document.body.style.filter = "contrast(200%)"')
                   ->takeScreenshot('accessibility_high_contrast', 'High contrast mode simulation');
        });
    });
});