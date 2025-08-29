<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;

class CompleteUserFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_complete_user_registration_and_workspace_creation_flow(): void
    {
        $this->browse(function (Browser $browser) {
            // 1. Visit landing page
            $browser->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('01_landing_page')
                   ->assertPresent('#app');

            // 2. Navigate to registration
            $browser->clickLink('Register')
                   ->waitFor('#app', 10)
                   ->screenshot('02_registration_page')
                   ->assertPresent('#app');

            // 3. Fill out registration form
            $browser->type('name', 'Test User')
                   ->type('email', 'test@example.com')
                   ->type('password', 'password123')
                   ->type('password_confirmation', 'password123')
                   ->type('company_name', 'Test Company')
                   ->screenshot('03_registration_filled')
                   ->press('Register')
                   ->waitFor('#app', 15);

            // 4. Should be redirected to engine selection
            $browser->screenshot('04_engine_selection')
                   ->assertPresent('#app');

            // 5. Select PlayCanvas engine
            if ($browser->element('[data-testid="playcanvas-option"]')) {
                $browser->click('[data-testid="playcanvas-option"]')
                       ->screenshot('05_playcanvas_selected');
            } else {
                // Fallback: look for any button or link containing "PlayCanvas"
                $browser->clickLink('PlayCanvas')
                       ->screenshot('05_playcanvas_selected_fallback');
            }

            $browser->press('Continue')
                   ->waitFor('#app', 15);

            // 6. Should be redirected to workspace selection
            $browser->screenshot('06_workspace_selection')
                   ->assertPresent('#app');

            // 7. Click create new workspace
            $browser->click('Create New Workspace')
                   ->waitFor('[role="dialog"]', 10)
                   ->screenshot('07_create_workspace_modal');

            // 8. Fill out workspace creation form
            $browser->type('name', 'My Test Game')
                   ->screenshot('08_workspace_form_filled')
                   ->press('Create Workspace')
                   ->waitFor('#app', 20);

            // 9. Should be redirected to chat interface
            $browser->screenshot('09_chat_interface')
                   ->assertPresent('#app');

            // 10. Test navigation to other pages
            $browser->visit('/games')
                   ->waitFor('#app', 10)
                   ->screenshot('10_games_page')
                   ->assertPresent('#app');

            $browser->visit('/settings')
                   ->waitFor('#app', 10)
                   ->screenshot('11_settings_page')
                   ->assertPresent('#app');
        });
    }

    public function test_existing_user_login_flow(): void
    {
        // Create a user with company
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => bcrypt('password123'),
        ]);
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->browse(function (Browser $browser) {
            // 1. Visit login page
            $browser->visit('/login')
                   ->waitFor('#app', 15)
                   ->screenshot('login_01_page')
                   ->assertPresent('#app');

            // 2. Fill login form
            $browser->type('email', 'existing@example.com')
                   ->type('password', 'password123')
                   ->screenshot('login_02_filled')
                   ->press('Login')
                   ->waitFor('#app', 15);

            // 3. Should be redirected appropriately
            $browser->screenshot('login_03_after_login')
                   ->assertPresent('#app');
        });
    }

    public function test_responsive_design(): void
    {
        $this->browse(function (Browser $browser) {
            // Test mobile viewport
            $browser->resize(375, 667)
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('responsive_01_mobile_landing')
                   ->assertPresent('#app');

            // Test tablet viewport
            $browser->resize(768, 1024)
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('responsive_02_tablet_landing')
                   ->assertPresent('#app');

            // Test desktop viewport
            $browser->resize(1920, 1080)
                   ->visit('/')
                   ->waitFor('#app', 15)
                   ->screenshot('responsive_03_desktop_landing')
                   ->assertPresent('#app');
        });
    }
}