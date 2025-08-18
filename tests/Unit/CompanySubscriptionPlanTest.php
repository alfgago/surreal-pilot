<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_have_subscription_plan(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'plan' => 'pro',
        ]);

        $this->assertNotNull($company->subscriptionPlan);
        $this->assertEquals('Pro', $company->subscriptionPlan->name);
        $this->assertEquals(10000, $company->subscriptionPlan->monthly_credits);
    }

    public function test_company_monthly_credit_limit_accessor_uses_subscription_plan(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'plan' => 'enterprise',
        ]);

        $this->assertEquals(100000, $company->monthly_credit_limit);
    }

    public function test_company_monthly_credit_limit_accessor_fallback(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'plan' => 'nonexistent',
            'monthly_credit_limit' => 2500,
        ]);

        $this->assertEquals(2500, $company->monthly_credit_limit);
    }

    public function test_subscription_plan_has_companies_relationship(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $user = User::factory()->create();
        $company1 = Company::factory()->create([
            'user_id' => $user->id,
            'plan' => 'starter',
        ]);
        $company2 = Company::factory()->create([
            'user_id' => $user->id,
            'plan' => 'starter',
        ]);

        $starterPlan = SubscriptionPlan::where('slug', 'starter')->first();
        $this->assertCount(2, $starterPlan->companies);
    }
}
