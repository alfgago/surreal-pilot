<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\ApiErrorHandler;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorHandlerTest extends TestCase
{
    use RefreshDatabase;

    private ApiErrorHandler $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorHandler = new ApiErrorHandler();
    }

    public function test_handles_insufficient_credits_error()
    {
        $company = Company::factory()->create([
            'credits' => 50,
            'plan' => 'starter',
        ]);

        $response = $this->errorHandler->handleInsufficientCredits($company, 100);

        $this->assertEquals(402, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_credits', $data['error']);
        $this->assertEquals('INSUFFICIENT_CREDITS', $data['error_code']);
        $this->assertEquals(50, $data['data']['credits_available']);
        $this->assertEquals(100, $data['data']['estimated_tokens_needed']);
        $this->assertEquals(50, $data['data']['credits_needed']);
        $this->assertArrayHasKey('actions', $data['data']);
    }

    public function test_handles_provider_unavailable_error()
    {
        $response = $this->errorHandler->handleProviderUnavailable(
            'openai',
            ['anthropic', 'gemini']
        );

        $this->assertEquals(503, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('provider_unavailable', $data['error']);
        $this->assertEquals('PROVIDER_UNAVAILABLE', $data['error_code']);
        $this->assertEquals('openai', $data['data']['requested_provider']);
        $this->assertEquals(['anthropic', 'gemini'], $data['data']['available_providers']);
        $this->assertArrayHasKey('fallback_suggestions', $data['data']);
    }

    public function test_handles_authentication_error()
    {
        $response = $this->errorHandler->handleAuthenticationError();

        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('authentication_required', $data['error']);
        $this->assertEquals('AUTHENTICATION_REQUIRED', $data['error_code']);
        $this->assertArrayHasKey('actions', $data['data']);
    }

    public function test_handles_authorization_error()
    {
        $response = $this->errorHandler->handleAuthorizationError(
            'Insufficient permissions',
            ['required_role' => 'developer']
        );

        $this->assertEquals(403, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('access_denied', $data['error']);
        $this->assertEquals('INSUFFICIENT_PERMISSIONS', $data['error_code']);
        $this->assertEquals('developer', $data['data']['required_role']);
    }

    public function test_handles_rate_limit_error()
    {
        $response = $this->errorHandler->handleRateLimitError(60);

        $this->assertEquals(429, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('rate_limit_exceeded', $data['error']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $data['error_code']);
        $this->assertEquals(60, $data['data']['retry_after']);
        $this->assertEquals('1 minute', $data['data']['retry_after_human']);
    }

    public function test_handles_validation_error()
    {
        $errors = [
            'email' => ['The email field is required.'],
            'password' => ['The password must be at least 8 characters.'],
        ];

        $response = $this->errorHandler->handleValidationError($errors);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('validation_failed', $data['error']);
        $this->assertEquals('VALIDATION_FAILED', $data['error_code']);
        $this->assertEquals($errors, $data['data']['errors']);
    }

    public function test_handles_general_error()
    {
        $exception = new Exception('Something went wrong');
        $response = $this->errorHandler->handleGeneralError($exception);

        $this->assertEquals(500, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('internal_server_error', $data['error']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $data['error_code']);
        $this->assertArrayHasKey('error_id', $data['data']);
        $this->assertStringStartsWith('err_', $data['data']['error_id']);
    }

    public function test_handles_provider_api_error()
    {
        $exception = new Exception('API rate limit exceeded');
        $response = $this->errorHandler->handleProviderApiError('openai', $exception);

        $this->assertEquals(502, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('provider_api_error', $data['error']);
        $this->assertEquals('PROVIDER_API_ERROR', $data['error_code']);
        $this->assertEquals('openai', $data['data']['provider']);
    }

    public function test_handles_timeout_error()
    {
        $response = $this->errorHandler->handleTimeoutError(30);

        $this->assertEquals(408, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('request_timeout', $data['error']);
        $this->assertEquals('REQUEST_TIMEOUT', $data['error_code']);
        $this->assertEquals(30, $data['data']['timeout_seconds']);
    }

    public function test_handles_company_not_found()
    {
        $response = $this->errorHandler->handleCompanyNotFound();

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('no_active_company', $data['error']);
        $this->assertEquals('NO_ACTIVE_COMPANY', $data['error_code']);
        $this->assertArrayHasKey('actions', $data['data']);
    }

    public function test_checks_retryable_errors()
    {
        $this->assertTrue($this->errorHandler->isRetryableError('provider_unavailable'));
        $this->assertTrue($this->errorHandler->isRetryableError('timeout_error'));
        $this->assertTrue($this->errorHandler->isRetryableError('rate_limit_exceeded'));
        $this->assertFalse($this->errorHandler->isRetryableError('authentication_error'));
        $this->assertFalse($this->errorHandler->isRetryableError('validation_error'));
    }

    public function test_gets_error_severity()
    {
        $this->assertEquals('low', $this->errorHandler->getErrorSeverity('authentication_error'));
        $this->assertEquals('medium', $this->errorHandler->getErrorSeverity('insufficient_credits'));
        $this->assertEquals('high', $this->errorHandler->getErrorSeverity('streaming_error'));
        $this->assertEquals('critical', $this->errorHandler->getErrorSeverity('general_error'));
        $this->assertEquals('medium', $this->errorHandler->getErrorSeverity('unknown_error'));
    }
}