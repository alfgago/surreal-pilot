<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
});

test('user can access settings page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="settings-page"]', 10)
            ->assertPresent('[data-testid="settings-page"]')
            ->assertPresent('[data-testid="profile-settings"]')
            ->assertPresent('[data-testid="api-keys-settings"]')
            ->assertSee('Settings');
    });
});

test('user can access profile page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="profile-page"]', 10)
            ->assertPresent('[data-testid="profile-page"]')
            ->assertPresent('[data-testid="profile-form"]')
            ->assertSee('Profile Settings');
    });
});

test('user can update profile information', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="profile-form"]', 10)
            ->clear('name')
            ->type('name', 'Updated Test Name')
            ->clear('email')
            ->type('email', 'updated@test.com')
            ->press('Update Profile')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Profile updated');
    });
    
    // Verify the update
    $this->testUser->refresh();
    expect($this->testUser->name)->toBe('Updated Test Name');
    expect($this->testUser->email)->toBe('updated@test.com');
    
    // Reset for other tests
    $this->testUser->update([
        'name' => 'Alfredo Test',
        'email' => 'alfredo@5e.cr',
    ]);
});

test('user can change password', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="password-form"]', 10)
            ->type('current_password', 'Test123!')
            ->type('password', 'NewPassword123!')
            ->type('password_confirmation', 'NewPassword123!')
            ->press('Update Password')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Password updated');
    });
    
    // Test login with new password
    $this->browse(function (Browser $browser) {
        logoutUser($browser);
        
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'alfredo@5e.cr')
            ->type('password', 'NewPassword123!')
            ->press('Sign in')
            ->waitForLocation('/engine-selection', 15)
            ->assertPathIs('/engine-selection');
    });
    
    // Reset password for other tests
    $this->testUser->update(['password' => bcrypt('Test123!')]);
});

test('password change validates current password', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="password-form"]', 10)
            ->type('current_password', 'WrongPassword!')
            ->type('password', 'NewPassword123!')
            ->type('password_confirmation', 'NewPassword123!')
            ->press('Update Password')
            ->waitFor('[data-testid="error-message"]', 10)
            ->assertSee('current password is incorrect');
    });
});

test('user can manage API keys', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="api-keys-settings"]', 10)
            ->assertPresent('[data-testid="api-keys-settings"]')
            ->assertSee('API Keys');
    });
});

test('user can update OpenAI API key', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="api-keys-form"]', 10)
            ->type('openai_api_key', 'sk-test-openai-key-123')
            ->press('Save API Keys')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('API keys updated');
    });
});

test('user can update Anthropic API key', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="api-keys-form"]', 10)
            ->type('anthropic_api_key', 'sk-ant-test-key-123')
            ->press('Save API Keys')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('API keys updated');
    });
});

test('user can clear API keys', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="api-keys-form"]', 10)
            ->clear('openai_api_key')
            ->clear('anthropic_api_key')
            ->press('Save API Keys')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('API keys updated');
    });
});

test('settings page shows user preferences', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="preferences-section"]', 10)
            ->assertPresent('[data-testid="preferences-section"]')
            ->assertSee('Preferences');
    });
});

test('user can update notification preferences', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="preferences-form"]', 10)
            ->check('email_notifications')
            ->uncheck('browser_notifications')
            ->press('Save Preferences')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Preferences updated');
    });
});

test('user can update theme preference', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings')
            ->waitFor('[data-testid="theme-selector"]', 10)
            ->select('theme', 'dark')
            ->press('Save Preferences')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Preferences updated');
    });
});

test('user can delete account', function () {
    // Create a temporary user for deletion test
    $tempUser = User::factory()->create([
        'email' => 'delete@test.com',
        'password' => bcrypt('password'),
    ]);
    
    $this->browse(function (Browser $browser) use ($tempUser) {
        // Login as temp user
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'delete@test.com')
            ->type('password', 'password')
            ->press('Sign in')
            ->waitForLocation('/engine-selection', 15);
            
        // Navigate to settings and delete account
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="danger-zone"]', 10)
            ->click('[data-testid="delete-account-button"]')
            ->waitFor('[data-testid="confirm-modal"]', 10)
            ->assertSee('Delete Account')
            ->type('password', 'password')
            ->press('Delete Account')
            ->waitForLocation('/', 15)
            ->assertPathIs('/');
    });
    
    // Verify user was deleted
    expect(User::where('email', 'delete@test.com')->exists())->toBeFalse();
});

test('account deletion requires password confirmation', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/settings/profile')
            ->waitFor('[data-testid="danger-zone"]', 10)
            ->click('[data-testid="delete-account-button"]')
            ->waitFor('[data-testid="confirm-modal"]', 10)
            ->type('password', 'WrongPassword!')
            ->press('Delete Account')
            ->waitFor('[data-testid="error-message"]', 10)
            ->assertSee('password is incorrect');
            
        // Close modal
        $browser->click('[data-testid="close-modal"]');
    });
});

test('settings page is responsive on mobile', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        testMobileView($browser, function ($browser) {
            $browser->visit('/settings')
                ->waitFor('[data-testid="settings-page"]', 10)
                ->assertPresent('[data-testid="settings-page"]')
                ->assertPresent('[data-testid="profile-settings"]');
        });
    });
});

test('profile page is responsive on mobile', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        testMobileView($browser, function ($browser) {
            $browser->visit('/settings/profile')
                ->waitFor('[data-testid="profile-page"]', 10)
                ->assertPresent('[data-testid="profile-page"]')
                ->assertPresent('[data-testid="profile-form"]');
        });
    });
});