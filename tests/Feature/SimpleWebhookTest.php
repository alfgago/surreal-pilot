<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SimpleWebhookTest extends TestCase
{
    use DatabaseMigrations;

    public function test_webhook_endpoint_exists()
    {
        // Test that the webhook endpoint exists and returns some response
        $response = $this->postJson('/stripe/webhook', [
            'type' => 'test.event',
            'id' => 'evt_test123',
        ]);

        // Should not be 404
        $this->assertNotEquals(404, $response->status());
    }

    public function test_credit_purchase_webhook_basic()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['stripe_id' => 'cus_test123', 'credits' => 0]);
        $user->companies()->attach($company);

        // Mock webhook signature verification by setting config
        config(['cashier.webhook.secret' => null]); // Disable signature verification for test

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
        $this->assertEquals(5000, $company->credits);
        
        // Verify billing history was created
        $this->assertDatabaseHas('billing_history', [
            'company_id' => $company->id,
            'type' => 'credit_purchase',
            'credits_added' => 5000,
            'amount_cents' => 2999,
        ]);
    }
}