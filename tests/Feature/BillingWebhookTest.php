<?php

namespace Tests\Feature;

use App\Models\BillingHistory;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable webhook signature verification for testing
        config(['cashier.webhook.secret' => null]);
        
        // Create test subscription plans
        SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'monthly_credits' => 10000,
            'price_cents' => 2999,
            'stripe_price_id' => 'price_test_pro',
        ]);

        SubscriptionPlan::create([
            'name' => 'Enterprise Plan',
            'slug' => 'enterprise',
            'monthly_credits' => 50000,
            'price_cents' => 9999,
            'stripe_price_id' => 'price_test_enterprise',
        ]);
    }

    public function test_credit_purchase_webhook_adds_credits()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123', 'credits' => 1000]);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'checkout.session.completed',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'cs_test123',
                    'customer' => 'cus_test123',
                    'mode' => 'payment',
                    'amount_total' => 2999,
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test123',
                    'metadata' => [
                        'type' => 'credit_purchase',
                        'company_id' => (string) $company->id,
                        'credits' => '5000',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        
        // Verify credits were added
        $company->refresh();
        $this->assertEquals(6000, $company->credits); // 1000 + 5000
        
        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'company_id' => $company->id,
            'type' => 'credit_purchase',
            'credits_added' => 5000,
            'amount_cents' => 2999,
            'status' => 'succeeded',
        ]);
    }

    public function test_subscription_cancelled_webhook_reverts_plan()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123', 'plan' => 'pro']);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'customer.subscription.deleted',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                    'customer' => 'cus_test123',
                    'canceled_at' => now()->timestamp,
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        
        // Verify company plan was reverted
        $company->refresh();
        $this->assertEquals('starter', $company->plan);
        
        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'company_id' => $company->id,
            'type' => 'subscription_cancelled',
        ]);
    }

    public function test_subscription_updated_webhook_changes_plan()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123', 'plan' => 'pro']);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'customer.subscription.updated',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                    'customer' => 'cus_test123',
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => 'price_test_enterprise',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        
        // Verify company plan was updated
        $company->refresh();
        $this->assertEquals('enterprise', $company->plan);
    }

    public function test_payment_failed_webhook_logs_failure()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123']);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'invoice.payment_failed',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'amount_due' => 2999,
                    'currency' => 'usd',
                    'attempt_count' => 1,
                    'last_finalization_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        
        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'company_id' => $company->id,
            'type' => 'payment_failed',
            'status' => 'failed',
            'amount_cents' => 2999,
        ]);
    }

    public function test_webhook_handles_invalid_company_gracefully()
    {
        $payload = [
            'type' => 'checkout.session.completed',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'cs_test123',
                    'customer' => 'cus_nonexistent',
                    'mode' => 'payment',
                    'amount_total' => 2999,
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test123',
                    'metadata' => [
                        'type' => 'credit_purchase',
                        'company_id' => '99999',
                        'credits' => '5000',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        // Should still return 200 even if company doesn't exist
        $response->assertStatus(200);
    }

    public function test_webhook_handles_missing_metadata_gracefully()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123', 'credits' => 0]);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'checkout.session.completed',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'cs_test123',
                    'customer' => 'cus_test123',
                    'mode' => 'payment',
                    'amount_total' => 2999,
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test123',
                    'metadata' => [
                        'type' => 'credit_purchase',
                        // Missing company_id and credits
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        // Should still return 200 even with invalid metadata
        $response->assertStatus(200);
        
        // Credits should not be added
        $company->refresh();
        $this->assertEquals(0, $company->credits);
    }
}