<?php

use App\Models\User;
use App\Models\Company;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
    $this->testCompany = $this->testUser->currentCompany;
});

test('user can access billing page', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to billing page
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="billing-page"]', 10)
            ->assertPresent('[data-testid="billing-page"]')
            ->assertPresent('[data-testid="credit-balance"]')
            ->assertPresent('[data-testid="subscription-plans"]')
            ->assertSee('Billing & Credits');
    });
});

test('billing page shows current credit balance', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="credit-balance"]', 10)
            ->assertPresent('[data-testid="credit-balance"]')
            ->assertSee('Current Balance')
            ->assertSee('1,000'); // Default test user credits
    });
});

test('billing page shows subscription plans', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="subscription-plans"]', 10)
            ->assertPresent('[data-testid="subscription-plans"]')
            ->assertSee('Starter')
            ->assertSee('Pro')
            ->assertSee('Enterprise');
    });
});

test('user can view usage analytics', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="usage-analytics"]', 10)
            ->assertPresent('[data-testid="usage-analytics"]')
            ->assertSee('Usage Analytics');
    });
});

test('user can view transaction history', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="transaction-history"]', 10)
            ->assertPresent('[data-testid="transaction-history"]')
            ->assertSee('Transaction History');
    });
});

test('user can upgrade subscription plan', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="subscription-plans"]', 10)
            ->click('[data-testid="upgrade-to-pro"]')
            ->waitFor('[data-testid="upgrade-modal"]', 10)
            ->assertPresent('[data-testid="upgrade-modal"]')
            ->assertSee('Upgrade to Pro');
            
        // Close modal for cleanup
        $browser->click('[data-testid="close-modal"]');
    });
});

test('billing page shows payment methods section', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="payment-methods"]', 10)
            ->assertPresent('[data-testid="payment-methods"]')
            ->assertSee('Payment Methods');
    });
});

test('user can add payment method', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="payment-methods"]', 10)
            ->click('[data-testid="add-payment-method"]')
            ->waitFor('[data-testid="payment-method-modal"]', 10)
            ->assertPresent('[data-testid="payment-method-modal"]')
            ->assertSee('Add Payment Method');
            
        // Close modal for cleanup
        $browser->click('[data-testid="close-modal"]');
    });
});

test('billing page shows credit purchase options', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="credit-purchase"]', 10)
            ->assertPresent('[data-testid="credit-purchase"]')
            ->assertSee('Buy Credits');
    });
});

test('user can purchase additional credits', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="credit-purchase"]', 10)
            ->click('[data-testid="buy-credits-button"]')
            ->waitFor('[data-testid="credit-purchase-modal"]', 10)
            ->assertPresent('[data-testid="credit-purchase-modal"]')
            ->assertSee('Purchase Credits');
            
        // Close modal for cleanup
        $browser->click('[data-testid="close-modal"]');
    });
});

test('billing page shows usage warnings when approaching limits', function () {
    // Update company to have low credits
    $this->testCompany->update(['credits' => 50]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->visit('/company/billing')
            ->waitFor('[data-testid="credit-balance"]', 10);
            
        // Should show warning for low credits
        if ($browser->element('[data-testid="low-credit-warning"]')) {
            $browser->assertPresent('[data-testid="low-credit-warning"]')
                ->assertSee('Low Credit Balance');
        }
    });
    
    // Reset credits for other tests
    $this->testCompany->update(['credits' => 1000]);
});

test('billing page is responsive on mobile', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        $browser->resize(375, 667) // iPhone SE size
            ->visit('/company/billing')
            ->waitFor('[data-testid="billing-page"]', 10)
            ->assertPresent('[data-testid="billing-page"]')
            ->assertPresent('[data-testid="credit-balance"]')
            ->resize(1920, 1080); // Reset to desktop
    });
});