<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CreditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSystemTest extends TestCase
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

    public function test_credit_balance_api_returns_correct_data()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'credits' => 1500,
            'plan' => 'pro',
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->getJson('/api/billing/balance');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_credits',
            'last_updated',
            'balance_summary' => [
                'current_credits',
                'monthly_limit',
                'current_month_usage',
                'remaining_monthly_allowance',
                'is_approaching_limit',
                'plan',
            ],
        ]);
        
        $response->assertJson([
            'current_credits' => 1500,
            'balance_summary' => [
                'current_credits' => 1500,
                'plan' => 'pro',
            ],
        ]);
    }

    public function test_credit_manager_deducts_credits_correctly()
    {
        $company = Company::factory()->create([
            'credits' => 1000,
        ]);

        $creditManager = app(CreditManager::class);
        
        $result = $creditManager->deductCredits($company, 100, 'Test deduction');
        
        $this->assertTrue($result);
        $this->assertEquals(900, $company->fresh()->credits);
        
        // Check transaction was created
        $this->assertDatabaseHas('credit_transactions', [
            'company_id' => $company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Test deduction',
        ]);
    }

    public function test_credit_manager_prevents_overdraft()
    {
        $company = Company::factory()->create([
            'credits' => 50,
        ]);

        $creditManager = app(CreditManager::class);
        
        $result = $creditManager->deductCredits($company, 100, 'Test overdraft');
        
        $this->assertFalse($result);
        $this->assertEquals(50, $company->fresh()->credits);
        
        // Check no transaction was created
        $this->assertDatabaseMissing('credit_transactions', [
            'company_id' => $company->id,
            'amount' => 100,
        ]);
    }

    public function test_usage_analytics_api_returns_correct_structure()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        // Create some test transactions
        CreditTransaction::factory()->create([
            'company_id' => $company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Test usage',
        ]);

        $response = $this->actingAs($user)->getJson('/api/billing/analytics?' . http_build_query([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'analytics' => [
                'daily_usage',
                'total_debits',
                'total_credits',
                'net_usage',
                'transaction_count',
            ],
            'engine_analytics' => [
                'total_usage',
                'total_mcp_surcharges',
                'engine_breakdown',
            ],
        ]);
    }

    public function test_subscription_plans_are_available()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->getJson('/api/billing/subscription');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'available_plans',
            'company_plan',
        ]);
        
        // Check that we have the expected plans
        $plans = $response->json('available_plans');
        $planSlugs = collect($plans)->pluck('slug')->toArray();
        
        $this->assertContains('starter', $planSlugs);
        $this->assertContains('pro', $planSlugs);
        $this->assertContains('enterprise', $planSlugs);
    }

    public function test_billing_summary_includes_usage_trend()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'credits' => 1000,
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->getJson('/api/billing/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'balance_summary',
            'current_month_usage',
            'usage_trend' => [
                'absolute',
                'percentage',
                'direction',
            ],
            'last_updated',
        ]);
    }

    public function test_real_time_balance_updates_correctly()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'credits' => 1000,
        ]);
        
        $user->companies()->attach($company->id, ['role' => 'owner']);

        // Get initial balance
        $response1 = $this->actingAs($user)->getJson('/api/billing/balance');
        $initialCredits = $response1->json('current_credits');
        
        // Deduct some credits
        $creditManager = app(CreditManager::class);
        $creditManager->deductCredits($company, 100, 'Test deduction');
        
        // Get updated balance
        $response2 = $this->actingAs($user)->getJson('/api/billing/balance');
        $updatedCredits = $response2->json('current_credits');
        
        $this->assertEquals($initialCredits - 100, $updatedCredits);
    }
}