<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
	/**
	 * Register the exception handling callbacks for the application.
	 */
	public function register(): void
	{
		$this->reportable(function (Throwable $e) {
			// Default reporting
		});
	}

	/**
	 * Render an exception into an HTTP response.
	 */
	public function render($request, Throwable $e)
	{
		if ($e instanceof ValidationException) {
			return response()->json([
				'message' => $e->getMessage(),
				'errors' => $e->errors(),
			], 422);
		}

		if ($e instanceof AuthenticationException && $request->is('api/*')) {
			return response()->json(['message' => 'Unauthenticated.'], 401);
		}

		return parent::render($request, $e);
	}
}


