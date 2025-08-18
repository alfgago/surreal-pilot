<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EngineHmacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_missing_hmac_headers(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 100]);
        $user->companies()->attach($company, ['role' => 'developer']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/mcp-command', [
            'workspace_id' => 1,
            'command' => 'noop',
        ]);

        $res->assertStatus(401);
    }

    public function test_accepts_valid_hmac(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 100, 'engine_hmac_secret' => 'secret']);
        $user->companies()->attach($company, ['role' => 'developer']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $payload = json_encode(['workspace_id' => 1, 'command' => 'noop']);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $payload, 'secret');

        $res = $this->withHeaders([
            'X-Company-Id' => (string) $company->id,
            'X-Surreal-Timestamp' => $ts,
            'X-Surreal-Signature' => $sig,
        ])->postJson('/api/mcp-command', json_decode($payload, true));

        // May still fail downstream validation, but should not be 401 due to HMAC
        $this->assertTrue(in_array($res->status(), [200, 400, 404, 422, 500]));
    }
}

