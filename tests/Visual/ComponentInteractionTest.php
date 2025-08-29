<?php

use Tests\Visual\VisualTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;

uses(VisualTestCase::class);

describe('Component Interaction Testing', function () {
    beforeEach(function () {
        $this->artisan('migrate:fresh --seed');
    });
    
    test('chat interface interactions', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        // Create a workspace for the user
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);
        
        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                   ->visit('/chat')
                   ->with('selected_workspace_id', $workspace->id)
                   ->waitForInertiaPage()
                   ->takeScreenshot('chat_01_initial', 'Chat interface initial state');
            
            // Test message input interactions
            $browser->click('[data-testid="message-input"]')
                   ->takeScreenshot('chat_02_input_focused', 'Message input field focused')
                   ->type('[data-testid="message-input"]', 'Create a rotating cube')
                   ->takeScreenshot('chat_03_message_typed', 'Message typed in input field');
            
            // Test send button states
            $browser->assertEnabled('[data-testid="send-button"]')
                   ->takeScreenshot('chat_04_send_enabled', 'Send button enabled with message')
                   ->press('[data-testid="send-button"]')
                   ->waitFor('[data-testid="user-message"]')
                   ->takeScreenshot('chat_05_message_sent', 'User message displayed in chat');
            
            // Test chat settings modal
            if ($browser->element('[data-testid="chat-settings-button"]')) {
                $browser->click('[data-testid="chat-settings-button"]')
                       ->waitFor('[data-testid="chat-settings-modal"]')
                       ->takeScreenshot('chat_06_settings_modal', 'Chat settings modal opened')
                       ->testResponsiveBreakpoints('chat_settings_modal');
                
                // Test settings interactions
                $browser->click('[data-testid="model-selector"]')
                       ->takeScreenshot('chat_07_model_dropdown', 'AI model selection dropdown')
                       ->click('[data-testid="temperature-slider"]')
                       ->takeScreenshot('chat_08_temperature_slider', 'Temperature slider interaction')
                       ->press('Save Settings')
                       ->waitUntilMissing('[data-testid="chat-settings-modal"]')
                       ->takeScreenshot('chat_09_settings_saved', 'Settings saved and modal closed');
            }
            
            // Test conversation sidebar
            if ($browser->element('[data-testid="conversation-sidebar"]')) {
                $browser->click('[data-testid="new-conversation"]')
                       ->takeScreenshot('chat_10_new_conversation', 'New conversation created')
                       ->click('[data-testid="conversation-history"]')
                       ->takeScreenshot('chat_11_conversation_history', 'Conversation history displayed');
            }
        });
    });
    
    test('games management interactions', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/games')
                   ->waitForInertiaPage()
                   ->takeScreenshot('games_01_index', 'Games index page');
            
            // Test create new game
            $browser->click('[data-testid="create-game-button"]')
                   ->waitForInertiaPage()
                   ->takeScreenshot('games_02_create_form', 'Create game form')
                   ->type('name', 'Test Game')
                   ->type('description', 'A test game for visual testing')
                   ->takeScreenshot('games_03_form_filled', 'Game creation form filled')
                   ->press('Create Game')
                   ->waitForInertiaPage()
                   ->takeScreenshot('games_04_game_created', 'Game successfully created');
            
            // Test game card interactions
            if ($browser->element('[data-testid="game-card"]')) {
                $browser->hover('[data-testid="game-card"]')
                       ->takeScreenshot('games_05_card_hover', 'Game card hover state')
                       ->click('[data-testid="game-card"]')
                       ->waitForInertiaPage()
                       ->takeScreenshot('games_06_game_detail', 'Game detail page');
                
                // Test game actions
                $browser->click('[data-testid="play-game"]')
                       ->waitFor('[data-testid="game-player"]', 10)
                       ->takeScreenshot('games_07_game_playing', 'Game player interface')
                       ->takeFullPageScreenshot('games_08_game_fullscreen', 'Full game player view');
            }
        });
    });
    
    test('workspace and engine selection interactions', function () {
        $this->browse(function (Browser $browser) {
            // Create a user and login
            $user = User::factory()->create();
            $company = Company::factory()->create();
            $user->companies()->attach($company->id, ['role' => 'admin']);
            
            $browser->loginAs($user)
                   ->visit('/engine-selection')
                   ->waitForInertiaPage()
                   ->takeScreenshot('engine_01_selection', 'Engine selection page');
            
            // Test engine option interactions
            $browser->hover('[data-testid="unreal-option"]')
                   ->takeScreenshot('engine_02_unreal_hover', 'Unreal Engine option hover state')
                   ->hover('[data-testid="playcanvas-option"]')
                   ->takeScreenshot('engine_03_playcanvas_hover', 'PlayCanvas option hover state')
                   ->click('[data-testid="playcanvas-option"]')
                   ->takeScreenshot('engine_04_playcanvas_selected', 'PlayCanvas selected')
                   ->press('Continue')
                   ->waitForInertiaPage();
            
            // Test workspace selection
            $browser->waitFor('[data-testid="workspace-selection"]')
                   ->takeScreenshot('workspace_01_selection', 'Workspace selection page')
                   ->testResponsiveBreakpoints('workspace_selection');
            
            // Test template selection
            $browser->click('[data-testid="template-basic-3d"]')
                   ->takeScreenshot('workspace_02_template_selected', 'Template selected')
                   ->type('workspace_name', 'Visual Test Workspace')
                   ->takeScreenshot('workspace_03_name_entered', 'Workspace name entered')
                   ->press('Create Workspace')
                   ->waitForInertiaPage()
                   ->takeScreenshot('workspace_04_created', 'Workspace successfully created');
        });
    });
    
    test('settings and profile interactions', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                   ->visit('/settings')
                   ->waitForInertiaPage()
                   ->takeScreenshot('settings_01_page', 'Settings page initial state');
            
            // Test profile settings
            $browser->click('[data-testid="profile-tab"]')
                   ->takeScreenshot('settings_02_profile_tab', 'Profile settings tab')
                   ->clear('name')
                   ->type('name', 'Updated Visual Test User')
                   ->takeScreenshot('settings_03_name_updated', 'Profile name updated')
                   ->press('Save Profile')
                   ->waitFor('[data-testid="success-message"]')
                   ->takeScreenshot('settings_04_profile_saved', 'Profile successfully saved');
            
            // Test API keys section
            $browser->click('[data-testid="api-keys-tab"]')
                   ->takeScreenshot('settings_05_api_keys_tab', 'API keys settings tab')
                   ->type('openai_api_key', 'sk-test-key-for-visual-testing')
                   ->takeScreenshot('settings_06_api_key_entered', 'API key entered')
                   ->press('Save API Keys')
                   ->waitFor('[data-testid="success-message"]')
                   ->takeScreenshot('settings_07_api_keys_saved', 'API keys saved');
            
            // Test company settings
            $browser->visit('/company/settings')
                   ->waitForInertiaPage()
                   ->takeScreenshot('company_01_settings', 'Company settings page')
                   ->type('company_name', 'Updated Visual Test Company')
                   ->takeScreenshot('company_02_name_updated', 'Company name updated')
                   ->press('Save Company Settings')
                   ->waitFor('[data-testid="success-message"]')
                   ->takeScreenshot('company_03_settings_saved', 'Company settings saved');
        });
    });
    
    test('mobile responsive interactions', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $this->browse(function (Browser $browser) use ($user) {
            // Test mobile navigation
            $browser->resize(375, 667) // iPhone SE
                   ->loginAs($user)
                   ->visit('/dashboard')
                   ->waitForInertiaPage()
                   ->takeScreenshot('mobile_01_dashboard', 'Mobile dashboard view');
            
            // Test mobile menu
            if ($browser->element('[data-testid="mobile-menu-button"]')) {
                $browser->click('[data-testid="mobile-menu-button"]')
                       ->takeScreenshot('mobile_02_menu_open', 'Mobile navigation menu opened')
                       ->click('[data-testid="mobile-nav-chat"]')
                       ->waitForInertiaPage()
                       ->takeScreenshot('mobile_03_chat_page', 'Mobile chat page');
            }
            
            // Test mobile chat interface
            $browser->visit('/mobile/chat')
                   ->waitForReactApp()
                   ->takeScreenshot('mobile_04_chat_interface', 'Mobile-optimized chat interface')
                   ->type('[data-testid="mobile-message-input"]', 'Test mobile message')
                   ->takeScreenshot('mobile_05_message_typed', 'Message typed on mobile')
                   ->press('[data-testid="mobile-send-button"]')
                   ->takeScreenshot('mobile_06_message_sent', 'Message sent on mobile');
        });
    });
});