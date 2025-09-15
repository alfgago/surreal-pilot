<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureGDevelopEnabled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EnsureGDevelopEnabledMiddlewareTest extends TestCase
{
    private EnsureGDevelopEnabled $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureGDevelopEnabled();
    }

    public function test_allows_request_when_gdevelop_enabled(): void
    {
        Config::set('gdevelop.enabled', true);

        $request = Request::create('/api/gdevelop/chat', 'POST');
        $next = function ($request) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_blocks_request_when_gdevelop_disabled(): void
    {
        Config::set('gdevelop.enabled', false);

        $request = Request::create('/api/gdevelop/chat', 'POST');
        $next = function ($request) {
            return new Response('Success', 200);
        };

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('GDevelop integration is disabled');

        $this->middleware->handle($request, $next);
    }

    public function test_returns_json_error_for_api_requests(): void
    {
        Config::set('gdevelop.enabled', false);

        $request = Request::create('/api/gdevelop/chat', 'POST');
        $request->headers->set('Accept', 'application/json');
        
        $next = function ($request) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(503, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('GDevelop integration is disabled', $content['error']);
        $this->assertEquals('GDevelop features are not available. Please enable GDEVELOP_ENABLED in your environment configuration.', $content['message']);
        $this->assertEquals('GDEVELOP_DISABLED', $content['code']);
    }

    public function test_returns_json_error_for_xhr_requests(): void
    {
        Config::set('gdevelop.enabled', false);

        $request = Request::create('/api/gdevelop/chat', 'POST');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        
        $next = function ($request) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(503, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('GDevelop integration is disabled', $content['error']);
        $this->assertEquals('GDEVELOP_DISABLED', $content['code']);
    }

    public function test_handles_missing_config_gracefully(): void
    {
        Config::set('gdevelop.enabled', null);

        $request = Request::create('/api/gdevelop/chat', 'POST');
        $next = function ($request) {
            return new Response('Success', 200);
        };

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('GDevelop integration is disabled');

        $this->middleware->handle($request, $next);
    }
}