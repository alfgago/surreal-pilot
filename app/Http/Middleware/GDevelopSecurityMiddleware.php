<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class GDevelopSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting per user
        $key = 'gdevelop-requests:' . ($request->user()?->id ?? $request->ip());
        
        if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute
            Log::warning('GDevelop rate limit exceeded', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'endpoint' => $request->path()
            ]);
            
            return response()->json([
                'error' => 'Too many requests. Please try again later.'
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Validate session ownership
        if ($request->route('sessionId')) {
            $sessionId = $request->route('sessionId');
            
            if (!$this->validateSessionOwnership($request, $sessionId)) {
                Log::warning('Unauthorized GDevelop session access attempt', [
                    'user_id' => $request->user()?->id,
                    'session_id' => $sessionId,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'error' => 'Unauthorized access to session.'
                ], 403);
            }
        }

        // Validate request size
        if ($request->getContentLength() > 10 * 1024 * 1024) { // 10MB limit
            Log::warning('GDevelop request size exceeded', [
                'user_id' => $request->user()?->id,
                'size' => $request->getContentLength(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'Request size too large.'
            ], 413);
        }

        // Sanitize input data
        if ($request->isMethod('POST') && $request->has('message')) {
            $message = $request->input('message');
            
            if (!$this->validateChatMessage($message)) {
                Log::warning('Invalid GDevelop chat message', [
                    'user_id' => $request->user()?->id,
                    'message_length' => strlen($message),
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'error' => 'Invalid message content.'
                ], 400);
            }
        }

        return $next($request);
    }

    /**
     * Validate session ownership
     */
    private function validateSessionOwnership(Request $request, string $sessionId): bool
    {
        if (!$request->user()) {
            return false;
        }

        // Check if session belongs to user's workspace
        $session = \App\Models\GDevelopGameSession::where('session_id', $sessionId)
            ->whereHas('workspace', function ($query) use ($request) {
                $query->where('company_id', $request->user()->company_id);
            })
            ->first();

        return $session !== null;
    }

    /**
     * Validate chat message content
     */
    private function validateChatMessage(string $message): bool
    {
        // Check message length
        if (strlen($message) > 5000) {
            return false;
        }

        // Check for potentially malicious content
        $dangerousPatterns = [
            '/\<script\>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/exec\(/i',
            '/system\(/i',
            '/shell_exec\(/i',
            '/passthru\(/i',
            '/file_get_contents\(/i',
            '/file_put_contents\(/i',
            '/fopen\(/i',
            '/fwrite\(/i',
            '/curl_exec\(/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return false;
            }
        }

        return true;
    }
}