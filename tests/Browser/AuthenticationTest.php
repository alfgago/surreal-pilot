<?php

use App\Models\User;
use Laravel\Dusk\Browser;

test('user can view login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->assertPresent('input[name="email"]')
            ->assertPresent('input[name="password"]')
            ->assertSee('Sign in');
    });
});

test('user can view register page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->waitFor('form', 10)
            ->assertPresent('input[name="name"]')
            ->assertPresent('input[name="email"]')
            ->assertPresent('input[name="password"]')
            ->assertPresent('input[name="password_confirmation"]')
            ->assertSee('Create account');
    });
});

test('user can login with valid credentials', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'alfredo@5e.cr')
            ->type('password', 'Test123!')
            ->press('Sign in')
            ->waitForLocation('/engine-selection', 15)
            ->assertPathIs('/engine-selection');
    });
});

test('user cannot login with invalid credentials', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'alfredo@5e.cr')
            ->type('password', 'wrongpassword')
            ->press('Sign in')
            ->waitFor('.text-red-500', 10) // Wait for error message
            ->assertSee('credentials do not match');
    });
});

test('user can register new account', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->waitFor('form', 10)
            ->type('name', 'New Test User')
            ->type('email', 'newuser@test.com')
            ->type('password', 'Password123!')
            ->type('password_confirmation', 'Password123!')
            ->press('Create account')
            ->waitForLocation('/engine-selection', 15)
            ->assertPathIs('/engine-selection');
    });

    // Verify user was created
    $user = User::where('email', 'newuser@test.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New Test User');
    expect($user->currentCompany)->not->toBeNull();
});

test('registration validates required fields', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->waitFor('form', 10)
            ->press('Create account')
            ->waitFor('.text-red-500', 10)
            ->assertSee('required');
    });
});

test('user can logout', function () {
    $this->browse(function (Browser $browser) {
        // First login
        $browser->visit('/login')
            ->waitFor('form', 10)
            ->type('email', 'alfredo@5e.cr')
            ->type('password', 'Test123!')
            ->press('Sign in')
            ->waitForLocation('/engine-selection', 15);
            
        // Then logout
        $browser->click('[data-testid="user-menu-trigger"]')
            ->waitFor('[data-testid="logout-button"]', 5)
            ->click('[data-testid="logout-button"]')
            ->waitForLocation('/', 15)
            ->assertPathIs('/');
    });
});