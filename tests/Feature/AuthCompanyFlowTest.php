<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthCompanyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_company_and_redirects_to_chat(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/chat');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $user = User::whereEmail('test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->current_company_id);
        $this->assertDatabaseHas('companies', ['id' => $user->current_company_id]);
    }

    public function test_login_redirects_to_chat(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $response->assertRedirect('/chat');
    }

    public function test_company_settings_update_and_invite(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'name' => 'Old Co']);
        $user->forceFill(['current_company_id' => $company->id])->save();

        $this->actingAs($user)
            ->patch('/company/settings', ['name' => 'New Co'])
            ->assertRedirect();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'New Co']);

        $this->actingAs($user)
            ->post('/company/invite', ['email' => 'invitee@example.com', 'role' => 'member'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'invitee@example.com']);
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id]);
    }
}


