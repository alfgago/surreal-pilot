<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create([
        'email' => 'owner@test.com',
        'name' => 'Company Owner',
    ]);
    
    $this->company = Company::factory()->create([
        'user_id' => $this->owner->id,
        'name' => 'Test Company',
        'plan' => 'pro',
        'credits' => 1000,
        'monthly_credit_limit' => 5000,
    ]);
    
    $this->owner->update(['current_company_id' => $this->company->id]);
    
    $this->member = User::factory()->create([
        'email' => 'member@test.com',
        'name' => 'Team Member',
        'current_company_id' => $this->company->id,
    ]);
    
    // Attach users to company with roles
    $this->company->users()->attach([
        $this->owner->id => ['role' => 'owner'],
        $this->member->id => ['role' => 'member'],
    ]);
});

test('company owner can view company settings', function () {
    $response = $this->actingAs($this->owner)
        ->get('/company/settings');
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Company/Settings')
            ->has('company')
            ->where('company.name', 'Test Company')
            ->where('company.is_owner', true)
            ->has('company.users', 2)
    );
});

test('company member can view but not edit company settings', function () {
    $this->member->update(['current_company_id' => $this->company->id]);
    
    $response = $this->actingAs($this->member)
        ->get('/company/settings');
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Company/Settings')
            ->where('company.is_owner', false)
    );
});

test('company owner can update company settings', function () {
    $response = $this->actingAs($this->owner)
        ->patch('/company/settings', [
            'name' => 'Updated Company Name',
            'plan' => 'enterprise',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Company settings updated successfully');
    
    $this->company->refresh();
    expect($this->company->name)->toBe('Updated Company Name');
    expect($this->company->plan)->toBe('enterprise');
});

test('company member cannot update company settings', function () {
    $this->member->update(['current_company_id' => $this->company->id]);
    
    $response = $this->actingAs($this->member)
        ->patch('/company/settings', [
            'name' => 'Hacked Company Name',
        ]);
    
    $response->assertStatus(403);
});

test('company owner can invite new team members', function () {
    $response = $this->actingAs($this->owner)
        ->post('/company/invite', [
            'email' => 'newmember@test.com',
            'role' => 'admin',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Invitation sent successfully');
    
    $this->assertDatabaseHas('company_invitations', [
        'company_id' => $this->company->id,
        'email' => 'newmember@test.com',
        'role' => 'admin',
    ]);
});

test('company owner can invite existing users directly', function () {
    $existingUser = User::factory()->create(['email' => 'existing@test.com']);
    
    $response = $this->actingAs($this->owner)
        ->post('/company/invite', [
            'email' => 'existing@test.com',
            'role' => 'member',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'User added to company successfully');
    
    $this->assertTrue($this->company->users()->where('user_id', $existingUser->id)->exists());
    $this->assertDatabaseMissing('company_invitations', [
        'email' => 'existing@test.com',
    ]);
});

test('cannot invite user who is already a member', function () {
    $response = $this->actingAs($this->owner)
        ->post('/company/invite', [
            'email' => 'member@test.com',
            'role' => 'admin',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHasErrors(['email' => 'This user is already a member of the company']);
});

test('cannot send duplicate invitations', function () {
    $this->company->invitations()->create([
        'email' => 'pending@test.com',
        'role' => 'member',
    ]);
    
    $response = $this->actingAs($this->owner)
        ->post('/company/invite', [
            'email' => 'pending@test.com',
            'role' => 'admin',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHasErrors(['email' => 'An invitation has already been sent to this email']);
});

test('company member cannot invite users', function () {
    $this->member->update(['current_company_id' => $this->company->id]);
    
    $response = $this->actingAs($this->member)
        ->post('/company/invite', [
            'email' => 'newmember@test.com',
            'role' => 'member',
        ]);
    
    $response->assertStatus(403);
});

test('company owner can remove team members', function () {
    $response = $this->actingAs($this->owner)
        ->delete("/company/users/{$this->member->id}");
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'User removed from company successfully');
    
    $this->assertFalse($this->company->users()->where('user_id', $this->member->id)->exists());
});

test('cannot remove company owner', function () {
    $response = $this->actingAs($this->owner)
        ->delete("/company/users/{$this->owner->id}");
    
    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'Cannot remove the company owner']);
});

test('company owner can cancel invitations', function () {
    $invitation = $this->company->invitations()->create([
        'email' => 'pending@test.com',
        'role' => 'member',
    ]);
    
    $response = $this->actingAs($this->owner)
        ->delete("/company/invitations/{$invitation->id}");
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Invitation canceled successfully');
    
    $this->assertDatabaseMissing('company_invitations', [
        'id' => $invitation->id,
    ]);
});

test('company owner can update user roles', function () {
    $response = $this->actingAs($this->owner)
        ->patch("/company/users/{$this->member->id}/role", [
            'role' => 'admin',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'User role updated successfully');
    
    // Check the role was updated in the database
    $this->assertDatabaseHas('company_user', [
        'company_id' => $this->company->id,
        'user_id' => $this->member->id,
        'role' => 'admin',
    ]);
});

test('cannot change owner role', function () {
    $response = $this->actingAs($this->owner)
        ->patch("/company/users/{$this->owner->id}/role", [
            'role' => 'member',
        ]);
    
    $response->assertRedirect();
    $response->assertSessionHasErrors(['error' => 'Cannot change the company owner role']);
});

test('company owner can update preferences', function () {
    $preferences = [
        'timezone' => 'America/New_York',
        'default_engine' => 'unreal',
        'auto_save' => true,
        'notifications_enabled' => false,
        'collaboration_enabled' => true,
        'public_templates' => false,
        'description' => 'A test company',
        'website' => 'https://test.com',
    ];
    
    $response = $this->actingAs($this->owner)
        ->patch('/company/preferences', $preferences);
    
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Company preferences updated successfully');
    
    $this->company->refresh();
    expect($this->company->preferences)->toEqual($preferences);
});

test('company member cannot update preferences', function () {
    $this->member->update(['current_company_id' => $this->company->id]);
    
    $response = $this->actingAs($this->member)
        ->patch('/company/preferences', [
            'timezone' => 'UTC',
            'default_engine' => 'playcanvas',
        ]);
    
    $response->assertStatus(403);
});

test('validates preference data correctly', function () {
    $response = $this->actingAs($this->owner)
        ->patch('/company/preferences', [
            'timezone' => '', // required
            'default_engine' => 'invalid', // must be playcanvas or unreal
            'website' => 'not-a-url', // must be valid URL
            'description' => str_repeat('a', 501), // max 500 chars
        ]);
    
    $response->assertSessionHasErrors([
        'timezone',
        'default_engine',
        'website',
        'description',
    ]);
});

test('user without current company cannot access company settings', function () {
    $userWithoutCompany = User::factory()->create();
    
    $response = $this->actingAs($userWithoutCompany)
        ->get('/company/settings');
    
    $response->assertStatus(404);
});