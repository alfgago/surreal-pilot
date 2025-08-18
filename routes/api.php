<?php

use App\Http\Controllers\Api\AssistController;
use App\Http\Controllers\Api\ChatSettingsController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PrototypeController;
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

// Role information route (requires authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/role-info', [AssistController::class, 'roleInfo']);
});

// Engine selection routes
Route::middleware(['auth:sanctum'])->group(function () {
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

// Games management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/workspaces/{workspaceId}/games', [GameController::class, 'getWorkspaceGames']);
    Route::post('/workspaces/{workspaceId}/games', [GameController::class, 'createGame']);
    Route::get('/games/recent', [GameController::class, 'getRecentGames']);
    Route::get('/games/{gameId}', [GameController::class, 'getGame']);
    Route::put('/games/{gameId}', [GameController::class, 'updateGame']);
    Route::delete('/games/{gameId}', [GameController::class, 'deleteGame']);
});

// Chat settings management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/chat/settings', [ChatSettingsController::class, 'getSettings']);
    Route::post('/chat/settings', [ChatSettingsController::class, 'saveSettings']);
    Route::get('/chat/models', [ChatSettingsController::class, 'getModels']);
    Route::post('/chat/settings/reset', [ChatSettingsController::class, 'resetSettings']);
    Route::get('/chat/settings/{engineType}', [ChatSettingsController::class, 'getEngineSettings']);
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

// Mobile-specific API routes
Route::prefix('mobile')->group(function () {
    Route::get('/demos', [\App\Http\Controllers\MobileController::class, 'getDemos']);
    Route::get('/device-info', [\App\Http\Controllers\MobileController::class, 'getDeviceInfo']);
    Route::get('/workspace/{workspaceId}/status', [\App\Http\Controllers\MobileController::class, 'getWorkspaceStatus']);
    Route::get('/playcanvas-suggestions', [\App\Http\Controllers\MobileController::class, 'getPlayCanvasSuggestions']);
});
