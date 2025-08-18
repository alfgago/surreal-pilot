<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_insufficient_credits_error_response()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 0]);
        $user->companies()->attach($company, ['role' => 'developer']);
        $user->update(['current_company_id' => $company->id]);
        $user->refresh();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, this is a test message that should require credits.']
            ],
            'provider' => 'openai',
        ]);

        // Debug the response
        if ($response->getStatusCode() !== 402) {
            dump('Status: ' . $response->getStatusCode());
            dump('Content: ' . $response->getContent());
        }
        
        $response->assertStatus(402)
            ->assertJson([
                'error' => 'insufficient_credits',
                'error_code' => 'INSUFFICIENT_CREDITS',
            ])
            ->assertJsonStructure([
                'error',
                'error_code',
                'message',
                'user_message',
                'data' => [
                    'credits_available',
                    'estimated_tokens_needed',
                    'credits_needed',
                    'actions',
                ],
            ]);
    }

    public function test_authentication_required_error()
    {
        // Test the user endpoint which should require authentication
        $response = $this->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_company_not_found_error()
    {
        $user = User::factory()->create();
        // Don't attach any company to the user
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/role-info');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'no_active_company',
                'error_code' => 'NO_ACTIVE_COMPANY',
            ])
            ->assertJsonStructure([
                'error',
                'error_code',
                'message',
                'user_message',
                'data' => [
                    'actions',
                ],
            ]);
    }

    public function test_validation_error_response()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        $user->companies()->attach($company);
        $user->update(['current_company_id' => $company->id]);

        Sanctum::actingAs($user);

        // Send invalid data (missing messages)
        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            // Missing required 'messages' field
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_error_logging_in_database()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 0]);
        $user->companies()->attach($company);
        $user->update(['current_company_id' => $company->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'provider' => 'openai',
        ]);

        // Check that error was logged in database
        $this->assertDatabaseHas('api_error_logs', [
            'error_type' => 'insufficient_credits',
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_provider_unavailable_middleware_error()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        $user->companies()->attach($company);
        $user->update(['current_company_id' => $company->id]);

        Sanctum::actingAs($user);

        // Request a provider that doesn't exist or isn't configured
        $response = $this->postJson('/api/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'provider' => 'nonexistent_provider',
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'provider_unavailable',
                'error_code' => 'PROVIDER_UNAVAILABLE',
            ])
            ->assertJsonStructure([
                'error',
                'error_code',
                'message',
                'user_message',
                'data' => [
                    'requested_provider',
                    'available_providers',
                    'fallback_suggestions',
                    'actions',
                ],
            ]);
    }

    public function test_role_permission_error()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        $user->companies()->attach($company);
        $user->update(['current_company_id' => $company->id]);

        // Don't assign any roles to the user, so they won't have developer permissions

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'provider' => 'openai',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'access_denied',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
            ])
            ->assertJsonStructure([
                'error',
                'error_code',
                'message',
                'user_message',
                'data' => [
                    'actions',
                ],
            ]);
    }
}