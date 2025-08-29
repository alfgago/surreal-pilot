# Next.js to Laravel Inertia React Migration - Design

## Architecture Overview

### Migration Strategy
1. **Incremental Migration**: Migrate pages one by one to ensure stability
2. **Component Reuse**: Port existing components with minimal changes
3. **Backend Integration**: Leverage existing Laravel API and services
4. **Testing First**: Write tests before migration to ensure functionality

### Inertia.js Integration Pattern

```php
// Laravel Controller Pattern
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Index', [
            'user' => $request->user(),
            'workspaces' => $request->user()->workspaces()->with('games')->get(),
            'credits' => $this->creditService->getUserCredits($request->user()),
            'notifications' => $this->notificationService->getRecent($request->user()),
        ]);
    }
}
```

```tsx
// React Component Pattern
import { Head, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

interface DashboardProps extends PageProps {
    workspaces: Workspace[];
    credits: CreditInfo;
    notifications: Notification[];
}

export default function Dashboard({ workspaces, credits, notifications }: DashboardProps) {
    return (
        <MainLayout>
            <Head title="Dashboard" />
            {/* Component content */}
        </MainLayout>
    );
}
```

## Page Migration Plan

### Phase 1: Core Authentication & Layout
1. **Landing Page** (`/`) - Marketing page with auth integration
2. **Login Page** (`/login`) - Authentication with Laravel Sanctum
3. **Register Page** (`/register`) - User registration flow
4. **Main Layout** - Global navigation and workspace context

### Phase 2: Workspace Management
1. **Workspace Selection** (`/workspace-selection`) - Choose/create workspace
2. **Engine Selection** (`/engine-selection`) - Unreal vs PlayCanvas
3. **Workspaces List** (`/workspaces`) - Manage user workspaces
4. **Workspace Creation** (`/workspaces/new`) - Create new workspace

### Phase 3: Core Features
1. **Chat Interface** (`/chat`) - AI chat with streaming responses
2. **Games Management** (`/games`) - Game library and management
3. **Game Detail** (`/games/{id}`) - Individual game editing
4. **Preview** (`/preview`) - Live game preview

### Phase 4: Advanced Features
1. **Templates** (`/templates`) - Game templates library
2. **History** (`/history`) - Chat and action history
3. **Multiplayer** (`/multiplayer`) - Multiplayer session management
4. **Publish** (`/publish`) - Game publishing workflow

### Phase 5: Settings & Administration
1. **Profile** (`/profile`) - User profile management
2. **Settings** (`/settings`) - User preferences
3. **Company Settings** (`/company/settings`) - Company management
4. **Billing** (`/company/billing`) - Subscription and credits

## Component Architecture

### Layout Components
```
resources/js/Layouts/
├── MainLayout.tsx          # Primary app layout
├── AuthLayout.tsx          # Authentication pages layout
├── GuestLayout.tsx         # Public pages layout
└── components/
    ├── Navigation.tsx      # Main navigation
    ├── Sidebar.tsx         # Desktop sidebar
    ├── MobileNav.tsx       # Mobile navigation
    ├── UserMenu.tsx        # User dropdown menu
    └── WorkspaceSwitcher.tsx
```

### Page Components
```
resources/js/Pages/
├── Auth/
│   ├── Login.tsx
│   ├── Register.tsx
│   └── ForgotPassword.tsx
├── Dashboard/
│   └── Index.tsx
├── Workspaces/
│   ├── Index.tsx
│   ├── Create.tsx
│   └── Show.tsx
├── Chat/
│   └── Index.tsx
├── Games/
│   ├── Index.tsx
│   ├── Show.tsx
│   └── Create.tsx
└── Settings/
    ├── Profile.tsx
    ├── Preferences.tsx
    └── Company.tsx
```

### Shared Components
```
resources/js/Components/
├── ui/                     # Radix UI components (ported)
├── chat/                   # Chat-specific components
├── games/                  # Game management components
├── billing/                # Billing components
├── forms/                  # Form components with Inertia
└── common/                 # Shared utilities
```

## Data Flow Design

### Server-Side Data Injection
```php
// Controller method
public function show(Workspace $workspace): Response
{
    return Inertia::render('Workspaces/Show', [
        'workspace' => $workspace->load(['games', 'conversations']),
        'engineStatus' => $this->engineService->getStatus($workspace),
        'recentActivity' => $this->activityService->getRecent($workspace),
        'permissions' => $this->permissionService->getUserPermissions(auth()->user(), $workspace),
    ]);
}
```

### Client-Side State Management
```tsx
// Using Inertia's built-in state management
import { router, usePage } from '@inertiajs/react';

// Form handling with Inertia
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing, errors } = useForm({
    name: '',
    engine: 'playcanvas',
});

const submit = (e: FormEvent) => {
    e.preventDefault();
    post('/workspaces', {
        onSuccess: () => {
            // Handle success
        },
    });
};
```

### Real-time Updates
```tsx
// WebSocket integration for real-time features
import { useEffect } from 'react';
import Echo from 'laravel-echo';

export function useRealtimeUpdates(workspaceId: string) {
    useEffect(() => {
        const channel = Echo.private(`workspace.${workspaceId}`)
            .listen('ChatMessageReceived', (e) => {
                // Update chat messages
                router.reload({ only: ['messages'] });
            })
            .listen('GameUpdated', (e) => {
                // Update game state
                router.reload({ only: ['games'] });
            });

        return () => channel.stopListening();
    }, [workspaceId]);
}
```

## Form Handling Strategy

### Inertia Form Pattern
```tsx
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface CreateGameForm {
    name: string;
    template: string;
    engine: 'unreal' | 'playcanvas';
}

export default function CreateGame() {
    const { data, setData, post, processing, errors, reset } = useForm<CreateGameForm>({
        name: '',
        template: '',
        engine: 'playcanvas',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/api/workspaces/1/games', {
            onSuccess: () => reset(),
            onError: () => {
                // Handle errors
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className={errors.name ? 'border-red-500' : ''}
            />
            {errors.name && <div className="text-red-500">{errors.name}</div>}
            
            <button type="submit" disabled={processing}>
                {processing ? 'Creating...' : 'Create Game'}
            </button>
        </form>
    );
}
```

### Validation Integration
```php
// Laravel Form Request
class CreateGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'template' => 'required|string|exists:demo_templates,id',
            'engine' => 'required|in:unreal,playcanvas',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Game name is required.',
            'template.exists' => 'Selected template is invalid.',
        ];
    }
}
```

## Error Handling Design

### Global Error Handling
```tsx
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        return createRoot(el).render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#4F46E5',
    },
});
```

### Page-Level Error Handling
```tsx
import { usePage } from '@inertiajs/react';

export default function SomePage() {
    const { errors, flash } = usePage().props;

    return (
        <div>
            {flash.success && (
                <div className="bg-green-100 text-green-800 p-4 rounded">
                    {flash.success}
                </div>
            )}
            
            {flash.error && (
                <div className="bg-red-100 text-red-800 p-4 rounded">
                    {flash.error}
                </div>
            )}
            
            {/* Page content */}
        </div>
    );
}
```

## Performance Optimization

### Code Splitting
```tsx
// Lazy loading for large components
import { lazy, Suspense } from 'react';

const GameEditor = lazy(() => import('@/Components/games/GameEditor'));

export default function GameShow() {
    return (
        <Suspense fallback={<div>Loading editor...</div>}>
            <GameEditor />
        </Suspense>
    );
}
```

### Partial Reloads
```tsx
// Only reload specific props
import { router } from '@inertiajs/react';

const refreshGames = () => {
    router.reload({ only: ['games'] });
};

const refreshCredits = () => {
    router.reload({ only: ['credits'] });
};
```

### Asset Optimization
```javascript
// vite.config.js
export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    ui: ['@radix-ui/react-dialog', '@radix-ui/react-dropdown-menu'],
                },
            },
        },
    },
});
```

## Testing Strategy

### Browser Test Structure
```php
// tests/Browser/WorkspaceManagementTest.php
class WorkspaceManagementTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_create_workspace(): void
    {
        $user = User::factory()->create([
            'email' => 'alfredo@5e.cr',
            'password' => Hash::make('Test123!'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/workspaces')
                    ->clickLink('Create Workspace')
                    ->type('name', 'Test Workspace')
                    ->select('engine', 'playcanvas')
                    ->press('Create')
                    ->assertPathIs('/workspaces/1')
                    ->assertSee('Test Workspace');
        });
    }
}
```

### Component Testing
```php
// tests/Browser/ChatInterfaceTest.php
public function test_chat_interface_sends_messages(): void
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->testUser)
                ->visit('/chat')
                ->type('message', 'Hello AI assistant')
                ->press('Send')
                ->waitForText('Hello AI assistant')
                ->waitForText('AI response', 10); // Wait up to 10 seconds for AI response
    });
}
```

## Security Considerations

### CSRF Protection
```tsx
// Automatic CSRF token handling with Inertia
import { router } from '@inertiajs/react';

// CSRF token automatically included in all requests
router.post('/api/games', data);
```

### Authentication Middleware
```php
// Protect all Inertia routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    // ... other protected routes
});
```

### Data Sanitization
```php
// Ensure all data is properly sanitized before sending to frontend
public function index(): Response
{
    return Inertia::render('Games/Index', [
        'games' => auth()->user()->games()
            ->select(['id', 'name', 'engine', 'created_at'])
            ->with('workspace:id,name')
            ->get(),
    ]);
}
```

This design provides a comprehensive blueprint for migrating the Next.js application to Laravel Inertia React while maintaining all functionality and improving backend integration.