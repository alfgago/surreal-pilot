<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddEngineTypeHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add engine type information to response headers for UI indicators
        if ($response->headers->get('Content-Type') === 'application/json' || 
            str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            
            // Check if response contains workspace data
            $content = $response->getContent();
            if ($content && is_string($content)) {
                $data = json_decode($content, true);
                
                if (isset($data['data']['engine_type'])) {
                    $response->headers->set('X-Engine-Type', $data['data']['engine_type']);
                    $response->headers->set('X-Engine-Compatibility', 'isolated');
                }
                
                // Handle multiple workspaces
                if (isset($data['data']['workspaces']) && is_array($data['data']['workspaces'])) {
                    $engineTypes = array_unique(array_column($data['data']['workspaces'], 'engine_type'));
                    $response->headers->set('X-Engine-Types', implode(',', $engineTypes));
                    $response->headers->set('X-Engine-Compatibility', 'isolated');
                }
                
                // Add general engine support information
                $response->headers->set('X-Supported-Engines', 'playcanvas,unreal');
                $response->headers->set('X-Cross-Engine-Support', 'false');
            }
        }

        return $response;
    }
}