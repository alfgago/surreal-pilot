# Technology Stack

## Backend Framework
- **Laravel 12** with PHP 8.3+
- **Filament 4** for admin dashboard and company management
- **Laravel Sanctum** for API authentication
- **Laravel Cashier** with Stripe integration for billing
- **Spatie Laravel Permission** for role-based access control

## AI Integration
- **Vizra ADK** for site-wide AI orchestration (primary)
- **Prism-PHP** for multi-provider AI integration (legacy support)
- Supported providers: OpenAI, Anthropic, Gemini, Ollama
- Streaming responses with Server-Sent Events
- Token counting and rate limiting

## Desktop Application
- **NativePHP/Electron** for cross-platform desktop distribution
- Local Laravel API server with port collision detection
- Local configuration management and offline capabilities

## Frontend
- **Vite** for asset bundling
- **TailwindCSS 4** for styling
- **Laravel Blade** templates
- **Axios** for HTTP requests

## Database & Storage
- **SQLite** (development) / **MySQL 8.0+** (production)
- **AWS S3** for file storage
- **CloudFront** for CDN
- **Redis** for caching and sessions

## Testing
- **PHPUnit** for PHP unit and feature tests
- **Puppeteer MCP** for end-to-end browser testing
- **Jest** for JavaScript unit tests
- Comprehensive test coverage for API endpoints, services, and UI

## Game Engine Integration
- **Unreal Engine 5.0+** plugin with C++ and Blueprint support
- **PlayCanvas** integration via MCP (Model Context Protocol) servers
- Context export and patch application systems

## Development Tools
- **Composer** for PHP dependency management
- **npm** for JavaScript dependencies
- **Laravel Pint** for code formatting
- **Laravel Pail** for log monitoring

## Model Context Protocol (MCP)
- **Laravel Boost MCP** for rapid Laravel scaffolding and code generation
- **Puppeteer MCP** for browser automation and end-to-end testing
- **PlayCanvas MCP** for game engine integration and context management
- Auto-approved tools for seamless development workflow

## Common Commands

### Development
```bash
# Start development environment
composer run dev

# Start desktop application
composer run native:dev

# Run tests
php artisan test
npm run test:jest
npm run test:mobile

# Code formatting
./vendor/bin/pint
```

### Database
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### Building & Deployment
```bash
# Build assets
npm run build

# Build desktop application
php artisan native:build

# Deploy to production
./scripts/deploy-production.sh
```

### Maintenance
```bash
# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Cleanup old resources
php artisan cleanup:old-workspaces
php artisan cleanup:all-resources
```