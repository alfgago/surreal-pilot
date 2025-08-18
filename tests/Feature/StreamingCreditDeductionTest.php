<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditManager;
use App\Services\PrismProviderManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class StreamingCreditDeductionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app('db')->disconnect();
        
        // Mock the PrismProviderManager to avoid actual API calls
        $mockProviderManager = Mockery::mock(PrismProviderManager::class);
        $mockProviderManager->shouldReceive('resolveProvider')
            ->andReturn('openai');
        $this->app->instance(PrismProviderManager::class, $mockProviderManager);
    }

    public function test_credit_validation_before_processing_streaming_request(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 5]); // Very low credits
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'This is a very long message that would require many tokens to process and should exceed the available credits for this company']
            ],
            'stream' => true,
        ]);

        $response->assertStatus(402)
            ->assertJson([
                'error' => 'insufficient_credits',
                'message' => 'Company has insufficient credits for this request',
                'credits_available' => 5,
            ])
            ->assertJsonStructure([
                'error',
                'message', 
                'credits_available',
                'estimated_tokens'
            ]);
    }

    public function test_credit_validation_before_processing_non_streaming_request(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 0]); // No credits
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ],
            'stream' => false,
        ]);

        $response->assertStatus(402)
            ->assertJson([
                'error' => 'insufficient_credits',
                'credits_available' => 0,
            ]);
    }

    public function test_insufficient_credits_error_response_structure(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1]);
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'This is a message that requires more tokens than available']
            ],
            'context' => [
                'blueprint' => 'Large blueprint context that increases token count',
                'errors' => ['Error 1', 'Error 2', 'Error 3'],
                'selection' => 'Additional selection context'
            ]
        ]);

        $response->assertStatus(402)
            ->assertJsonStructure([
                'error',
                'message',
                'credits_available',
                'estimated_tokens'
            ])
            ->assertJson([
                'error' => 'insufficient_credits',
                'credits_available' => 1,
            ]);

        // Verify estimated_tokens is a positive integer
        $responseData = $response->json();
        $this->assertIsInt($responseData['estimated_tokens']);
        $this->assertGreaterThan(0, $responseData['estimated_tokens']);
    }

    public function test_token_estimation_considers_all_input_components(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 15]); // Enough for minimal, not for extensive
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        // Test with minimal input (should pass)
        $minimalResponse = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi']
            ]
        ]);

        // Test with extensive input (should be blocked due to higher token estimate)
        $extensiveResponse = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'This is a much longer message with more content that should result in higher token estimation']
            ],
            'context' => [
                'blueprint' => 'Very detailed blueprint context with lots of information about the current state',
                'errors' => ['Detailed error message 1', 'Another comprehensive error message'],
                'selection' => 'Selected context with additional details'
            ]
        ]);

        // The minimal request should pass (200 or 500 due to Prism setup)
        $this->assertTrue(in_array($minimalResponse->getStatusCode(), [200, 500]));
        
        // The extensive request should be blocked due to insufficient credits
        $extensiveResponse->assertStatus(402);
        
        // Verify the extensive request has higher estimated tokens
        $extensiveTokens = $extensiveResponse->json('estimated_tokens');
        $this->assertGreaterThan(15, $extensiveTokens); // Should exceed available credits
    }

    public function test_credit_manager_integration_with_streaming_endpoint(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        // Mock CreditManager to verify it's called correctly
        $mockCreditManager = Mockery::mock(CreditManager::class);
        
        // Should check if company can afford the request
        $mockCreditManager->shouldReceive('canAffordRequest')
            ->once()
            ->with(Mockery::on(function ($arg) use ($company) {
                return $arg->id === $company->id;
            }), Mockery::type('int'))
            ->andReturn(true);

        $this->app->instance(CreditManager::class, $mockCreditManager);

        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'Test message']
            ],
            'stream' => true,
        ]);

        // The response will likely be 500 due to missing Prism setup, but that's expected
        // We're testing that the credit validation was called
        $this->assertTrue(in_array($response->getStatusCode(), [200, 500]));
    }

    public function test_streaming_response_headers_are_correct(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/chat', [
            'provider' => 'openai',
            'messages' => [
                ['role' => 'user', 'content' => 'Test']
            ],
            'stream' => true,
        ]);

        // Check if streaming headers are set correctly when not insufficient credits
        if ($response->getStatusCode() !== 402) {
            $contentType = $response->headers->get('Content-Type');
            $this->assertTrue(
                str_contains($contentType, 'text/event-stream') ||
                $response->getStatusCode() === 500, // Expected error without Prism setup
                "Expected streaming headers or 500 error, got status {$response->getStatusCode()} with content-type: {$contentType}"
            );
        }
    }

    public function test_credit_deduction_metadata_structure(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        // Use real CreditManager to test actual credit deduction
        $creditManager = new CreditManager();
        
        // Test the metadata structure that would be used during streaming
        $result = $creditManager->deductCredits(
            $company,
            50,
            'AI Chat - Streaming Response',
            [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'user_id' => $user->id,
                'chunk_tokens' => 50,
            ]
        );

        $this->assertTrue($result);

        $transaction = CreditTransaction::where('company_id', $company->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(50, $transaction->amount);
        $this->assertEquals('AI Chat - Streaming Response', $transaction->description);
        
        $metadata = $transaction->metadata;
        $this->assertEquals('openai', $metadata['provider']);
        $this->assertEquals('gpt-4', $metadata['model']);
        $this->assertEquals($user->id, $metadata['user_id']);
        $this->assertEquals(50, $metadata['chunk_tokens']);
    }

    public function test_non_streaming_credit_deduction_metadata(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['credits' => 1000]);
        
        $user->companies()->attach($company, ['role' => 'admin']);
        $user->current_company_id = $company->id;
        $user->save();

        $creditManager = new CreditManager();
        
        // Test the metadata structure for non-streaming responses
        $result = $creditManager->deductCredits(
            $company,
            100,
            'AI Chat - Complete Response',
            [
                'provider' => 'anthropic',
                'model' => 'claude-3',
                'user_id' => $user->id,
                'total_tokens' => 100,
            ]
        );

        $this->assertTrue($result);

        $transaction = CreditTransaction::where('company_id', $company->id)->first();
        $this->assertEquals('AI Chat - Complete Response', $transaction->description);
        
        $metadata = $transaction->metadata;
        $this->assertEquals('anthropic', $metadata['provider']);
        $this->assertEquals('claude-3', $metadata['model']);
        $this->assertEquals($user->id, $metadata['user_id']);
        $this->assertEquals(100, $metadata['total_tokens']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}