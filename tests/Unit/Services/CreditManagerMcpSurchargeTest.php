<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Services\CreditManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditManagerMcpSurchargeTest extends TestCase
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
            'monthly_credit_limit' => 5000,
        ]);
    }

    public function test_calculate_mcp_surcharge_for_playcanvas()
    {
        $surcharge = $this->creditManager->calculateMcpSurcharge('playcanvas');
        $this->assertEquals(0.1, $surcharge);
    }

    public function test_calculate_mcp_surcharge_for_unreal()
    {
        $surcharge = $this->creditManager->calculateMcpSurcharge('unreal');
        $this->assertEquals(0.0, $surcharge);
    }

    public function test_calculate_mcp_surcharge_with_multiple_actions()
    {
        $surcharge = $this->creditManager->calculateMcpSurcharge('playcanvas', 5);
        $this->assertEquals(0.5, $surcharge);
    }

    public function test_deduct_credits_with_mcp_surcharge_playcanvas()
    {
        $initialCredits = $this->company->credits;
        $tokens = 100;
        $engineType = 'playcanvas';

        $result = $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            $tokens,
            $engineType,
            'Test MCP Command',
            ['workspace_id' => 123]
        );

        $this->assertTrue($result);
        $this->company->refresh();
        
        // Should deduct 100 tokens + 0.1 surcharge = 100.1 total
        $expectedCredits = $initialCredits - 100.1;
        $this->assertEquals($expectedCredits, $this->company->credits);

        // Check transaction was created with correct metadata
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(100.1, $transaction->amount);
        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals('Test MCP Command', $transaction->description);
        
        $metadata = $transaction->metadata;
        $this->assertEquals('playcanvas', $metadata['engine_type']);
        $this->assertEquals(100, $metadata['base_tokens']);
        $this->assertEquals(0.1, $metadata['mcp_surcharge']);
        $this->assertEquals(100.1, $metadata['total_cost']);
        $this->assertTrue($metadata['has_mcp_surcharge']);
        $this->assertEquals(123, $metadata['workspace_id']);
    }

    public function test_deduct_credits_with_mcp_surcharge_unreal()
    {
        $initialCredits = $this->company->credits;
        $tokens = 100;
        $engineType = 'unreal';

        $result = $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            $tokens,
            $engineType
        );

        $this->assertTrue($result);
        $this->company->refresh();
        
        // Should deduct only 100 tokens (no surcharge for Unreal)
        $expectedCredits = $initialCredits - 100;
        $this->assertEquals($expectedCredits, $this->company->credits);

        // Check transaction metadata
        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $metadata = $transaction->metadata;
        $this->assertEquals('unreal', $metadata['engine_type']);
        $this->assertEquals(100, $metadata['base_tokens']);
        $this->assertEquals(0.0, $metadata['mcp_surcharge']);
        $this->assertEquals(100, $metadata['total_cost']);
        $this->assertFalse($metadata['has_mcp_surcharge']);
    }

    public function test_cannot_deduct_credits_with_insufficient_balance()
    {
        $this->company->update(['credits' => 50]);
        
        $result = $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            100,
            'playcanvas'
        );

        $this->assertFalse($result);
        $this->company->refresh();
        $this->assertEquals(50, $this->company->credits);
        
        // No transaction should be created
        $this->assertEquals(0, CreditTransaction::where('company_id', $this->company->id)->count());
    }

    public function test_get_engine_usage_analytics()
    {
        // Create test transactions for different engines
        $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            100,
            'playcanvas',
            'PlayCanvas Command 1',
            ['workspace_id' => 1]
        );

        $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            200,
            'unreal',
            'Unreal Command 1',
            ['workspace_id' => 2]
        );

        $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            150,
            'playcanvas',
            'PlayCanvas Command 2',
            ['workspace_id' => 3]
        );

        // Regular credit transaction without engine type
        $this->creditManager->deductCredits(
            $this->company,
            50,
            'Regular API call'
        );

        $from = Carbon::now()->subDay();
        $to = Carbon::now()->addDay();

        $analytics = $this->creditManager->getEngineUsageAnalytics($this->company, $from, $to);

        // Check totals (using delta for floating point precision)
        $this->assertEqualsWithDelta(500.2, $analytics['total_usage'], 0.01); // 100.1 + 200 + 150.1 + 50
        $this->assertEqualsWithDelta(0.2, $analytics['total_mcp_surcharges'], 0.01); // 0.1 + 0 + 0.1 + 0

        // Check engine breakdown
        $this->assertEqualsWithDelta(250.2, $analytics['engine_breakdown']['playcanvas']['usage'], 0.01); // 100.1 + 150.1
        $this->assertEquals(2, $analytics['engine_breakdown']['playcanvas']['transactions']);
        $this->assertEquals(0.2, $analytics['engine_breakdown']['playcanvas']['mcp_surcharge']);

        $this->assertEquals(200, $analytics['engine_breakdown']['unreal']['usage']);
        $this->assertEquals(1, $analytics['engine_breakdown']['unreal']['transactions']);
        $this->assertEquals(0, $analytics['engine_breakdown']['unreal']['mcp_surcharge']);

        $this->assertEquals(50, $analytics['engine_breakdown']['other']['usage']);
        $this->assertEquals(1, $analytics['engine_breakdown']['other']['transactions']);
        $this->assertEquals(0, $analytics['engine_breakdown']['other']['mcp_surcharge']);

        // Check daily breakdown exists
        $today = Carbon::now()->format('Y-m-d');
        $this->assertArrayHasKey($today, $analytics['daily_breakdown']);
        $this->assertEqualsWithDelta(500.2, $analytics['daily_breakdown'][$today]['total'], 0.01);
        $this->assertEqualsWithDelta(250.2, $analytics['daily_breakdown'][$today]['playcanvas'], 0.01);
        $this->assertEquals(200, $analytics['daily_breakdown'][$today]['unreal']);
        $this->assertEquals(50, $analytics['daily_breakdown'][$today]['other']);
        $this->assertEqualsWithDelta(0.2, $analytics['daily_breakdown'][$today]['mcp_surcharges'], 0.01);
    }

    public function test_get_real_time_balance()
    {
        $balance = $this->creditManager->getRealTimeBalance($this->company);

        $this->assertArrayHasKey('current_credits', $balance);
        $this->assertArrayHasKey('last_updated', $balance);
        $this->assertArrayHasKey('balance_summary', $balance);
        
        $this->assertEquals(1000, $balance['current_credits']);
        $this->assertIsString($balance['last_updated']);
        $this->assertIsArray($balance['balance_summary']);
    }

    public function test_can_afford_request_with_decimal_cost()
    {
        $this->company->update(['credits' => 100.5]);

        $this->assertTrue($this->creditManager->canAffordRequest($this->company, 100.1));
        $this->assertTrue($this->creditManager->canAffordRequest($this->company, 100.5));
        $this->assertFalse($this->creditManager->canAffordRequest($this->company, 100.6));
    }

    public function test_deduct_credits_with_decimal_amount()
    {
        $initialCredits = $this->company->credits;
        
        $result = $this->creditManager->deductCredits(
            $this->company,
            100.75,
            'Test decimal deduction',
            ['test' => true]
        );

        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEquals($initialCredits - 100.75, $this->company->credits);

        $transaction = CreditTransaction::where('company_id', $this->company->id)->first();
        $this->assertEquals(100.75, $transaction->amount);
    }

    public function test_monthly_usage_calculation_with_decimals()
    {
        // Create transactions for current month
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 200, 'unreal');
        $this->creditManager->deductCredits($this->company, 50.5, 'Regular usage');

        // Create transaction for previous month (should not be counted)
        $oldTransaction = new CreditTransaction([
            'company_id' => $this->company->id,
            'amount' => 1000,
            'type' => 'debit',
            'description' => 'Old transaction',
        ]);
        $oldTransaction->created_at = Carbon::now()->subMonth();
        $oldTransaction->updated_at = Carbon::now()->subMonth();
        $oldTransaction->save();

        $monthlyUsage = $this->creditManager->getCurrentMonthUsage($this->company);
        
        // Should be 100.1 + 200 + 50.5 = 350.6
        $this->assertEqualsWithDelta(350.6, $monthlyUsage, 0.01);
    }

    public function test_approaching_limit_with_decimal_usage()
    {
        $this->company->update(['monthly_credit_limit' => 1000]);

        // Use 950 credits (95% of limit)
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 949, 'playcanvas');
        // This adds 949 + 0.1 = 949.1 usage

        $this->assertTrue($this->creditManager->isApproachingLimit($this->company, 0.1)); // 10% threshold
        $this->assertFalse($this->creditManager->isApproachingLimit($this->company, 0.05)); // 5% threshold
    }

    public function test_engine_usage_analytics_empty_period()
    {
        $from = Carbon::now()->subYear();
        $to = Carbon::now()->subMonth();

        $analytics = $this->creditManager->getEngineUsageAnalytics($this->company, $from, $to);

        $this->assertEquals(0, $analytics['total_usage']);
        $this->assertEquals(0, $analytics['total_mcp_surcharges']);
        $this->assertEquals(0, $analytics['engine_breakdown']['playcanvas']['usage']);
        $this->assertEquals(0, $analytics['engine_breakdown']['unreal']['usage']);
        $this->assertEquals([], $analytics['daily_breakdown']);
    }

    public function test_balance_summary_includes_decimal_values()
    {
        // Use some credits with MCP surcharge
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas');

        $summary = $this->creditManager->getBalanceSummary($this->company);

        $this->assertEqualsWithDelta(899.9, $summary['current_credits'], 0.01); // 1000 - 100.1
        $this->assertEquals(5000, $summary['monthly_limit']);
        $this->assertEqualsWithDelta(100.1, $summary['current_month_usage'], 0.01);
        $this->assertEqualsWithDelta(4899.9, $summary['remaining_monthly_allowance'], 0.01); // 5000 - 100.1
    }
}