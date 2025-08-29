<?php

use App\Models\User;
use App\Models\Company;

test('user can view profile page', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test-profile@example.com',
    ]);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Settings/Profile')
             ->has('user')
             ->has('stats')
             ->has('timezones')
    );
});

test('user can update profile information', function () {
    $user = User::factory()->create([
        'email' => 'original-profile@example.com',
    ]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'email' => 'updated-profile@example.com',
        'bio' => 'Updated bio',
        'timezone' => 'America/New_York',
        'email_notifications' => true,
        'browser_notifications' => false,
    ]);

    $response->assertRedirect('/profile');
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated-profile@example.com');
    expect($user->bio)->toBe('Updated bio');
    expect($user->timezone)->toBe('America/New_York');
});

test('user can view settings page', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id]);
    $user->update(['current_company_id' => $company->id]);

    $response = $this->actingAs($user)->get('/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Settings/Index')
             ->has('providers')
             ->has('preferences')
             ->has('apiKeyStatus')
             ->has('isCompanyOwner')
    );
});

test('user can update settings preferences', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings', [
        'ai' => [
            'default_provider' => 'openai',
            'temperature' => 0.8,
            'stream_responses' => false,
            'save_history' => true,
        ],
        'ui' => [
            'theme' => 'dark',
            'compact_mode' => true,
            'show_line_numbers' => false,
        ],
        'notifications' => [
            'email' => true,
            'browser' => false,
            'chat_mentions' => true,
            'game_updates' => false,
        ],
    ]);

    $response->assertRedirect('/settings');
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->preferences['ai']['default_provider'])->toBe('openai');
    expect($user->preferences['ai']['temperature'])->toBe(0.8);
    expect($user->preferences['ui']['theme'])->toBe('dark');
});

test('company owner can update api keys', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id]);
    $user->update(['current_company_id' => $company->id]);

    $response = $this->actingAs($user)->patch('/settings/api-keys', [
        'openai_api_key' => 'sk-test1234567890123456789012345678901234567890123456',
        'anthropic_api_key' => 'sk-ant-api03_test123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890',
        'playcanvas_api_key' => 'pc-test-key',
        'playcanvas_project_id' => '123456',
    ]);

    $response->assertRedirect('/settings');
    $response->assertSessionHas('success');

    $company->refresh();
    expect($company->openai_api_key_enc)->not->toBeNull();
    expect($company->anthropic_api_key_enc)->not->toBeNull();
    expect($company->playcanvas_api_key)->toBe('pc-test-key');
    expect($company->playcanvas_project_id)->toBe('123456');
});

test('non-owner cannot update api keys', function () {
    $owner = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $owner->id]);
    $user = User::factory()->create(['current_company_id' => $company->id]);

    $response = $this->actingAs($user)->patch('/settings/api-keys', [
        'openai_api_key' => 'sk-test1234567890123456789012345678901234567890123456',
    ]);

    $response->assertStatus(403);
});

test('company owner can remove api keys', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create([
        'user_id' => $user->id,
        'openai_api_key_enc' => encrypt('test-key'),
    ]);
    $user->update(['current_company_id' => $company->id]);

    $response = $this->actingAs($user)->delete('/settings/api-keys/openai');

    $response->assertRedirect('/settings');
    $response->assertSessionHas('success');

    $company->refresh();
    expect($company->openai_api_key_enc)->toBeNull();
});

test('profile validation works correctly', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => '', // Required field
        'email' => 'invalid-email', // Invalid email
        'bio' => str_repeat('a', 501), // Too long
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'bio']);
});

test('settings validation works correctly', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings', [
        'ai' => [
            'default_provider' => 'invalid-provider',
            'temperature' => 2.0, // Out of range
        ],
    ]);

    $response->assertSessionHasErrors(['ai.default_provider', 'ai.temperature']);
});