<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EngineSelectionController;
use App\Http\Controllers\WorkspaceSelectionController;
use Illuminate\Support\Facades\Route;

// Add logout route
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Main app routes (require authentication)
Route::middleware('auth')->group(function () {
    // Engine selection routes
    Route::get('/engine-selection', [EngineSelectionController::class, 'index'])->name('engine.selection');
    Route::post('/engine-selection', [EngineSelectionController::class, 'select'])->name('engine.select');
    Route::post('/engine-selection/clear', [EngineSelectionController::class, 'clear'])->name('engine.clear');
    
    // Workspace selection routes
    Route::get('/workspace-selection', [WorkspaceSelectionController::class, 'index'])->name('workspace.selection');
    Route::post('/workspace-selection', [WorkspaceSelectionController::class, 'select'])->name('workspace.select');
    Route::post('/workspace-selection/create', [WorkspaceSelectionController::class, 'create'])->name('workspace.create');
    Route::get('/workspace-selection/templates', [WorkspaceSelectionController::class, 'getTemplates'])->name('workspace.templates');
    
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/games', [GamesController::class, 'index'])->name('games');
    Route::get('/games/{id}', [GamesController::class, 'show'])->name('games.show');
    Route::get('/unreal-copilot', function () {
        return redirect('/chat')->with('engine_type', 'unreal');
    })->name('unreal.copilot');
    Route::get('/web-mobile-games', function () {
        return redirect('/chat')->with('engine_type', 'playcanvas');
    })->name('web.mobile.games');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/company/settings', [CompanyController::class, 'settings']);
    Route::patch('/company/settings', [CompanyController::class, 'updateSettings']);
    Route::post('/company/invite', [CompanyController::class, 'invite']);
    Route::get('/company/billing', [CompanyController::class, 'billing']);
    Route::get('/company/provider-settings', [CompanyController::class, 'providerSettings']);
    Route::patch('/company/provider-settings', [CompanyController::class, 'updateProviderSettings']);
});

// Home routes - redirect based on user state
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        
        // If user hasn't selected an engine, redirect to engine selection
        if (!$user->hasSelectedEngine()) {
            return redirect()->route('engine.selection');
        }
        
        // If user has selected engine but no workspace in session, redirect to workspace selection
        if (!session('selected_workspace_id')) {
            return redirect()->route('workspace.selection');
        }
        
        // Otherwise go to chat
        return redirect('/chat');
    }
    // For guests, show lightweight landing (no layout usage to avoid route lookups)
    return view('landing');
})->name('home');

// Basic Auth routes (temporary MVP)
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// PWA manifest route
Route::get('/manifest.json', function () {
    return response()->json([
        'name' => 'SurrealPilot Mobile',
        'short_name' => 'SurrealPilot',
        'description' => 'AI-powered PlayCanvas game development on mobile',
        'start_url' => '/mobile/chat',
        'display' => 'standalone',
        'background_color' => '#1f2937',
        'theme_color' => '#1f2937',
        'orientation' => 'portrait-primary',
        'icons' => [
            [
                'src' => '/images/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ],
            [
                'src' => '/images/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ]
        ],
        'categories' => ['games', 'developer', 'productivity'],
        'lang' => 'en',
        'dir' => 'ltr',
        'scope' => '/mobile/',
        'prefer_related_applications' => false
    ]);
});

// Mobile routes
Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::get('/chat', [App\Http\Controllers\MobileController::class, 'chat'])->name('chat');
    Route::get('/tutorials', [App\Http\Controllers\MobileController::class, 'tutorials'])->name('tutorials');
});

// Stripe webhook routes
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// Checkout routes (stub): create subscription or top-up sessions
Route::middleware(['auth'])->group(function () {
    Route::post('/billing/checkout/subscription', [\App\Http\Controllers\BillingController::class, 'createSubscriptionSession']);
    Route::post('/billing/checkout/topup', [\App\Http\Controllers\BillingController::class, 'createTopUpSession']);
});

// Checkout result pages
Route::view('/billing/success', 'billing.success')->name('billing.success');
Route::view('/billing/cancel', 'billing.cancel')->name('billing.cancel');
