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
use App\Http\Controllers\WorkspacesController;
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
    // Dashboard route
    Route::get('/dashboard', function () {
        return \Inertia\Inertia::render('Dashboard');
    })->name('dashboard');
    
    // Engine selection routes
    Route::get('/engine-selection', [EngineSelectionController::class, 'index'])->name('engine.selection');
    Route::post('/engine-selection', [EngineSelectionController::class, 'select'])->name('engine.select');
    Route::post('/engine-selection/clear', [EngineSelectionController::class, 'clear'])->name('engine.clear');
    
    // Workspace selection routes
    Route::get('/workspace-selection', [WorkspaceSelectionController::class, 'index'])->name('workspace.selection');
    Route::post('/workspace-selection', [WorkspaceSelectionController::class, 'select'])->name('workspace.select');
    Route::post('/workspace-selection/create', [WorkspaceSelectionController::class, 'create'])->name('workspace.create');
    Route::get('/workspace-selection/templates', [WorkspaceSelectionController::class, 'getTemplates'])->name('workspace.templates');
    
    // Workspace routes
    Route::get('/workspaces', [\App\Http\Controllers\WorkspacesController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/create', [\App\Http\Controllers\WorkspacesController::class, 'create'])->name('workspaces.create');
    Route::get('/workspaces/templates', [\App\Http\Controllers\WorkspacesController::class, 'getTemplates'])->name('workspaces.templates');
    Route::post('/workspaces', [\App\Http\Controllers\WorkspacesController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/select', [\App\Http\Controllers\WorkspacesController::class, 'select'])->name('workspaces.select');
    Route::get('/workspaces/{workspace}', [\App\Http\Controllers\WorkspacesController::class, 'show'])->name('workspaces.show');
    
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    
    // Games management routes
    Route::get('/games', [GamesController::class, 'index'])->name('games.index');
    Route::get('/games/create', [GamesController::class, 'create'])->name('games.create');
    Route::post('/games', [GamesController::class, 'store'])->name('games.store');
    Route::get('/games/{game}', [GamesController::class, 'show'])->name('games.show');
    Route::get('/games/{game}/edit', [GamesController::class, 'edit'])->name('games.edit');
    Route::put('/games/{game}', [GamesController::class, 'update'])->name('games.update');
    Route::delete('/games/{game}', [GamesController::class, 'destroy'])->name('games.destroy');
    Route::get('/games/{game}/play', [GamesController::class, 'play'])->name('games.play');
    
    // Templates routes
    Route::get('/templates', [\App\Http\Controllers\TemplatesController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}', [\App\Http\Controllers\TemplatesController::class, 'show'])->name('templates.show');
    
    // History routes
    Route::get('/history', [\App\Http\Controllers\HistoryController::class, 'index'])->name('history.index');
    
    // Multiplayer routes
    Route::get('/multiplayer', [\App\Http\Controllers\MultiplayerController::class, 'index'])->name('multiplayer.index');
    Route::get('/multiplayer/{sessionId}', [\App\Http\Controllers\MultiplayerController::class, 'show'])->name('multiplayer.show');
    
    Route::get('/unreal-copilot', function () {
        return redirect('/chat')->with('engine_type', 'unreal');
    })->name('unreal.copilot');
    Route::get('/web-mobile-games', function () {
        return redirect('/chat')->with('engine_type', 'playcanvas');
    })->name('web.mobile.games');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/api-keys', [SettingsController::class, 'updateApiKeys'])->name('settings.api-keys.update');
    Route::delete('/settings/api-keys/{provider}', [SettingsController::class, 'removeApiKey'])->name('settings.api-keys.remove');
    Route::get('/company/settings', [CompanyController::class, 'settings'])->name('company.settings');
    Route::patch('/company/settings', [CompanyController::class, 'updateSettings'])->name('company.settings.update');
    Route::post('/company/invite', [CompanyController::class, 'invite'])->name('company.invite');
    Route::delete('/company/users/{user}', [CompanyController::class, 'removeUser'])->name('company.users.remove');
    Route::delete('/company/invitations/{invitation}', [CompanyController::class, 'cancelInvitation'])->name('company.invitations.cancel');
    Route::patch('/company/users/{user}/role', [CompanyController::class, 'updateUserRole'])->name('company.users.role.update');
    Route::patch('/company/preferences', [CompanyController::class, 'updatePreferences'])->name('company.preferences.update');
    Route::get('/company/billing', [CompanyController::class, 'billing'])->name('company.billing');
    Route::get('/company/provider-settings', [CompanyController::class, 'providerSettings'])->name('company.provider-settings');
    Route::patch('/company/provider-settings', [CompanyController::class, 'updateProviderSettings'])->name('company.provider-settings.update');
    
    // Redesign demo route
    Route::get('/redesign-demo', function () {
        return view('redesign-demo');
    })->name('redesign.demo');
    
    // Component test route
    Route::get('/component-test', function () {
        return \Inertia\Inertia::render('ComponentTest');
    })->name('component.test');
    
    // Layout test route
    Route::get('/layout-test', function () {
        return \Inertia\Inertia::render('LayoutTest');
    })->name('layout.test');
    
    // AI Thinking Display test route
    Route::get('/ai-thinking-test', function () {
        return \Inertia\Inertia::render('AIThinkingTest');
    })->name('ai.thinking.test');
});

// Home routes - redirect based on user state
Route::get('/', function () {
    if (auth()->check()) {
        // If no workspace in session, redirect to workspaces
        if (!session('selected_workspace_id')) {
            return redirect()->route('workspaces.index');
        }
        
        // Otherwise go to chat
        return redirect('/chat');
    }
    // For guests, show welcome page with Inertia
    return \Inertia\Inertia::render('Welcome');
})->name('home');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    
    // Password reset routes
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Debug route for testing authentication
Route::get('/debug-auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user() ? [
            'id' => auth()->user()->id,
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
        ] : null,
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token(),
    ]);
})->middleware('web');

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
    Route::post('/billing/setup-payment-method', [\App\Http\Controllers\BillingController::class, 'setupPaymentMethod']);
    Route::post('/billing/payment-methods/{paymentMethod}/delete', [\App\Http\Controllers\BillingController::class, 'deletePaymentMethod']);
    Route::post('/billing/payment-methods/{paymentMethod}/default', [\App\Http\Controllers\BillingController::class, 'setDefaultPaymentMethod']);
});

// Checkout result pages
Route::view('/billing/success', 'billing.success')->name('billing.success');
Route::view('/billing/cancel', 'billing.cancel')->name('billing.cancel');

// Public pages
Route::get('/privacy', function () {
    return \Inertia\Inertia::render('Public/Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return \Inertia\Inertia::render('Public/Terms');
})->name('terms');

Route::get('/support', function () {
    return \Inertia\Inertia::render('Public/Support');
})->name('support');

// Public game sharing routes (no authentication required)
Route::get('/games/shared/{shareToken}', [\App\Http\Controllers\SharedGameController::class, 'show'])->name('games.shared');
Route::get('/games/embed/{shareToken}', [\App\Http\Controllers\SharedGameController::class, 'embed'])->name('games.embed');
Route::get('/games/shared/{shareToken}/metadata', [\App\Http\Controllers\SharedGameController::class, 'metadata'])->name('games.shared.metadata');
Route::get('/games/shared/{shareToken}/assets/{assetPath}', [\App\Http\Controllers\SharedGameController::class, 'assets'])->name('games.shared.assets')->where('assetPath', '.*');
