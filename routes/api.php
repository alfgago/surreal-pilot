<?php

use App\Http\Controllers\Api\AssistController;
use App\Http\Controllers\Api\ChatSettingsController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PrototypeController;
use App\Http\Controllers\Api\RealtimeChatController;
use App\Http\Controllers\Api\StreamingChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth.sanctum_or_web');

// AI assistance routes with authentication, role check, and provider resolution middleware
// Chat endpoints: allow on all plans; Unreal-related context should be gated at action time
Route::middleware(['auth.sanctum_or_web', 'check.developer.role', 'resolve.ai.driver', 'throttle:chat'])->group(function () {
    Route::post('/assist', [AssistController::class, 'assist']);
    Route::post('/chat', [AssistController::class, 'chat']); // New streaming chat endpoint
});

// MCP command routes with additional tracking middleware
// MCP commands require engine-specific capability checks
Route::middleware(['auth.sanctum_or_web', 'check.developer.role', 'resolve.ai.driver', 'verify.engine.hmac', 'track.mcp.actions', 'throttle:chat'])->group(function () {
    Route::post('/mcp-command', [AssistController::class, 'mcpCommand']); // MCP command routing
});

// Patch validation & undo
Route::middleware(['auth.sanctum_or_web', 'check.developer.role', 'verify.engine.hmac', 'throttle:chat'])->group(function () {
    Route::post('/patch/validate', [AssistController::class, 'validatePatch']);
    Route::post('/patch/undo', [AssistController::class, 'undoPatch']);
});

// Prototype workflow API routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/demos', [PrototypeController::class, 'getDemos']);
    Route::post('/prototype', [PrototypeController::class, 'createPrototype']);
    Route::get('/workspace/{id}/status', [PrototypeController::class, 'getWorkspaceStatus']);
    Route::post('/workspace/publish', [PrototypeController::class, 'publishWorkspace']);
    Route::post('/workspace/publish-playcanvas-cloud', [PrototypeController::class, 'publishToPlayCanvasCloud']);
    Route::get('/workspaces/stats', [PrototypeController::class, 'getWorkspaceStats']);
    Route::get('/workspaces', [PrototypeController::class, 'listWorkspaces']);
});

// Provider information route (no middleware needed)
Route::get('/providers', [AssistController::class, 'providers']);

// Billing and credit management API routes
Route::middleware(['auth.sanctum_or_web'])->prefix('billing')->group(function () {
    Route::get('/balance', [\App\Http\Controllers\Api\BillingController::class, 'balance']);
    Route::get('/analytics', [\App\Http\Controllers\Api\BillingController::class, 'analytics']);
    Route::get('/transactions', [\App\Http\Controllers\Api\BillingController::class, 'transactions']);
    Route::get('/subscription', [\App\Http\Controllers\Api\BillingController::class, 'subscription']);
    Route::get('/summary', [\App\Http\Controllers\Api\BillingController::class, 'summary']);
});

// Role information route (requires authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/role-info', [AssistController::class, 'roleInfo']);
});

// Engine selection routes (web-based, use web middleware)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/engines', [EngineController::class, 'getEngines']);
    Route::post('/user/engine-preference', [EngineController::class, 'setEnginePreference']);
    Route::get('/user/engine-preference', [EngineController::class, 'getEnginePreference']);
    Route::delete('/user/engine-preference', [EngineController::class, 'clearEnginePreference']);
});

// Chat conversation management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/workspaces/{workspaceId}/conversations', [ConversationController::class, 'getWorkspaceConversations']);
    Route::post('/workspaces/{workspaceId}/conversations', [ConversationController::class, 'createConversation']);
    Route::get('/conversations/{conversationId}/messages', [ConversationController::class, 'getConversationMessages']);
    Route::post('/conversations/{conversationId}/messages', [ConversationController::class, 'addMessage']);
    Route::put('/conversations/{conversationId}', [ConversationController::class, 'updateConversation']);
    Route::delete('/conversations/{conversationId}', [ConversationController::class, 'deleteConversation']);
    Route::get('/conversations/recent', [ConversationController::class, 'getRecentConversations']);
});

// Real-time chat features
Route::middleware(['auth:sanctum'])->group(function () {
    // Streaming chat with Laravel Stream
    Route::post('/chat/stream', [StreamingChatController::class, 'stream']);
    Route::get('/chat/stream', [StreamingChatController::class, 'streamSSE']);

    // Typing indicators
    Route::post('/conversations/{conversationId}/typing', [RealtimeChatController::class, 'updateTypingStatus']);
    Route::get('/conversations/{conversationId}/typing', [RealtimeChatController::class, 'getTypingUsers']);

    // Connection status
    Route::post('/workspaces/{workspaceId}/connection', [RealtimeChatController::class, 'updateConnectionStatus']);
    Route::get('/workspaces/{workspaceId}/connections', [RealtimeChatController::class, 'getConnectionStatuses']);
    Route::get('/workspaces/{workspaceId}/chat-stats', [RealtimeChatController::class, 'getChatStatistics']);

    // Real-time collaboration features
    Route::get('/workspaces/{workspaceId}/collaboration-stats', [RealtimeChatController::class, 'getCollaborationStats']);
    Route::post('/workspaces/{workspaceId}/collaboration/join', [RealtimeChatController::class, 'joinCollaboration']);
    Route::post('/workspaces/{workspaceId}/collaboration/leave', [RealtimeChatController::class, 'leaveCollaboration']);
});

// Games management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/workspaces/{workspaceId}/games', [GameController::class, 'getWorkspaceGames']);
    Route::post('/workspaces/{workspaceId}/games', [GameController::class, 'createGame']);
    Route::get('/games/recent', [GameController::class, 'getRecentGames']);
    Route::get('/games/{gameId}', [GameController::class, 'getGame']);
    Route::get('/games/{gameId}/preview', [GameController::class, 'getGamePreview']);
    Route::put('/games/{gameId}', [GameController::class, 'updateGame']);
    Route::delete('/games/{gameId}', [GameController::class, 'deleteGame']);

    // Game sharing routes
    Route::post('/games/{gameId}/share', [GameController::class, 'shareGame']);
    Route::put('/games/{gameId}/sharing-settings', [GameController::class, 'updateSharingSettings']);
    Route::delete('/games/{gameId}/share', [GameController::class, 'revokeShareLink']);
    Route::get('/games/{gameId}/sharing-stats', [GameController::class, 'getSharingStats']);

    // Game publishing and build management
    Route::post('/games/{game}/build', [\App\Http\Controllers\Api\GamePublishingController::class, 'startBuild']);
    Route::get('/games/{game}/build/status', [\App\Http\Controllers\Api\GamePublishingController::class, 'getBuildStatus']);
    Route::get('/games/{game}/build/history', [\App\Http\Controllers\Api\GamePublishingController::class, 'getBuildHistory']);
    Route::post('/games/{game}/publish', [\App\Http\Controllers\Api\GamePublishingController::class, 'publishGame']);
    Route::post('/games/{game}/unpublish', [\App\Http\Controllers\Api\GamePublishingController::class, 'unpublishGame']);
    Route::post('/games/{game}/share-token', [\App\Http\Controllers\Api\GamePublishingController::class, 'generateShareToken']);

    // Domain publishing routes
    Route::post('/games/{game}/domain', [\App\Http\Controllers\Api\GameController::class, 'setupCustomDomain']);
    Route::post('/games/{game}/domain/verify', [\App\Http\Controllers\Api\GameController::class, 'verifyDomain']);
    Route::delete('/games/{game}/domain', [\App\Http\Controllers\Api\GameController::class, 'removeDomain']);
    Route::get('/games/{game}/domain/status', [\App\Http\Controllers\Api\GameController::class, 'getDomainStatus']);
});

// Chat settings management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/chat/settings', [ChatSettingsController::class, 'getSettings']);
    Route::post('/chat/settings', [ChatSettingsController::class, 'saveSettings']);
    Route::get('/chat/models', [ChatSettingsController::class, 'getModels']);
    Route::post('/chat/settings/reset', [ChatSettingsController::class, 'resetSettings']);
    Route::get('/chat/settings/{engineType}', [ChatSettingsController::class, 'getEngineSettings']);

    // API Key Management
    Route::get('/chat/api-keys', [ChatSettingsController::class, 'getApiKeys']);
    Route::post('/chat/api-keys', [ChatSettingsController::class, 'saveApiKeys']);
    Route::delete('/chat/api-keys', [ChatSettingsController::class, 'deleteApiKeys']);
});

// Credit management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/credits/balance', [\App\Http\Controllers\Api\CreditController::class, 'getRealTimeBalance']);
    Route::get('/credits/analytics', [\App\Http\Controllers\Api\CreditController::class, 'getEngineUsageAnalytics']);
    Route::get('/credits/transactions', [\App\Http\Controllers\Api\CreditController::class, 'getTransactionHistory']);
    Route::get('/credits/mcp-surcharge-info', [\App\Http\Controllers\Api\CreditController::class, 'getMcpSurchargeInfo']);
});

// Mobile-specific routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/mobile/demos', [\App\Http\Controllers\MobileController::class, 'getDemos']);
    Route::get('/mobile/device-info', [\App\Http\Controllers\MobileController::class, 'getDeviceInfo']);
    Route::get('/mobile/workspace/{id}/status', [\App\Http\Controllers\MobileController::class, 'getWorkspaceStatus']);
    Route::get('/mobile/playcanvas-suggestions', [\App\Http\Controllers\MobileController::class, 'getPlayCanvasSuggestions']);
});

// Multiplayer session management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/multiplayer/start', [\App\Http\Controllers\Api\MultiplayerController::class, 'start']);
    Route::post('/multiplayer/{sessionId}/stop', [\App\Http\Controllers\Api\MultiplayerController::class, 'stop']);
    Route::get('/multiplayer/{sessionId}/status', [\App\Http\Controllers\Api\MultiplayerController::class, 'status']);
    Route::post('/multiplayer/{sessionId}/upload', [\App\Http\Controllers\Api\MultiplayerController::class, 'uploadProgress']);
    Route::get('/multiplayer/{sessionId}/download/{filename}', [\App\Http\Controllers\Api\MultiplayerController::class, 'downloadProgress']);
    Route::get('/multiplayer/{sessionId}/files', [\App\Http\Controllers\Api\MultiplayerController::class, 'listProgress']);
    Route::get('/multiplayer/stats', [\App\Http\Controllers\Api\MultiplayerController::class, 'stats']);
    Route::get('/multiplayer/active', [\App\Http\Controllers\Api\MultiplayerController::class, 'activeSessions']);
});

// Engine integration routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Engine status and management
    Route::get('/workspaces/{workspaceId}/engine/status', [EngineController::class, 'getEngineStatus']);
    Route::get('/workspaces/{workspaceId}/context', [EngineController::class, 'getWorkspaceContext']);

    // Unreal Engine specific routes
    Route::get('/workspaces/{workspaceId}/unreal/status', [EngineController::class, 'getUnrealStatus']);
    Route::post('/workspaces/{workspaceId}/unreal/test', [EngineController::class, 'testUnrealConnection']);

    // PlayCanvas specific routes
    Route::get('/workspaces/{workspaceId}/playcanvas/status', [EngineController::class, 'getPlayCanvasStatus']);
    Route::post('/workspaces/{workspaceId}/playcanvas/refresh', [EngineController::class, 'refreshPlayCanvasPreview']);
    Route::post('/workspaces/{workspaceId}/playcanvas/start', [EngineController::class, 'startPlayCanvasMcp']);
    Route::post('/workspaces/{workspaceId}/playcanvas/stop', [EngineController::class, 'stopPlayCanvasMcp']);

    // Engine AI configuration
    Route::get('/engine/{engineType}/ai-config', [EngineController::class, 'getEngineAiConfig']);
    Route::put('/engine/{engineType}/ai-config', [EngineController::class, 'updateEngineAiConfig']);
});

// GDevelop integration routes
Route::middleware(['auth:sanctum', 'gdevelop.enabled'])->prefix('gdevelop')->name('api.gdevelop.')->group(function () {
    Route::post('/chat', [\App\Http\Controllers\Api\GDevelopChatController::class, 'chat'])->name('chat');
    Route::get('/preview/{sessionId}', [\App\Http\Controllers\Api\GDevelopChatController::class, 'preview'])->name('preview');
    Route::post('/export/{sessionId}', [\App\Http\Controllers\Api\GDevelopExportController::class, 'export'])->name('export');
    Route::get('/export/{sessionId}/status', [\App\Http\Controllers\Api\GDevelopExportController::class, 'status'])->name('export.status');
    Route::get('/export/{sessionId}/download', [\App\Http\Controllers\Api\GDevelopExportController::class, 'download'])->name('export.download');
    Route::delete('/export/{sessionId}', [\App\Http\Controllers\Api\GDevelopExportController::class, 'delete'])->name('export.delete');
    Route::post('/export/cleanup', [\App\Http\Controllers\Api\GDevelopExportController::class, 'cleanup'])->name('export.cleanup');
    Route::get('/session/{sessionId}', [\App\Http\Controllers\Api\GDevelopChatController::class, 'getSession'])->name('session');
    Route::delete('/session/{sessionId}', [\App\Http\Controllers\Api\GDevelopChatController::class, 'deleteSession'])->name('session.delete');
});

// GDevelop preview file serving (public access for iframe loading)
Route::prefix('gdevelop')->name('gdevelop.')->group(function () {
    Route::get('/preview/{sessionId}/serve/{filePath?}', [\App\Http\Controllers\Api\GDevelopPreviewController::class, 'serveFile'])
        ->name('preview.serve')
        ->where('filePath', '.*');
    Route::post('/preview/{sessionId}/refresh', [\App\Http\Controllers\Api\GDevelopPreviewController::class, 'refresh'])
        ->name('preview.refresh')
        ->middleware('auth:sanctum');
});

// Mobile-specific API routes
Route::prefix('mobile')->group(function () {
    Route::get('/demos', [\App\Http\Controllers\MobileController::class, 'getDemos']);
    Route::get('/device-info', [\App\Http\Controllers\MobileController::class, 'getDeviceInfo']);
    Route::get('/workspace/{workspaceId}/status', [\App\Http\Controllers\MobileController::class, 'getWorkspaceStatus']);
    Route::get('/playcanvas-suggestions', [\App\Http\Controllers\MobileController::class, 'getPlayCanvasSuggestions']);
});
