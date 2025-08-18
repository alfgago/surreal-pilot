<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\ApiErrorHandler;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyEngineHmac
{
    public function __construct(private ApiErrorHandler $errorHandler) {}

    /**
     * Validate HMAC signature for engine-originated requests (UE plugin / Bridge / MCP).
     * Expects headers: X-Company-Id, X-Surreal-Timestamp, X-Surreal-Signature
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-Id');
        $timestamp = $request->header('X-Surreal-Timestamp');
        $signature = $request->header('X-Surreal-Signature');

        if (!$companyId || !$timestamp || !$signature) {
            return $this->errorHandler->handleAuthenticationError('Missing HMAC headers', [
                'middleware' => 'VerifyEngineHmac',
            ]);
        }

        $company = Company::find($companyId);
        if (!$company) {
            return $this->errorHandler->handleAuthenticationError('Invalid company', [
                'middleware' => 'VerifyEngineHmac',
                'company_id' => $companyId,
            ]);
        }

        // Secret from env or company settings (prefer company-specific secret when available)
        $secret = $company->engine_hmac_secret ?? config('app.engine_hmac_secret');
        if (!$secret) {
            Log::warning('HMAC verification skipped due to missing secret', [
                'company_id' => $company->id,
            ]);
            return $next($request);
        }

        // Basic replay protection: allow window of 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            return $this->errorHandler->handleAuthenticationError('Stale request timestamp', [
                'middleware' => 'VerifyEngineHmac',
            ]);
        }

        $payload = $request->getContent();
        $computed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        if (!hash_equals($computed, $signature)) {
            return $this->errorHandler->handleAuthenticationError('Invalid HMAC signature', [
                'middleware' => 'VerifyEngineHmac',
            ]);
        }

        return $next($request);
    }
}

