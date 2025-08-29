<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->testUser = User::factory()->create([
            'name' => 'Alfredo Test',
            'email' => 'alfredo@5e.cr',
            'password' => Hash::make('Test123!'),
        ]);

        // Create a company for the test user
        $company = Company::factory()->create([
            'name' => 'Test Company',
            'user_id' => $this->testUser->id,
            'credits' => 1000,
            'plan' => 'starter',
            'monthly_credit_limit' => 1000,
            'personal_company' => true,
        ]);

        $this->testUser->update(['current_company_id' => $company->id]);
        $company->users()->attach($this->testUser->id, ['role' => 'owner']);
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Auth/Login')
        );
    }

    public function test_register_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Auth/Register')
        );
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $response = $this->post('/login', [
            'email' => 'alfredo@5e.cr',
            'password' => 'Test123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/engine-selection');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'alfredo@5e.cr',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/engine-selection');

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);

        // Verify company was created
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->currentCompany);
        $this->assertEquals('New User Studio', $user->currentCompany->name);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_registration_requires_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_registration_requires_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_users_can_logout(): void
    {
        $this->actingAs($this->testUser);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Auth/ForgotPassword')
        );
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'alfredo@5e.cr',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $token = \Illuminate\Support\Facades\Password::createToken($this->testUser);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'alfredo@5e.cr',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
    }

    public function test_authenticated_users_are_redirected_from_auth_pages(): void
    {
        $this->actingAs($this->testUser);

        $loginResponse = $this->get('/login');
        $registerResponse = $this->get('/register');

        // Should redirect authenticated users away from auth pages
        $loginResponse->assertRedirect();
        $registerResponse->assertRedirect();
    }

    public function test_remember_me_functionality(): void
    {
        $response = $this->post('/login', [
            'email' => 'alfredo@5e.cr',
            'password' => 'Test123!',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        
        // Verify remember token was set
        $user = User::where('email', 'alfredo@5e.cr')->first();
        $this->assertNotNull($user->remember_token);
    }
}