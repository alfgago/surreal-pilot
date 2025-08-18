# Project Structure & Organization

## Laravel Application Structure

### Core Application (`app/`)
- **Actions/**: FilamentCompanies actions for team management
- **Agents/**: AI agent classes (PlayCanvasAgent, UnrealAgent, Enhanced/)
- **Console/Commands/**: Artisan commands for cleanup and maintenance
- **Http/Controllers/**: API and web controllers organized by purpose
  - `Api/`: REST API endpoints (AssistController, CreditController, etc.)
  - Web controllers for frontend pages
- **Http/Middleware/**: Custom middleware (engine routing, credit tracking, HMAC validation)
- **Models/**: Eloquent models following Laravel conventions
- **Services/**: Business logic services (CreditManager, WorkspaceService, AI providers)
- **Policies/**: Authorization policies for models
- **Providers/**: Service providers (Prism, NativeApp, Filament)

### Key Models
- **Company**: Multi-tenant company management with credits and plans
- **User**: Users belonging to companies with roles
- **Workspace**: Game development workspaces (Unreal/PlayCanvas)
- **CreditTransaction**: Credit usage tracking
- **Patch**: AI-generated code patches with application history
- **MultiplayerSession**: Real-time multiplayer game sessions

### Configuration (`config/`)
- **ai.php**: AI orchestration and model configuration
- **multiplayer.php**: Multiplayer session settings
- **workspace.php**: Workspace and template configuration
- **aws.php**: AWS services configuration
- **prism.php**: Legacy AI provider configuration

## Testing Organization

### Test Structure (`tests/`)
- **Unit/**: Isolated unit tests for services, models, and utilities
- **Feature/**: Integration tests for API endpoints and workflows
- **Browser/**: Laravel Dusk browser tests
- **Playwright/**: End-to-end tests for complex user flows

### Test Categories
- **API Tests**: Comprehensive coverage of REST endpoints
- **Service Tests**: Business logic validation
- **Integration Tests**: Cross-engine compatibility and workflows
- **Mobile Tests**: Playwright tests for mobile UI components
- **PlayCanvas Tests**: Game engine specific functionality

## Frontend Assets (`resources/`)
- **css/**: Stylesheets including mobile-specific styles
- **js/**: JavaScript modules and components
- **views/**: Blade templates organized by feature
  - `mobile/`: Mobile-optimized templates
  - Standard web templates

## Game Engine Integration

### Unreal Engine (`UnrealEngine/SurrealPilot/`)
- C++ plugin with Blueprint integration
- Context export and patch application
- HTTP client for API communication

### PlayCanvas Integration (`vendor/pc_mcp/`)
- MCP server implementation
- Demo loader and template management
- Jest test suite for JavaScript components

## Storage Organization (`storage/`)
- **workspaces/**: User workspace files and builds
- **templates/**: Game templates and samples
- **knowledge-base/**: AI training and context data
- **test_build_*/**: Temporary build artifacts

## Development Scripts (`scripts/`)
- **dev-setup.bat**: Windows development environment setup
- **deploy-production.sh**: Production deployment automation
- **setup-forge.sh**: Laravel Forge server configuration

## Naming Conventions

### Files & Classes
- **Controllers**: Descriptive names ending in `Controller` (e.g., `AssistController`)
- **Services**: Business domain + `Service` (e.g., `CreditManager`, `WorkspaceService`)
- **Models**: Singular nouns (e.g., `User`, `Company`, `Workspace`)
- **Migrations**: Timestamp + descriptive action (e.g., `add_cross_engine_compatibility_constraints`)

### API Routes
- RESTful conventions: `/api/resource` with appropriate HTTP verbs
- Nested resources: `/api/companies/{company}/workspaces`
- Action-based: `/api/assist` for AI chat endpoint

### Database Tables
- Plural snake_case (e.g., `credit_transactions`, `multiplayer_sessions`)
- Foreign keys: `{model}_id` (e.g., `company_id`, `workspace_id`)
- Pivot tables: alphabetical order (e.g., `company_user`)

## Code Organization Patterns

### Service Layer Pattern
- Business logic encapsulated in service classes
- Dependency injection through Laravel container
- Clear separation between controllers and business logic

### Repository Pattern (Selective)
- Used for complex queries and data access patterns
- Models handle simple CRUD operations directly

### Event-Driven Architecture
- Laravel events for credit transactions
- Webhook processing for Stripe integration
- Real-time updates for multiplayer sessions

### Multi-Tenancy
- Company-based isolation using Filament Companies
- Workspace ownership and access control
- Credit allocation per company