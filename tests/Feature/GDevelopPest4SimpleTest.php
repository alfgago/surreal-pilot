<?php

test('basic test works', function () {
    expect(true)->toBeTrue();
});

test('gdevelop config can be read', function () {
    $enabled = config('gdevelop.enabled', false);
    expect($enabled)->toBeBool();
});

test('can create user and company', function () {
    $company = \App\Models\Company::factory()->create(['name' => 'Test Company']);
    $user = \App\Models\User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@simple.com'
    ]);
    
    expect($user->email)->toBe('test@simple.com');
    expect($company->name)->toBe('Test Company');
});

test('can make http request to homepage', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

test('can access engine selection page', function () {
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['current_company_id' => $company->id]);
    $user->companies()->attach($company->id);
    
    $response = $this->actingAs($user)->get('/engine-selection');
    $response->assertOk();
    $response->assertSee('Choose Your Game Engine');
});