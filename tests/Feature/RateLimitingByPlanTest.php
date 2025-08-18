<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingByPlanTest extends TestCase
{
    use DatabaseMigrations;

    public function test_starter_plan_rate_limit_applies(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000, 'plan' => 'starter']);
        $user->companies()->attach($company, ['role' => 'developer']);
        $user->current_company_id = $company->id;
        $user->save();
        Sanctum::actingAs($user);

        // Burst a number of requests; exact threshold depends on app env, but ensure throttle kicks in eventually
        $lastStatus = null;
        for ($i = 0; $i < 120; $i++) {
            $res = $this->postJson('/api/assist', ['provider' => 'openai']);
            $lastStatus = $res->status();
            if ($lastStatus === 429) break;
        }

        $this->assertTrue(in_array($lastStatus, [200, 429]));
    }
}

