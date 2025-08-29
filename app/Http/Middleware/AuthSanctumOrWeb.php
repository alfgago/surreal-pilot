<?php

namespace App\Http\Middleware;

use App\Services\ApiErrorHandler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthSanctumOrWeb
{
    public function __construct(private ApiErrorHandler $errorHandler) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Prefer Sanctum user if present
        $user = $request->user();

        // Fall back to web guard session user for web-based API requests
        if (!$user) {
            $user = auth('web')->user();
            if ($user) {
                // Set the user on the request so downstream middlewares see a user
                $request->setUserResolver(fn () => $user);
            }
        }

        if (!$user) {
            // Preserve legacy API format for unauthenticated feature tests
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return $this->errorHandler->handleAuthenticationError('Authentication required');
        }

        return $next($request);
    }
}



