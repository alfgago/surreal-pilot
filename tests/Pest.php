<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->extend(Tests\DuskTestCase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Browser Test Helpers
|--------------------------------------------------------------------------
|
| Helper functions for browser testing with Laravel Dusk.
|
*/

/**
 * Get test user credentials.
 */
function testUserCredentials(): array
{
    return [
        'email' => 'alfredo@5e.cr',
        'password' => 'Test123!',
    ];
}

/**
 * Login as test user.
 */
function loginAsTestUser($browser)
{
    $credentials = testUserCredentials();
    
    return $browser->visit('/login')
        ->type('email', $credentials['email'])
        ->type('password', $credentials['password'])
        ->press('Sign in')
        ->waitForLocation('/engine-selection', 10);
}

/**
 * Wait for page to load completely.
 */
function waitForPageLoad($browser, $timeout = 10)
{
    return $browser->waitUntil('document.readyState === "complete"', $timeout);
}

/**
 * Assert no JavaScript errors on page.
 */
function assertNoJavaScriptErrors($browser)
{
    $logs = $browser->driver->manage()->getLog('browser');
    $errors = array_filter($logs, function ($log) {
        return $log['level'] === 'SEVERE';
    });
    
    expect($errors)->toBeEmpty('JavaScript errors found: ' . json_encode($errors));
}
/**
 * Helper function to navigate to a specific workspace
 */
function navigateToWorkspace($browser, int $workspaceId)
{
    return $browser->click('[data-testid="playcanvas-option"]')
        ->waitForLocation('/workspace-selection', 15)
        ->click('[data-testid="workspace-' . $workspaceId . '"]')
        ->waitForLocation('/chat', 15);
}

/**
 * Helper function to create a new workspace
 */
function createWorkspace($browser, string $name)
{
    return $browser->click('[data-testid="playcanvas-option"]')
        ->waitForLocation('/workspace-selection', 15)
        ->click('[data-testid="create-workspace-button"]')
        ->waitFor('input[name="name"]', 10)
        ->type('name', $name)
        ->press('Create Workspace')
        ->waitForLocation('/chat', 15);
}

/**
 * Helper function to send a chat message
 */
function sendChatMessage($browser, string $message)
{
    return $browser->type('[data-testid="message-input"]', $message)
        ->press('Send')
        ->waitFor('[data-testid="user-message"]', 10);
}

/**
 * Helper function to create a new game
 */
function createGame($browser, string $name)
{
    return $browser->visit('/games')
        ->waitFor('[data-testid="create-game-button"]', 10)
        ->click('[data-testid="create-game-button"]')
        ->waitFor('input[name="name"]', 10)
        ->type('name', $name)
        ->press('Create Game')
        ->waitForLocation('/games/', 15);
}

/**
 * Helper function to wait for and assert success message
 */
function assertSuccessMessage($browser, ?string $expectedMessage = null)
{
    $browser->waitFor('[data-testid="success-message"]', 10);
    
    if ($expectedMessage) {
        $browser->assertSee($expectedMessage);
    }
    
    return $browser;
}

/**
 * Helper function to wait for and assert error message
 */
function assertErrorMessage($browser, ?string $expectedMessage = null)
{
    $browser->waitFor('[data-testid="error-message"]', 10);
    
    if ($expectedMessage) {
        $browser->assertSee($expectedMessage);
    }
    
    return $browser;
}

/**
 * Helper function to test mobile responsiveness
 */
function testMobileView($browser, callable $callback)
{
    $browser->resize(375, 667); // iPhone SE size
    $callback($browser);
    $browser->resize(1920, 1080); // Reset to desktop
    return $browser;
}

/**
 * Helper function to close any open modal
 */
function closeModal($browser)
{
    if ($browser->element('[data-testid="close-modal"]')) {
        $browser->click('[data-testid="close-modal"]');
    } elseif ($browser->element('[data-testid="modal-overlay"]')) {
        $browser->click('[data-testid="modal-overlay"]');
    }
    
    return $browser;
}

/**
 * Helper function to logout user
 */
function logoutUser($browser)
{
    return $browser->click('[data-testid="user-menu-trigger"]')
        ->waitFor('[data-testid="logout-button"]', 5)
        ->click('[data-testid="logout-button"]')
        ->waitForLocation('/', 15);
}