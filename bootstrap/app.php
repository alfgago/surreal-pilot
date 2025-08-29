<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load desktop routes ONLY when running in NativePHP desktop app
            // Check for NATIVE_PHP environment variable or specific desktop context
            if (class_exists(\Native\Laravel\Facades\Window::class) &&
                !app()->runningInConsole() &&
                !app()->environment(['testing', 'dusk.local']) &&
                (env('NATIVE_PHP', false) || isset($_SERVER['NATIVE_PHP']))) {
                Route::middleware('web')
                    ->group(base_path('routes/desktop.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'resolve.ai.driver' => \App\Http\Middleware\ResolveAiDriver::class,
            'check.developer.role' => \App\Http\Middleware\CheckDeveloperRole::class,
            'track.mcp.actions' => \App\Http\Middleware\TrackMcpActions::class,
            'verify.engine.hmac' => \App\Http\Middleware\VerifyEngineHmac::class,
            'plan.capability' => \App\Http\Middleware\CheckPlanCapability::class,
            'auth.sanctum_or_web' => \App\Http\Middleware\AuthSanctumOrWeb::class,
            'api.error.handling' => \App\Http\Middleware\ApiErrorHandling::class,
            'validate.engine.compatibility' => \App\Http\Middleware\ValidateEngineCompatibility::class,
            'validate.workspace.access' => \App\Http\Middleware\ValidateWorkspaceAccess::class,
        ]);

        // Add API error handling middleware to all API routes
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'api.error.handling',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\ApiException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Track the error
                $errorMonitoring = app(\App\Services\ErrorMonitoringService::class);
                $errorMonitoring->trackError(
                    $e->getErrorCode(),
                    $e->getMessage(),
                    $request->user(),
                    $request->user()?->currentCompany,
                    $e->getErrorData()
                );

                return $e->toJsonResponse();
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Don't handle authentication exceptions - let Laravel handle them
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return null;
                }

                // Return standard Laravel validation JSON structure for Feature tests
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ], 422);
                }

                $errorHandler = app(\App\Services\ApiErrorHandler::class);
                $errorMonitoring = app(\App\Services\ErrorMonitoringService::class);

                // Track the error
                $errorMonitoring->trackError(
                    'general_error',
                    $e->getMessage(),
                    $request->user(),
                    $request->user()?->currentCompany,
                    [
                        'exception_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                );

                return $errorHandler->handleGeneralError($e);
            }
        });
    })->create();
