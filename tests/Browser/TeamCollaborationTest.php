<?php

use App\Models\User;
use App\Models\Company;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
    $this->testCompany = $this->testUser->currentCompany;
    
    // Create a team member for testing
    $this->teamMember = User::factory()->create([
        'name' => 'Team Member',
        'email' => 'member@test.com',
        'current_company_id' => $this->testCompany->id,
    ]);
    
    $this->testCompany->users()->attach($this->teamMember->id, ['role' => 'member']);
});

test('company owner can access company settings', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="company-settings"]', 10)
            ->assertPresent('[data-testid="company-settings"]')
            ->assertPresent('[data-testid="company-info"]')
            ->assertPresent('[data-testid="team-management"]')
            ->assertSee('Company Settings');
    });
});

test('company settings shows team members', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="team-management"]', 10)
            ->assertPresent('[data-testid="team-management"]')
            ->assertSee('Team Members')
            ->assertSee('Alfredo Test') // Owner
            ->assertSee('Team Member'); // Member we created
    });
});

test('company owner can invite new team members', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="team-management"]', 10)
            ->click('[data-testid="invite-member-button"]')
            ->waitFor('[data-testid="invite-modal"]', 10)
            ->assertPresent('[data-testid="invite-modal"]')
            ->type('email', 'newmember@test.com')
            ->select('role', 'member')
            ->press('Send Invitation')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Invitation sent');
    });
});

test('company owner can change team member roles', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="team-management"]', 10)
            ->click('[data-testid="member-' . $this->teamMember->id . '-role-button"]')
            ->waitFor('[data-testid="role-dropdown"]', 5)
            ->click('[data-testid="role-admin"]')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Role updated');
    });
});

test('company owner can remove team members', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="team-management"]', 10)
            ->click('[data-testid="member-' . $this->teamMember->id . '-remove-button"]')
            ->waitFor('[data-testid="confirm-modal"]', 10)
            ->assertPresent('[data-testid="confirm-modal"]')
            ->assertSee('Remove Team Member')
            ->press('Remove Member')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Member removed');
    });
});

test('company owner can update company preferences', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="company-preferences"]', 10)
            ->assertPresent('[data-testid="company-preferences"]')
            ->select('timezone', 'America/New_York')
            ->select('default_engine', 'unreal')
            ->check('collaboration_enabled')
            ->press('Save Preferences')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Preferences updated');
    });
});

test('team member can view but not edit company settings', function () {
    $this->browse(function (Browser $browser) {
        // Login as team member
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'member@test.com')
            ->type('password', 'password')
            ->press('Sign in')
            ->waitForLocation('/engine-selection', 15);
            
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="company-settings"]', 10)
            ->assertPresent('[data-testid="company-settings"]')
            ->assertMissing('[data-testid="invite-member-button"]') // Should not see invite button
            ->assertMissing('[data-testid="company-preferences"]'); // Should not see preferences
    });
});

test('workspace collaboration shows active users', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat (main workspace)
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->testUser->workspaces->first()->id . '"]')
            ->waitForLocation('/chat', 15)
            ->assertPresent('[data-testid="collaboration-status"]')
            ->assertSee('Active Users');
    });
});

test('multiplayer session page shows collaboration features', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to multiplayer page
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->testUser->workspaces->first()->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/multiplayer')
            ->waitFor('[data-testid="multiplayer-page"]', 10)
            ->assertPresent('[data-testid="multiplayer-page"]')
            ->assertSee('Multiplayer Sessions');
    });
});

test('user can create multiplayer session', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to multiplayer and create session
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->testUser->workspaces->first()->id . '"]')
            ->waitForLocation('/chat', 15)
            ->visit('/multiplayer')
            ->waitFor('[data-testid="create-session-button"]', 10)
            ->click('[data-testid="create-session-button"]')
            ->waitFor('[data-testid="session-modal"]', 10)
            ->type('name', 'Test Collaboration Session')
            ->press('Create Session')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Session created');
    });
});

test('company settings shows pending invitations', function () {
    // Create a pending invitation
    $this->testCompany->invitations()->create([
        'email' => 'pending@test.com',
        'role' => 'member',
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="pending-invitations"]', 10)
            ->assertPresent('[data-testid="pending-invitations"]')
            ->assertSee('Pending Invitations')
            ->assertSee('pending@test.com');
    });
});

test('company owner can cancel pending invitations', function () {
    // Create a pending invitation
    $invitation = $this->testCompany->invitations()->create([
        'email' => 'cancel@test.com',
        'role' => 'member',
    ]);
    
    $this->browse(function (Browser $browser) use ($invitation) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/settings')
            ->waitFor('[data-testid="pending-invitations"]', 10)
            ->click('[data-testid="invitation-' . $invitation->id . '-cancel"]')
            ->waitFor('[data-testid="success-message"]', 10)
            ->assertSee('Invitation canceled');
    });
});

test('team collaboration is responsive on mobile', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->resize(375, 667) // iPhone SE size
            ->visit('/company/settings')
            ->waitFor('[data-testid="company-settings"]', 10)
            ->assertPresent('[data-testid="company-settings"]')
            ->assertPresent('[data-testid="team-management"]')
            ->resize(1920, 1080); // Reset to desktop
    });
});