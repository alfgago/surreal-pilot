# Route Audit and Guidelines

## Overview
This document provides a comprehensive audit of all routes in the SurrealPilot application and establishes guidelines to prevent route conflicts and ensure proper controller mapping.

## Route Audit Results ✅

### ✅ **No Route Conflicts Detected**
All routes are properly mapped to their intended controllers with no conflicts.

### ✅ **Desktop Route Isolation**
Desktop routes are properly isolated and only load in NativePHP environment, preventing conflicts with web routes.

## Route Categories

### 🏠 **Core Web Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/` | `Inertia::render('Welcome')` | Landing page | ✅ Correct |
| `/chat` | `ChatController@index` | Main chat interface | ✅ Correct |
| `/dashboard` | `Inertia::render('Dashboard')` | User dashboard | ✅ Correct |
| `/engine-selection` | `EngineSelectionController@index` | Engine selection | ✅ Correct |
| `/workspace-selection` | `WorkspaceSelectionController@index` | Workspace selection | ✅ Correct |

### 🔐 **Authentication Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/login` | `AuthController@showLogin` | Login page | ✅ Correct |
| `/register` | `AuthController@showRegister` | Registration page | ✅ Correct |
| `/logout` | Closure (auth logout) | Logout action | ✅ Correct |
| `/forgot-password` | `Auth\PasswordResetLinkController` | Password reset | ✅ Correct |

### 🎮 **Game Management Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/games` | `GamesController@index` | Games listing | ✅ Correct |
| `/games/create` | `GamesController@create` | Create game | ✅ Correct |
| `/games/{game}` | `GamesController@show` | Show game | ✅ Correct |
| `/games/{game}/play` | `GamesController@play` | Play game | ✅ Correct |

### 🔧 **Settings & Profile Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/profile` | `ProfileController@index` | User profile | ✅ Correct |
| `/settings` | `SettingsController@index` | User settings | ✅ Correct |
| `/company/settings` | `CompanyController@settings` | Company settings | ✅ Correct |
| `/company/billing` | `CompanyController@billing` | Billing management | ✅ Correct |

### 🌐 **Public Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/privacy` | `Inertia::render('Public/Privacy')` | Privacy policy | ✅ Correct |
| `/terms` | `Inertia::render('Public/Terms')` | Terms of service | ✅ Correct |
| `/support` | `Inertia::render('Public/Support')` | Support page | ✅ Correct |

### 📱 **Mobile Routes** (`routes/web.php`)
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/mobile/chat` | `MobileController@chat` | Mobile chat | ✅ Correct |
| `/mobile/tutorials` | `MobileController@tutorials` | Mobile tutorials | ✅ Correct |

### 🔌 **API Routes** (`routes/api.php`)
| Route Prefix | Controller Namespace | Purpose | Status |
|--------------|---------------------|---------|--------|
| `/api/chat/*` | `Api\ChatSettingsController` | Chat settings API | ✅ Correct |
| `/api/games/*` | `Api\GameController` | Games API | ✅ Correct |
| `/api/workspaces/*` | `Api\PrototypeController` | Workspaces API | ✅ Correct |
| `/api/billing/*` | `Api\BillingController` | Billing API | ✅ Correct |
| `/api/engine/*` | `Api\EngineController` | Engine API | ✅ Correct |

### 🖥️ **Desktop Routes** (`routes/desktop.php`) - **ISOLATED**
| Route | Controller | Purpose | Status |
|-------|------------|---------|--------|
| `/` | `Desktop\DesktopController@index` | Desktop home | ✅ Isolated |
| `/chat` | `Desktop\ChatController@index` | Desktop chat | ✅ Isolated |
| `/settings` | `Desktop\DesktopController@settings` | Desktop settings | ✅ Isolated |

**Note**: Desktop routes are only loaded when `NATIVE_PHP=true` or running in NativePHP environment.

## Route Conflict Prevention Guidelines

### 1. **Environment-Based Route Loading**
```php
// bootstrap/app.php
then: function () {
    // Load desktop routes ONLY when running in NativePHP desktop app
    if (class_exists(\Native\Laravel\Facades\Window::class) &&
        !app()->runningInConsole() &&
        !app()->environment(['testing', 'dusk.local']) &&
        (env('NATIVE_PHP', false) || isset($_SERVER['NATIVE_PHP']))) {
        Route::middleware('web')
            ->group(base_path('routes/desktop.php'));
    }
},
```

### 2. **Controller Naming Conventions**
- **Web Controllers**: `App\Http\Controllers\{Name}Controller`
- **API Controllers**: `App\Http\Controllers\Api\{Name}Controller`
- **Desktop Controllers**: `App\Http\Controllers\Desktop\{Name}Controller`
- **Auth Controllers**: `App\Http\Controllers\Auth\{Name}Controller`

### 3. **Route File Organization**
- **`routes/web.php`**: All web interface routes (Inertia.js)
- **`routes/api.php`**: All API endpoints
- **`routes/desktop.php`**: Desktop app routes (NativePHP only)
- **`routes/console.php`**: Artisan commands

### 4. **Route Naming Conventions**
- **Web routes**: `{resource}.{action}` (e.g., `games.index`, `chat`)
- **API routes**: No names (use URI-based access)
- **Desktop routes**: `desktop.{resource}.{action}`

## Testing Route Integrity

### Automated Route Testing
```bash
# Test all routes are accessible
php artisan route:list

# Test specific route patterns
php artisan route:list --name=chat
php artisan route:list --path=api

# Run browser tests to verify routes work
./vendor/bin/pest tests/Browser/BrowserSetupTest.php
```

### Manual Route Verification Checklist
- [ ] Homepage loads React landing page
- [ ] Chat route redirects properly based on auth state
- [ ] All authenticated routes require login
- [ ] API routes return JSON responses
- [ ] Desktop routes only load in NativePHP environment

## Common Issues and Solutions

### Issue 1: Route Conflicts
**Symptoms**: Wrong controller being called, "View not found" errors
**Solution**: Check route loading conditions in `bootstrap/app.php`

### Issue 2: Desktop Routes Loading in Web
**Symptoms**: Blade view errors when expecting Inertia components
**Solution**: Verify `NATIVE_PHP` environment variable is not set in web environment

### Issue 3: Missing Route Names
**Symptoms**: `route()` helper fails in Blade/React components
**Solution**: Add proper route names to all web routes

## Maintenance Tasks

### Monthly Route Audit
1. Run `php artisan route:list` and review for conflicts
2. Check that all routes point to correct controllers
3. Verify desktop routes are properly isolated
4. Test critical user flows with browser tests

### When Adding New Routes
1. Follow naming conventions
2. Place in correct route file
3. Use appropriate controller namespace
4. Add route name if used in frontend
5. Test for conflicts with existing routes

## Emergency Route Debugging

### Quick Diagnostic Commands
```bash
# Check which routes are loaded
php artisan route:list

# Check specific route resolution
php artisan route:list --name={route-name}

# Test route in browser
curl -I http://localhost:8000/{route-path}

# Check environment variables
php artisan tinker
>>> env('NATIVE_PHP')
>>> app()->environment()
```

### Route Conflict Resolution Steps
1. Identify conflicting routes with `php artisan route:list`
2. Check route loading conditions in `bootstrap/app.php`
3. Verify controller namespaces and locations
4. Test with browser tests
5. Update this documentation

---

**Last Updated**: August 27, 2025
**Status**: ✅ All routes verified and working correctly
**Next Review**: September 27, 2025