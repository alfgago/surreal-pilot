<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Services\CreditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditSystemIntegrationTest extends TestCase
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
        
        // Properly switch to the company using the HasCompanies trait method
        $this->user->switchCompany($this->company);
        
        $this->creditManager = app(CreditManager::class);
    }

    public function test_mcp_surcharge_info_endpoint_works()
    {
        Sanctum::actingAs($this->user);
        
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

    public function test_real_time_balance_endpoint_works()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/credits/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_credits',
                    'last_updated',
                    'balance_summary',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1000.0, $data['current_credits']);
    }

    public function test_credit_deduction_with_mcp_surcharge()
    {
        $initialCredits = $this->company->credits;

        // Test PlayCanvas operation (should have surcharge)
        $result = $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            100,
            'playcanvas',
            'Test PlayCanvas Command'
        );

        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEqualsWithDelta(899.9, $this->company->credits, 0.01); // 1000 - 100.1

        // Test Unreal operation (no surcharge)
        $result = $this->creditManager->deductCreditsWithMcpSurcharge(
            $this->company,
            50,
            'unreal',
            'Test Unreal Command'
        );

        $this->assertTrue($result);
        $this->company->refresh();
        $this->assertEqualsWithDelta(849.9, $this->company->credits, 0.01); // 899.9 - 50
    }

    public function test_engine_usage_analytics_endpoint()
    {
        Sanctum::actingAs($this->user);

        // Create some usage
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas', 'PlayCanvas Command');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 200, 'unreal', 'Unreal Command');

        $response = $this->getJson('/api/credits/analytics?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'analytics' => [
                        'total_usage',
                        'total_mcp_surcharges',
                        'engine_breakdown',
                        'daily_breakdown',
                    ],
                    'period',
                ],
            ]);

        $analytics = $response->json('data.analytics');
        $this->assertEqualsWithDelta(300.1, $analytics['total_usage'], 0.01); // 100.1 + 200
        $this->assertEqualsWithDelta(0.1, $analytics['total_mcp_surcharges'], 0.01); // Only from PlayCanvas
    }

    public function test_transaction_history_with_engine_filtering()
    {
        Sanctum::actingAs($this->user);

        // Create transactions
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 100, 'playcanvas', 'PlayCanvas Command');
        $this->creditManager->deductCreditsWithMcpSurcharge($this->company, 200, 'unreal', 'Unreal Command');

        // Test PlayCanvas filter
        $response = $this->getJson('/api/credits/transactions?engine_type=playcanvas');
        $response->assertStatus(200);
        
        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('playcanvas', $transactions[0]['metadata']['engine_type']);

        // Test Unreal filter
        $response = $this->getJson('/api/credits/transactions?engine_type=unreal');
        $response->assertStatus(200);
        
        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('unreal', $transactions[0]['metadata']['engine_type']);
    }
}