<?php

namespace App\Http\Middleware;

use App\Services\CreditManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackMcpActions
{
    public function __construct(
        private CreditManager $creditManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track MCP actions for successful responses
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->trackMcpAction($request, $response);
        }

        return $response;
    }

    /**
     * Track MCP action for credit analytics.
     */
    private function trackMcpAction(Request $request, Response $response): void
    {
        // Check if this is an MCP-related request
        if (!$this->isMcpRequest($request)) {
            return;
        }

        $user = $request->user();
        if (!$user || !$user->company) {
            return;
        }

        $workspace = $this->extractWorkspaceFromRequest($request);
        if (!$workspace) {
            return;
        }

        // Only track PlayCanvas MCP operations for surcharge
        if ($workspace->isPlayCanvas()) {
            Log::info('PlayCanvas MCP action tracked', [
                'user_id' => $user->id,
                'company_id' => $user->company->id,
                'workspace_id' => $workspace->id,
                'engine_type' => $workspace->engine_type,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Check if the request is MCP-related.
     */
    private function isMcpRequest(Request $request): bool
    {
        $mcpEndpoints = [
            'api/mcp/command',
            'api/assist', // When routing to MCP
        ];

        $path = $request->path();
        
        foreach ($mcpEndpoints as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract workspace from request.
     */
    private function extractWorkspaceFromRequest(Request $request): ?\App\Models\Workspace
    {
        // Try to get workspace_id from request
        $workspaceId = $request->input('workspace_id') ?? 
                      $request->route('workspace_id') ?? 
                      $request->route('workspaceId');

        if ($workspaceId) {
            return \App\Models\Workspace::find($workspaceId);
        }

        return null;
    }
}