<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed subscription plans
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
    }

    public function test_billing_page_loads_successfully()
    {
        $user = User::factory()->create([
            'email' => 'alfredo@5e.cr',
            'password' => bcrypt('Test123!'),
        ]);
        
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'credits' => 1000,
            'plan' => 'starter',
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get('/company/billing');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Company/Billing')
                ->has('company')
                ->has('analytics')
                ->has('balanceSummary')
                ->has('subscriptionPlans')
        );
    }
}