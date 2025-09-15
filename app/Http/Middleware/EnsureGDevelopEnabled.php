<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGDevelopEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('gdevelop.enabled')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'GDevelop integration is disabled',
                    'message' => 'GDevelop features are not available. Please enable GDEVELOP_ENABLED in your environment configuration.',
                    'code' => 'GDEVELOP_DISABLED'
                ], 503);
            }

            abort(503, 'GDevelop integration is disabled');
        }

        return $next($request);
    }
}