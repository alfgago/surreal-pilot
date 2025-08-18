<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Services\CreditManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditManagerTest extends TestCase
{
    use RefreshDatabase;

    private CreditManager $creditManager;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->creditManager = new CreditManager();
        $this->company = Company::factory()->create([
            'credits' => 1000,
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);
    }

    public function test_can_deduct_credits_successfully()
    {
        $initialCredits = $this->company->credits;
        $tokensToDeduct = 100;

        $result = $this->creditManager->deductCredits($this->company, $tokensToDeduct, 'Test API call');

        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEquals($initialCredits - $tokensToDeduct, $this->company->credits);

        // Check transaction was created
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals($tokensToDeduct, $transaction->amount);
        $this->assertEquals('Test API call', $transaction->description);
    }

    public function test_cannot_deduct_more_credits_than_available()
    {
        $this->company->update(['credits' => 50]);
        $tokensToDeduct = 100;

        $result = $this->creditManager->deductCredits($this->company, $tokensToDeduct);

        $this->assertFalse($result);
        $this->company->refresh();
        $this->assertEquals(50, $this->company->credits);

        // Check no transaction was created
        $this->assertEquals(0, CreditTransaction::where('company_id', $this->company->id)->count());
    }

    public function test_can_add_credits_successfully()
    {
        $initialCredits = $this->company->credits;
        $creditsToAdd = 500;

        $this->creditManager->addCredits($this->company, $creditsToAdd, 'Credit purchase');

        $this->company->refresh();
        $this->assertEquals($initialCredits + $creditsToAdd, $this->company->credits);

        // Check transaction was created
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals($creditsToAdd, $transaction->amount);
        $this->assertEquals('Credit purchase', $transaction->description);
    }

    public function test_can_afford_request_returns_correct_boolean()
    {
        $this->company->update(['credits' => 100]);

        $this->assertTrue($this->creditManager->canAffordRequest($this->company, 50));
        $this->assertTrue($this->creditManager->canAffordRequest($this->company, 100));
        $this->assertFalse($this->creditManager->canAffordRequest($this->company, 150));
    }

    public function test_get_current_month_usage_returns_correct_amount()
    {
        // Create a fresh company for this test
        $testCompany = Company::factory()->create([
            'credits' => 1000,
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);
        
        // Create some transactions for current month using the service
        $this->creditManager->deductCredits($testCompany, 100, 'API call 1');
        $this->creditManager->deductCredits($testCompany, 200, 'API call 2');

        // Create a credit transaction (should not be counted in usage)
        $this->creditManager->addCredits($testCompany, 50, 'Credit added');

        // Create a transaction from previous month (should not be counted)
        $previousMonthTransaction = new CreditTransaction([
            'company_id' => $testCompany->id,
            'amount' => 300,
            'type' => 'debit',
            'description' => 'Previous month',
        ]);
        $previousMonthTransaction->created_at = now()->subMonths(2)->startOfMonth();
        $previousMonthTransaction->updated_at = now()->subMonths(2)->startOfMonth();
        $previousMonthTransaction->save();

        $usage = $this->creditManager->getCurrentMonthUsage($testCompany);
        
        $this->assertEquals(300, $usage);
    }

    public function test_get_usage_analytics_returns_correct_data()
    {
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        // Create test transactions
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'API call',
            'created_at' => $from->copy()->addDays(1),
        ]);

        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 200,
            'type' => 'debit',
            'description' => 'API call',
            'created_at' => $from->copy()->addDays(2),
        ]);

        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 500,
            'type' => 'credit',
            'description' => 'Credit purchase',
            'created_at' => $from->copy()->addDays(3),
        ]);

        $analytics = $this->creditManager->getUsageAnalytics($this->company, $from, $to);

        $this->assertEquals(300, $analytics['total_debits']);
        $this->assertEquals(500, $analytics['total_credits']);
        $this->assertEquals(-200, $analytics['net_usage']); // More credits than debits
        $this->assertEquals(3, $analytics['transaction_count']);
        $this->assertIsArray($analytics['daily_usage']);
    }

    public function test_is_approaching_limit_returns_correct_boolean()
    {
        $this->company->update(['monthly_credit_limit' => 1000]);

        // Create transactions totaling 950 (95% of limit)
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 950,
            'type' => 'debit',
            'description' => 'Heavy usage',
            'created_at' => now(),
        ]);

        // Default threshold is 10%, so 90% usage should trigger warning
        $this->assertTrue($this->creditManager->isApproachingLimit($this->company));

        // Test with custom threshold
        $this->assertFalse($this->creditManager->isApproachingLimit($this->company, 0.03)); // 3% threshold
    }

    public function test_get_balance_summary_returns_complete_data()
    {
        $this->company->update([
            'credits' => 750,
            'monthly_credit_limit' => 2000,
            'plan' => 'enterprise',
        ]);

        // Add some usage for current month
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 300,
            'type' => 'debit',
            'description' => 'API usage',
            'created_at' => now(),
        ]);

        $summary = $this->creditManager->getBalanceSummary($this->company);

        $this->assertEquals(750, $summary['current_credits']);
        $this->assertEquals(2000, $summary['monthly_limit']);
        $this->assertEquals(300, $summary['current_month_usage']);
        $this->assertEquals(1700, $summary['remaining_monthly_allowance']);
        $this->assertEquals('enterprise', $summary['plan']);
        $this->assertIsBool($summary['is_approaching_limit']);
    }

    public function test_get_recent_transactions_returns_limited_results()
    {
        // Create a fresh company for this test
        $testCompany = Company::factory()->create([
            'credits' => 1000,
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);
        
        // Create more transactions than the limit
        for ($i = 0; $i < 60; $i++) {
            $transaction = new CreditTransaction([
                'company_id' => $testCompany->id,
                'amount' => 10,
                'type' => 'debit',
                'description' => "Transaction $i",
            ]);
            $transaction->created_at = now()->subMinutes($i);
            $transaction->updated_at = now()->subMinutes($i);
            $transaction->save();
        }

        $recentTransactions = $this->creditManager->getRecentTransactions($testCompany, 25);

        $this->assertEquals(25, $recentTransactions->count());
        
        // Should be ordered by most recent first
        $this->assertEquals('Transaction 0', $recentTransactions->first()->description);
        $this->assertEquals('Transaction 24', $recentTransactions->last()->description);
    }

    public function test_deduct_credits_with_metadata()
    {
        $metadata = [
            'provider' => 'openai',
            'model' => 'gpt-4',
            'request_id' => 'req_123',
        ];

        $this->creditManager->deductCredits($this->company, 100, 'API call with metadata', $metadata);

        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertEquals($metadata, $transaction->metadata);
    }

    public function test_add_credits_with_metadata()
    {
        $metadata = [
            'payment_id' => 'pay_123',
            'stripe_charge_id' => 'ch_456',
        ];

        $this->creditManager->addCredits($this->company, 500, 'Stripe payment', $metadata);

        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertEquals($metadata, $transaction->metadata);
    }
}