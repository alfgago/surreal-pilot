<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CreditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditSystemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private CreditManager $creditManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'credits' => 1000,
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);
        
        $this->user->companies()->attach($this->company, ['role' => 'admin']);
        $this->creditManager = new CreditManager();
    }

    public function test_complete_credit_purchase_workflow()
    {
        // 1. Check initial state
        $this->assertEquals(1000, $this->company->credits);
        $this->assertEquals(0, CreditTransaction::count());

        // 2. Purchase credits
        $this->creditManager->addCredits($this->company, 500, 'Credit purchase', [
            'payment_method' => 'stripe',
            'payment_id' => 'pay_test123',
            'amount_paid' => 2500, // $25.00 in cents
        ]);

        // 3. Verify credits were added
        $this->company->refresh();
        $this->assertEquals(1500, $this->company->credits);

        // 4. Verify transaction was recorded
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(500, $transaction->amount);
        $this->assertEquals('Credit purchase', $transaction->description);
        $this->assertEquals('stripe', $transaction->metadata['payment_method']);
        $this->assertEquals('pay_test123', $transaction->metadata['payment_id']);
    }

    public function test_complete_ai_usage_workflow()
    {
        // 1. Simulate AI request processing
        $tokensUsed = 150;
        $result = $this->creditManager->deductCredits(
            $this->company,
            $tokensUsed,
            'AI Chat Request',
            [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'user_id' => $this->user->id,
                'request_type' => 'chat',
                'message_count' => 3,
            ]
        );

        // 2. Verify deduction was successful
        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEquals(850, $this->company->credits);

        // 3. Verify transaction was recorded with metadata
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals($tokensUsed, $transaction->amount);
        $this->assertEquals('openai', $transaction->metadata['provider']);
        $this->assertEquals($this->user->id, $transaction->metadata['user_id']);
    }

    public function test_monthly_usage_tracking_workflow()
    {
        // 1. Create usage throughout the month
        $usageData = [
            ['tokens' => 100, 'date' => now()->startOfMonth()],
            ['tokens' => 200, 'date' => now()->startOfMonth()->addDays(5)],
            ['tokens' => 150, 'date' => now()->startOfMonth()->addDays(10)],
            ['tokens' => 300, 'date' => now()->startOfMonth()->addDays(15)],
            ['tokens' => 250, 'date' => now()->startOfMonth()->addDays(20)],
        ];

        foreach ($usageData as $usage) {
            $transaction = new CreditTransaction([
                'company_id' => $this->company->id,
                'amount' => $usage['tokens'],
                'type' => 'debit',
                'description' => 'AI usage',
                'metadata' => ['provider' => 'openai'],
            ]);
            $transaction->created_at = $usage['date'];
            $transaction->updated_at = $usage['date'];
            $transaction->save();
        }

        // 2. Check monthly usage calculation
        $monthlyUsage = $this->creditManager->getCurrentMonthUsage($this->company);
        $this->assertEquals(1000, $monthlyUsage); // Sum of all usage

        // 3. Check if approaching limit
        $this->assertFalse($this->creditManager->isApproachingLimit($this->company));

        // 4. Add more usage to approach limit
        $this->creditManager->deductCredits($this->company, 4000, 'Heavy usage');
        $this->assertTrue($this->creditManager->isApproachingLimit($this->company));
    }

    public function test_subscription_plan_upgrade_workflow()
    {
        // 1. Create subscription plans
        $starterPlan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_credits' => 1000,
            'price_cents' => 1000,
            'stripe_price_id' => 'price_starter',
        ]);

        $proPlan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_credits' => 5000,
            'price_cents' => 5000,
            'stripe_price_id' => 'price_pro',
        ]);

        // 2. Start with starter plan
        $this->company->update([
            'plan' => 'starter',
            'monthly_credit_limit' => 1000,
            'credits' => 500,
        ]);

        // 3. Simulate plan upgrade
        $this->company->update([
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);

        // 4. Add credits for the new plan
        $this->creditManager->addCredits($this->company, 4500, 'Plan upgrade bonus');

        // 5. Verify upgrade
        $this->company->refresh();
        $this->assertEquals('pro', $this->company->plan);
        $this->assertEquals(5000, $this->company->monthly_credit_limit);
        $this->assertEquals(5000, $this->company->credits); // 500 + 4500
    }

    public function test_credit_exhaustion_and_replenishment_workflow()
    {
        // 1. Use up most credits
        $this->creditManager->deductCredits($this->company, 950, 'Heavy usage');
        $this->company->refresh();
        $this->assertEquals(50, $this->company->credits);

        // 2. Verify low credits detection
        $balanceSummary = $this->creditManager->getBalanceSummary($this->company);
        $this->assertTrue($balanceSummary['is_approaching_limit']);

        // 3. Try to use more credits than available
        $result = $this->creditManager->deductCredits($this->company, 100, 'Attempted usage');
        $this->assertFalse($result);
        
        // Credits should remain unchanged
        $this->company->refresh();
        $this->assertEquals(50, $this->company->credits);

        // 4. Replenish credits
        $this->creditManager->addCredits($this->company, 1000, 'Emergency top-up');
        $this->company->refresh();
        $this->assertEquals(1050, $this->company->credits);

        // 5. Now usage should work again
        $result = $this->creditManager->deductCredits($this->company, 100, 'Successful usage');
        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEquals(950, $this->company->credits);
    }

    public function test_usage_analytics_and_reporting_workflow()
    {
        // 1. Create diverse usage patterns
        $providers = ['openai', 'anthropic', 'gemini'];
        $models = ['gpt-4', 'claude-3', 'gemini-pro'];
        
        for ($i = 0; $i < 10; $i++) {
            $provider = $providers[$i % 3];
            $model = $models[$i % 3];
            $tokens = rand(50, 200);
            
            $this->creditManager->deductCredits(
                $this->company,
                $tokens,
                "AI request #{$i}",
                [
                    'provider' => $provider,
                    'model' => $model,
                    'user_id' => $this->user->id,
                    'request_type' => 'chat',
                ]
            );
        }

        // 2. Get usage analytics
        $analytics = $this->creditManager->getUsageAnalytics(
            $this->company,
            now()->subDays(7),
            now()
        );

        // 3. Verify analytics structure
        $this->assertArrayHasKey('total_debits', $analytics);
        $this->assertArrayHasKey('total_credits', $analytics);
        $this->assertArrayHasKey('net_usage', $analytics);
        $this->assertArrayHasKey('transaction_count', $analytics);
        $this->assertArrayHasKey('daily_usage', $analytics);

        // 4. Verify data accuracy
        $this->assertGreaterThan(0, $analytics['total_debits']);
        $this->assertEquals(10, $analytics['transaction_count']);
        $this->assertIsArray($analytics['daily_usage']);

        // 5. Get recent transactions
        $recentTransactions = $this->creditManager->getRecentTransactions($this->company, 5);
        $this->assertCount(5, $recentTransactions);
        
        // Should be ordered by most recent first
        $this->assertTrue(
            $recentTransactions->first()->created_at >= $recentTransactions->last()->created_at
        );
    }

    public function test_multi_user_company_credit_sharing()
    {
        // 1. Create additional users in the company
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        $this->company->users()->attach($user2, ['role' => 'developer']);
        $this->company->users()->attach($user3, ['role' => 'developer']);

        // 2. Each user makes AI requests
        $users = [$this->user, $user2, $user3];
        $totalUsage = 0;
        
        foreach ($users as $index => $user) {
            $tokens = 100 + ($index * 50); // 100, 150, 200 tokens
            $this->creditManager->deductCredits(
                $this->company,
                $tokens,
                "User {$user->id} AI request",
                ['user_id' => $user->id, 'provider' => 'openai']
            );
            $totalUsage += $tokens;
        }

        // 3. Verify shared credit pool was used
        $this->company->refresh();
        $this->assertEquals(1000 - $totalUsage, $this->company->credits);

        // 4. Verify all transactions are tracked
        $transactions = CreditTransaction::where('company_id', $this->company->id)->get();
        $this->assertCount(3, $transactions);
        
        // Each transaction should have the correct user_id
        foreach ($users as $index => $user) {
            $userTransaction = $transactions->where('metadata->user_id', $user->id)->first();
            $this->assertNotNull($userTransaction);
            $this->assertEquals(100 + ($index * 50), $userTransaction->amount);
        }
    }
}