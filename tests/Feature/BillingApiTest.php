<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_balance_api_works()
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
            'balance_summary',
        ]);
    }

    public function test_billing_summary_api_works()
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
            'usage_trend',
            'last_updated',
        ]);
    }
}