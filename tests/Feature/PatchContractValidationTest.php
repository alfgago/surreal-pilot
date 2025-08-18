<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatchContractValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): array
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        $user->companies()->attach($company, ['role' => 'developer']);
        $user->current_company_id = $company->id;
        $user->save();
        Sanctum::actingAs($user);
        return [$user, $company];
    }

    public function test_valid_patch_schema(): void
    {
        [$user, $company] = $this->actingUser();
        $company->update(['engine_hmac_secret' => 'secret']);

        $payload = [
            'patch' => [
                'v' => '1.0',
                'engine' => 'playcanvas',
                'intent' => 'scene_edit',
                'actions' => [['type' => 'add-entity', 'name' => 'Enemy']],
                'constraints' => ['dryRun' => true, 'maxOps' => 50],
            ],
        ];

        $body = json_encode($payload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, 'secret');

        $res = $this->withHeaders([
            'X-Company-Id' => (string) $company->id,
            'X-Surreal-Timestamp' => $ts,
            'X-Surreal-Signature' => $sig,
        ])->postJson('/api/patch/validate', $payload);
        $res->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_rejects_exceeding_max_ops(): void
    {
        [$user, $company] = $this->actingUser();
        $company->update(['engine_hmac_secret' => 'secret']);

        $actions = array_fill(0, 60, ['type' => 'noop']);
        $payload = [
            'patch' => [
                'v' => '1.0',
                'engine' => 'ue',
                'intent' => 'refactor',
                'actions' => $actions,
                'constraints' => ['dryRun' => true, 'maxOps' => 50],
            ],
        ];

        $body = json_encode($payload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, 'secret');

        $res = $this->withHeaders([
            'X-Company-Id' => (string) $company->id,
            'X-Surreal-Timestamp' => $ts,
            'X-Surreal-Signature' => $sig,
        ])->postJson('/api/patch/validate', $payload);
        $res->assertStatus(422)->assertJson(['error' => 'too_many_operations']);
    }

    public function test_undo_requires_existing_patch(): void
    {
        [$user, $company] = $this->actingUser();
        $company->update(['engine_hmac_secret' => 'secret']);

        // Create a PlayCanvas workspace for the company
        $workspace = \App\Models\Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3000,
            'status' => 'ready',
        ]);

        $body = json_encode(['workspace_id' => $workspace->id, 'patch_id' => 'missing']);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, 'secret');

        $res = $this->withHeaders([
            'X-Company-Id' => (string) $company->id,
            'X-Surreal-Timestamp' => $ts,
            'X-Surreal-Signature' => $sig,
        ])->postJson('/api/patch/undo', [
            'workspace_id' => $workspace->id,
            'patch_id' => 'missing',
        ]);

        $res->assertStatus(404);
    }
}

