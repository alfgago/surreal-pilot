<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use DatabaseMigrations;

    private RolePermissionService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = app(RolePermissionService::class);
    }

    public function test_company_owner_can_access_ai_features(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->switchCompany($company);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(200);
    }

    public function test_developer_role_can_access_ai_features(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        
        $developer = User::factory()->create();
        $company->users()->attach($developer, ['role' => 'developer']);
        $developer->switchCompany($company);

        Sanctum::actingAs($developer);

        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_role_can_access_ai_features(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        
        $admin = User::factory()->create();
        $company->users()->attach($admin, ['role' => 'admin']);
        $admin->switchCompany($company);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(200);
    }

    public function test_viewer_role_cannot_access_ai_features(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        
        $viewer = User::factory()->create();
        $company->users()->attach($viewer, ['role' => 'viewer']);
        $viewer->switchCompany($company);

        Sanctum::actingAs($viewer);

        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'access_denied',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
            ]);
    }

    public function test_user_without_role_cannot_access_ai_features(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        
        $user = User::factory()->create();
        // User is not attached to company with any role
        $user->switchCompany($company);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_ai_features(): void
    {
        $response = $this->postJson('/api/assist', [
            'original_provider' => 'openai',
        ]);

        $response->assertStatus(401);
    }

    public function test_role_info_endpoint_returns_correct_information(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->switchCompany($company);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/role-info');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'company' => ['id', 'name', 'credits'],
                    'role_info' => [
                        'role',
                        'role_display_name',
                        'permissions',
                        'is_owner',
                        'can_access_ai',
                    ],
                    'available_roles',
                ],
            ])
            ->assertJson([
                'data' => [
                    'role_info' => [
                        'role' => 'owner',
                        'is_owner' => true,
                        'can_access_ai' => true,
                    ],
                ],
            ]);
    }

    public function test_role_service_correctly_identifies_permissions(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);

        // Test owner permissions
        $this->assertTrue($this->roleService->canAccessAI($owner, $company));
        $this->assertTrue($this->roleService->hasPermission($owner, $company, 'chat'));
        $this->assertTrue($this->roleService->hasPermission($owner, $company, 'assist'));
        $this->assertTrue($this->roleService->hasPermission($owner, $company, 'manage_credits'));

        // Test developer permissions
        $developer = User::factory()->create();
        $company->users()->attach($developer, ['role' => 'developer']);
        
        $this->assertTrue($this->roleService->canAccessAI($developer, $company));
        $this->assertTrue($this->roleService->hasPermission($developer, $company, 'chat'));
        $this->assertTrue($this->roleService->hasPermission($developer, $company, 'assist'));
        $this->assertFalse($this->roleService->hasPermission($developer, $company, 'manage_credits'));

        // Test viewer permissions
        $viewer = User::factory()->create();
        $company->users()->attach($viewer, ['role' => 'viewer']);
        
        $this->assertFalse($this->roleService->canAccessAI($viewer, $company));
        $this->assertFalse($this->roleService->hasPermission($viewer, $company, 'chat'));
        $this->assertFalse($this->roleService->hasPermission($viewer, $company, 'assist'));
    }
}