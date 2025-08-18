<?php

namespace Tests\Unit;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_plans_are_seeded_correctly(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $plans = SubscriptionPlan::all();
        $this->assertCount(3, $plans);

        // Test Starter plan
        $starter = SubscriptionPlan::where('slug', 'starter')->first();
        $this->assertNotNull($starter);
        $this->assertEquals('Starter', $starter->name);
        $this->assertEquals(1000, $starter->monthly_credits);
        $this->assertEquals(0, $starter->price_cents);

        // Test Pro plan
        $pro = SubscriptionPlan::where('slug', 'pro')->first();
        $this->assertNotNull($pro);
        $this->assertEquals('Pro', $pro->name);
        $this->assertEquals(10000, $pro->monthly_credits);
        $this->assertEquals(2900, $pro->price_cents);

        // Test Enterprise plan
        $enterprise = SubscriptionPlan::where('slug', 'enterprise')->first();
        $this->assertNotNull($enterprise);
        $this->assertEquals('Enterprise', $enterprise->name);
        $this->assertEquals(100000, $enterprise->monthly_credits);
        $this->assertEquals(9900, $enterprise->price_cents);
    }

    public function test_subscription_plan_casts_work_correctly(): void
    {
        $plan = new SubscriptionPlan([
            'name' => 'Test Plan',
            'slug' => 'test',
            'monthly_credits' => '5000',
            'price_cents' => '1999',
        ]);

        $this->assertIsInt($plan->monthly_credits);
        $this->assertIsInt($plan->price_cents);
        $this->assertEquals(5000, $plan->monthly_credits);
        $this->assertEquals(1999, $plan->price_cents);
    }
}
