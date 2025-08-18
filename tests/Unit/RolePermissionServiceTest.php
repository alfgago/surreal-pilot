<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RolePermissionService $roleService;
    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->roleService = new RolePermissionService();
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
    }

    public function test_owner_has_all_permissions()
    {
        // Make user the owner of the company
        $this->company->update(['user_id' => $this->user->id]);

        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'chat'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'assist'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'manage_credits'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'manage_users'));
    }

    public function test_admin_has_all_permissions()
    {
        // Attach user to company with admin role
        $this->user->companies()->attach($this->company, ['role' => 'admin']);

        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'chat'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'assist'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'manage_credits'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'manage_users'));
    }

    public function test_developer_has_limited_permissions()
    {
        // Attach user to company with developer role
        $this->user->companies()->attach($this->company, ['role' => 'developer']);

        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'chat'));
        $this->assertTrue($this->roleService->hasPermission($this->user, $this->company, 'assist'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_credits'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_users'));
    }

    public function test_viewer_has_no_ai_permissions()
    {
        // Attach user to company with viewer role
        $this->user->companies()->attach($this->company, ['role' => 'viewer']);

        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'chat'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'assist'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_credits'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_users'));
    }

    public function test_user_without_role_has_no_permissions()
    {
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'chat'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'assist'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_credits'));
        $this->assertFalse($this->roleService->hasPermission($this->user, $this->company, 'manage_users'));
    }

    public function test_can_access_ai_returns_true_for_authorized_users()
    {
        // Test owner
        $this->company->update(['user_id' => $this->user->id]);
        $this->assertTrue($this->roleService->canAccessAI($this->user, $this->company));

        // Test developer
        $this->company->update(['user_id' => null]);
        $this->user->companies()->attach($this->company, ['role' => 'developer']);
        $this->assertTrue($this->roleService->canAccessAI($this->user, $this->company));
    }

    public function test_can_access_ai_returns_false_for_unauthorized_users()
    {
        // Test viewer
        $this->user->companies()->attach($this->company, ['role' => 'viewer']);
        $this->assertFalse($this->roleService->canAccessAI($this->user, $this->company));

        // Test user without role
        $this->user->companies()->detach($this->company);
        $this->assertFalse($this->roleService->canAccessAI($this->user, $this->company));
    }

    public function test_get_user_role_returns_owner_for_company_owner()
    {
        $this->company->update(['user_id' => $this->user->id]);

        $role = $this->roleService->getUserRole($this->user, $this->company);

        $this->assertEquals('owner', $role);
    }

    public function test_get_user_role_returns_pivot_role_for_members()
    {
        $this->user->companies()->attach($this->company, ['role' => 'developer']);

        $role = $this->roleService->getUserRole($this->user, $this->company);

        $this->assertEquals('developer', $role);
    }

    public function test_get_user_role_returns_null_for_non_members()
    {
        $role = $this->roleService->getUserRole($this->user, $this->company);

        $this->assertNull($role);
    }

    public function test_get_available_roles_returns_all_roles()
    {
        $roles = $this->roleService->getAvailableRoles();

        $expected = [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'developer' => 'Developer',
            'viewer' => 'Viewer',
        ];

        $this->assertEquals($expected, $roles);
    }

    public function test_get_role_permissions_returns_correct_permissions()
    {
        $ownerPermissions = $this->roleService->getRolePermissions('owner');
        $this->assertEquals(['chat', 'assist', 'manage_credits', 'manage_users'], $ownerPermissions);

        $adminPermissions = $this->roleService->getRolePermissions('admin');
        $this->assertEquals(['chat', 'assist', 'manage_credits', 'manage_users'], $adminPermissions);

        $developerPermissions = $this->roleService->getRolePermissions('developer');
        $this->assertEquals(['chat', 'assist'], $developerPermissions);

        $viewerPermissions = $this->roleService->getRolePermissions('viewer');
        $this->assertEquals([], $viewerPermissions);

        $unknownPermissions = $this->roleService->getRolePermissions('unknown');
        $this->assertEquals([], $unknownPermissions);
    }

    public function test_is_valid_role_returns_correct_boolean()
    {
        $this->assertTrue($this->roleService->isValidRole('owner'));
        $this->assertTrue($this->roleService->isValidRole('admin'));
        $this->assertTrue($this->roleService->isValidRole('developer'));
        $this->assertTrue($this->roleService->isValidRole('viewer'));
        $this->assertFalse($this->roleService->isValidRole('invalid'));
        $this->assertFalse($this->roleService->isValidRole(''));
    }

    public function test_get_user_permissions_returns_role_permissions()
    {
        // Test developer permissions
        $this->user->companies()->attach($this->company, ['role' => 'developer']);

        $permissions = $this->roleService->getUserPermissions($this->user, $this->company);

        $this->assertEquals(['chat', 'assist'], $permissions);
    }

    public function test_get_user_permissions_returns_empty_for_no_role()
    {
        $permissions = $this->roleService->getUserPermissions($this->user, $this->company);

        $this->assertEquals([], $permissions);
    }

    public function test_format_role_info_returns_complete_information()
    {
        // Test for owner
        $this->company->update(['user_id' => $this->user->id]);

        $roleInfo = $this->roleService->formatRoleInfo($this->user, $this->company);

        $this->assertEquals('owner', $roleInfo['role']);
        $this->assertEquals('Owner', $roleInfo['role_display_name']);
        $this->assertEquals(['chat', 'assist', 'manage_credits', 'manage_users'], $roleInfo['permissions']);
        $this->assertTrue($roleInfo['is_owner']);
        $this->assertTrue($roleInfo['can_access_ai']);
    }

    public function test_format_role_info_for_developer()
    {
        $this->user->companies()->attach($this->company, ['role' => 'developer']);

        $roleInfo = $this->roleService->formatRoleInfo($this->user, $this->company);

        $this->assertEquals('developer', $roleInfo['role']);
        $this->assertEquals('Developer', $roleInfo['role_display_name']);
        $this->assertEquals(['chat', 'assist'], $roleInfo['permissions']);
        $this->assertFalse($roleInfo['is_owner']);
        $this->assertTrue($roleInfo['can_access_ai']);
    }

    public function test_format_role_info_for_viewer()
    {
        $this->user->companies()->attach($this->company, ['role' => 'viewer']);

        $roleInfo = $this->roleService->formatRoleInfo($this->user, $this->company);

        $this->assertEquals('viewer', $roleInfo['role']);
        $this->assertEquals('Viewer', $roleInfo['role_display_name']);
        $this->assertEquals([], $roleInfo['permissions']);
        $this->assertFalse($roleInfo['is_owner']);
        $this->assertFalse($roleInfo['can_access_ai']);
    }

    public function test_format_role_info_for_non_member()
    {
        $roleInfo = $this->roleService->formatRoleInfo($this->user, $this->company);

        $this->assertNull($roleInfo['role']);
        $this->assertEquals('Unknown', $roleInfo['role_display_name']);
        $this->assertEquals([], $roleInfo['permissions']);
        $this->assertFalse($roleInfo['is_owner']);
        $this->assertFalse($roleInfo['can_access_ai']);
    }
}