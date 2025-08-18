<?php

use App\Http\Controllers\Desktop\DesktopController;
use App\Http\Controllers\Desktop\ChatController;
use App\Http\Controllers\Desktop\ConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop Routes
|--------------------------------------------------------------------------
|
| These routes are specifically for the NativePHP desktop application.
| They provide the desktop UI and local API endpoints.
|
*/

// Desktop UI routes
Route::get('/', [DesktopController::class, 'index'])->name('desktop.index');
Route::get('/chat', [ChatController::class, 'index'])->name('desktop.chat');
Route::get('/settings', [DesktopController::class, 'settings'])->name('desktop.settings');

// Desktop API routes (local only)
Route::prefix('api/desktop')->group(function () {
    Route::get('/config', [ConfigController::class, 'getConfig']);
    Route::post('/config', [ConfigController::class, 'updateConfig']);
    Route::get('/server-info', [ConfigController::class, 'getServerInfo']);
    Route::post('/test-connection', [ConfigController::class, 'testConnection']);
    Route::get('/credits', [ChatController::class, 'credits']);
    Route::post('/setup-ollama', [ConfigController::class, 'setupOllama']);
    Route::get('/ollama-status', [ConfigController::class, 'getOllamaStatus']);
});

// Proxy routes to main API (with local authentication)
Route::prefix('api')->group(function () {
    Route::post('/assist', [ChatController::class, 'assist']);
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::get('/providers', [ChatController::class, 'providers']);
});