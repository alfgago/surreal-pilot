<?php

namespace Tests\Unit;

use App\Http\Middleware\ResolveAiDriver;
use App\Models\Company;
use App\Models\User;
use App\Services\PrismProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ResolveAiDriverMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private PrismProviderManager $mockProviderManager;
    private ResolveAiDriver $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockProviderManager = Mockery::mock(PrismProviderManager::class);
        $this->middleware = new ResolveAiDriver($this->mockProviderManager);
    }

    public function test_middleware_resolves_provider_successfully(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $request = Request::create('/api/assist', 'POST', ['provider' => 'openai']);
        $request->setUserResolver(fn() => $user);

        $this->mockProviderManager
            ->shouldReceive('resolveProvider')
            ->with('openai')
            ->once()
            ->andReturn('openai');

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals('openai', $req->input('resolved_provider'));
            $this->assertEquals('openai', $req->input('original_provider'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_handles_provider_fallback(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $request = Request::create('/api/assist', 'POST', ['provider' => 'ollama']);
        $request->setUserResolver(fn() => $user);

        $this->mockProviderManager
            ->shouldReceive('resolveProvider')
            ->with('ollama')
            ->once()
            ->andReturn('openai'); // Fallback to openai

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals('openai', $req->input('resolved_provider'));
            $this->assertEquals('ollama', $req->input('original_provider'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_handles_no_provider_specified(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $request = Request::create('/api/assist', 'POST');
        $request->setUserResolver(fn() => $user);

        $this->mockProviderManager
            ->shouldReceive('resolveProvider')
            ->with(null)
            ->once()
            ->andReturn('openai');

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertEquals('openai', $req->input('resolved_provider'));
            $this->assertNull($req->input('original_provider'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_handles_provider_unavailable_exception(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $request = Request::create('/api/assist', 'POST', ['provider' => 'invalid']);
        $request->setUserResolver(fn() => $user);

        $this->mockProviderManager
            ->shouldReceive('resolveProvider')
            ->with('invalid')
            ->once()
            ->andThrow(new \Exception('No AI providers are currently available'));

        $this->mockProviderManager
            ->shouldReceive('getAvailableProviders')
            ->once()
            ->andReturn(['openai', 'anthropic']);

        $response = $this->middleware->handle($request, function ($req) {
            // This should not be called
            return response()->json(['success' => true]);
        });

        $this->assertEquals(503, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('provider_unavailable', $responseData['error']);
        $this->assertArrayHasKey('available_providers', $responseData);
    }

    public function test_middleware_logs_provider_resolution(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('AI provider resolved', Mockery::type('array'));

        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();

        $request = Request::create('/api/assist', 'POST', ['provider' => 'openai']);
        $request->setUserResolver(fn() => $user);

        $this->mockProviderManager
            ->shouldReceive('resolveProvider')
            ->with('openai')
            ->once()
            ->andReturn('openai');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
