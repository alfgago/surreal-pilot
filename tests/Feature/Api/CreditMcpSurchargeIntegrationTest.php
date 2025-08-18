<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditMcpSurchargeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private CreditManager $creditManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create([
            'credits' => 1000.0,
            'monthly_credit_limit' => 5000.0,
        ]);
        
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['role' => 'developer']);
        $this->user->update(['current_company_id' => $this->company->id]);
        
        $this->creditManager = app(CreditManager::class);
    }

    public function test_get_real_time_balance_endpoint()
    {
        Sanctum::actingAs($this->user);

        // Use some credits with MCP surcharge
        $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            100,
            'playcanvas',
            'Test PlayCanvas Command'
        );

        $response = $this->getJson('/api/credits/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
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
                ],
            ]);

        $data = $response->json('data');
        $this->assertEqualsWithDelta(899.9, $data['current_credits'], 0.01); // 1000 - 100.1
        $this->assertEqualsWithDelta(100.1, $data['balance_summary']['current_month_usage'], 0.01);
    }

    public function test_get_engine_usage_analytics_endpoint()
    {
        Sanctum::actingAs($this->user);

        // Create mixed usage
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas', 'PlayCanvas Command 1');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 200, 'unreal', 'Unreal Command 1');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 150, 'playcanvas', 'PlayCanvas Command 2');

        $response = $this->getJson('/api/credits/analytics?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'analytics' => [
                        'total_usage',
                        'total_mcp_surcharges',
                        'engine_breakdown' => [
                            'unreal' => ['usage', 'transactions', 'mcp_surcharge'],
                            'playcanvas' => ['usage', 'transactions', 'mcp_surcharge'],
                            'other' => ['usage', 'transactions', 'mcp_surcharge'],
                        ],
                        'daily_breakdown',
                    ],
                    'period' => ['from', 'to'],
                ],
            ]);

        $analytics = $response->json('data.analytics');
        $this->assertEqualsWithDelta(450.2, $analytics['total_usage'], 0.01); // 100.1 + 200 + 150.1
        $this->assertEqualsWithDelta(0.2, $analytics['total_mcp_surcharges'], 0.01); // 0.1 + 0 + 0.1
        $this->assertEqualsWithDelta(250.2, $analytics['engine_breakdown']['playcanvas']['usage'], 0.01); // 100.1 + 150.1
        $this->assertEquals(200, $analytics['engine_breakdown']['unreal']['usage']);
    }

    public function test_get_transaction_history_with_engine_filter()
    {
        Sanctum::actingAs($this->user);

        // Create transactions for different engines
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas', 'PlayCanvas Command');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 200, 'unreal', 'Unreal Command');

        // Test filtering by PlayCanvas
        $response = $this->getJson('/api/credits/transactions?engine_type=playcanvas&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transactions',
                    'pagination' => ['total', 'limit', 'offset', 'has_more'],
                ],
            ]);

        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('playcanvas', $transactions[0]['metadata']['engine_type']);
        $this->assertEqualsWithDelta(100.1, $transactions[0]['amount'], 0.01);

        // Test filtering by Unreal
        $response = $this->getJson('/api/credits/transactions?engine_type=unreal&limit=10');
        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('unreal', $transactions[0]['metadata']['engine_type']);
        $this->assertEquals(200, $transactions[0]['amount']);
    }

    public function test_get_mcp_surcharge_info_endpoint()
    {
        $response = $this->getJson('/api/credits/mcp-surcharge-info');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'surcharge_rates' => [
                        'unreal' => 0.0,
                        'playcanvas' => 0.1,
                    ],
                    'description' => 'MCP surcharge is applied per action for PlayCanvas operations',
                ],
            ]);
    }
}