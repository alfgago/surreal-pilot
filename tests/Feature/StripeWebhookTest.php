<?php

namespace Tests\Feature;

use App\Models\BillingHistory;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CreditManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app('db')->disconnect();
        
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

    public function test_webhook_signature_verification_fails_with_invalid_signature()
    {
        // Set the webhook secret in config for testing
        config(['cashier.webhook.secret' => 'test_webhook_secret']);
        
        // Mock Stripe\Webhook to throw SignatureVerificationException
        $this->partialMock(\Stripe\Webhook::class, function ($mock) {
            $mock->shouldReceive('constructEvent')
                ->andThrow(new \Stripe\Exception\SignatureVerificationException('Invalid signature'));
        });
        
        $payload = json_encode(['type' => 'checkout.session.completed']);
        
        $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400);
    }

    public function test_checkout_session_completed_handles_credit_purchase()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123']);
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
                        'company_id' => $company->id,
                        'credits' => '5000',
                    ],
                ],
            ],
        ];

        // Mock the webhook signature verification
        $this->mockWebhookSignature();

        $response = $this->postJson('/stripe/webhook', $payload);

        if ($response->status() !== 200) {
            dump($response->getContent());
        }
        
        $response->assertStatus(200);
        
        // Verify credits were added
        $company->refresh();
        $this->assertEquals(5000, $company->credits);
        
        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'company_id' => $company->id,
            'type' => 'credit_purchase',
            'credits_added' => 5000,
            'amount_cents' => 2999,
        ]);
    }

    public function test_checkout_session_completed_handles_subscription_creation()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123']);
        $user->companies()->attach($company);

        $payload = [
            'type' => 'checkout.session.completed',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'cs_test123',
                    'customer' => 'cus_test123',
                    'mode' => 'subscription',
                    'amount_total' => 2999,
                    'currency' => 'usd',
                ],
            ],
        ];

        $this->mockWebhookSignature();

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
    }

    public function test_invoice_payment_succeeded_handles_missing_company()
    {
        $payload = [
            'type' => 'invoice.payment_succeeded',
            'id' => 'evt_test123',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_nonexistent',
                    'amount_paid' => 2999,
                    'currency' => 'usd',
                    'period_start' => now()->timestamp,
                    'period_end' => now()->addMonth()->timestamp,
                ],
            ],
        ];

        $this->mockWebhookSignature();

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
    }

    public function test_customer_subscription_updated_changes_plan()
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

        $this->mockWebhookSignature();

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        
        // Verify company plan was updated
        $company->refresh();
        $this->assertEquals('enterprise', $company->plan);
    }

    public function test_customer_subscription_deleted_reverts_to_starter()
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

        $this->mockWebhookSignature();

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

    public function test_invoice_payment_failed_logs_failure()
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

        $this->mockWebhookSignature();

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

    protected function mockWebhookSignature()
    {
        // Set the webhook secret in config for testing
        config(['cashier.webhook.secret' => 'test_webhook_secret']);
        
        // Mock the Stripe webhook signature verification
        $this->partialMock(\Stripe\Webhook::class, function ($mock) {
            $mock->shouldReceive('constructEvent')->andReturn(true);
        });
    }
}