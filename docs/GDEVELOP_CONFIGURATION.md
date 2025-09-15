# GDevelop Configuration Guide

This guide explains how to configure GDevelop integration in SurrealPilot.

## Environment Variables

### Required Configuration

```bash
# Enable/disable GDevelop integration
GDEVELOP_ENABLED=true

# Enable/disable PlayCanvas integration (can be used alongside GDevelop)
PLAYCANVAS_ENABLED=false

# GDevelop CLI tool paths (install with npm install -g gdevelop-cli gdcore-tools)
GDEVELOP_CLI_PATH=gdexport
GDEVELOP_CORE_TOOLS_PATH=gdcore-tools

# Storage paths (relative to storage directory)
GDEVELOP_TEMPLATES_PATH=storage/gdevelop/templates
GDEVELOP_SESSIONS_PATH=storage/gdevelop/sessions
GDEVELOP_EXPORTS_PATH=storage/gdevelop/exports
```

### Optional Configuration

```bash
# Session management
GDEVELOP_MAX_SESSION_SIZE=100MB
GDEVELOP_SESSION_TIMEOUT=24h

# Preview settings
GDEVELOP_PREVIEW_CACHE_TIMEOUT=120
GDEVELOP_PREVIEW_MAX_FILE_SIZE=10MB
GDEVELOP_PREVIEW_CLEANUP_INTERVAL="1 hour"
GDEVELOP_PREVIEW_ENABLE_CACHING=true

# Export settings
GDEVELOP_EXPORT_TIMEOUT=30
GDEVELOP_MAX_EXPORT_SIZE=104857600
GDEVELOP_EXPORT_CLEANUP_HOURS=24

# Error handling
GDEVELOP_MAX_RETRIES=3
GDEVELOP_RETRY_DELAY=2
GDEVELOP_BACKOFF_MULTIPLIER=2
GDEVELOP_ENABLE_FALLBACK=true
GDEVELOP_ERROR_TRACKING_DURATION="24 hours"
```

## Feature Flags

The system uses feature flags to control which game engines are available:

- `GDEVELOP_ENABLED=true` - Enables GDevelop integration
- `PLAYCANVAS_ENABLED=false` - Disables PlayCanvas integration

You can have both engines enabled simultaneously, but for the best user experience, it's recommended to enable only one primary engine.

## Setup and Validation

### Prerequisites

1. **Node.js**: Required for GDevelop CLI tools
2. **GDevelop CLI Tools**: Install globally with npm

```bash
npm install -g gdevelop-cli
npm install -g gdcore-tools
```

### Verification Commands

```bash
# Quick setup verification
php artisan gdevelop:setup:verify

# Automatically fix common issues
php artisan gdevelop:setup:verify --install --create-dirs

# Full configuration validation
php artisan gdevelop:config:validate

# Show configuration summary
php artisan gdevelop:config:validate --summary

# Attempt to fix configuration issues
php artisan gdevelop:config:validate --fix
```

### Manual Setup Steps

1. **Copy environment configuration**:
   ```bash
   cp .env.example .env
   ```

2. **Enable GDevelop**:
   ```bash
   # Edit .env file
   GDEVELOP_ENABLED=true
   PLAYCANVAS_ENABLED=false
   ```

3. **Install CLI tools**:
   ```bash
   npm install -g gdevelop-cli gdcore-tools
   ```

4. **Create storage directories**:
   ```bash
   php artisan gdevelop:setup:verify --create-dirs
   ```

5. **Verify setup**:
   ```bash
   php artisan gdevelop:config:validate
   ```

## Engine Configuration Patterns

### GDevelop Only
```bash
GDEVELOP_ENABLED=true
PLAYCANVAS_ENABLED=false
```

### PlayCanvas Only
```bash
GDEVELOP_ENABLED=false
PLAYCANVAS_ENABLED=true
```

### Both Engines (Advanced)
```bash
GDEVELOP_ENABLED=true
PLAYCANVAS_ENABLED=true
```

## Troubleshooting

### Common Issues

1. **CLI tools not found**:
   - Install with: `npm install -g gdevelop-cli gdcore-tools`
   - Verify with: `gdexport --version` and `gdcore-tools --version`

2. **Storage permission errors**:
   - Run: `php artisan gdevelop:setup:verify --create-dirs`
   - Check directory permissions in `storage/gdevelop/`

3. **No engines enabled**:
   - Enable at least one engine: `GDEVELOP_ENABLED=true` or `PLAYCANVAS_ENABLED=true`

4. **Configuration validation fails**:
   - Run: `php artisan gdevelop:config:validate --fix`
   - Check the detailed error messages

### Debug Information

```bash
# Get detailed configuration info
php artisan gdevelop:config:validate -v

# Check feature flag status
php artisan tinker
>>> app(App\Services\FeatureFlagService::class)->getDebugInfo()
```

## Integration with SurrealPilot

When properly configured, GDevelop integration provides:

- **Workspace Creation**: GDevelop appears as an engine option
- **Chat Interface**: Natural language game development
- **Real-time Preview**: HTML5 game previews in the browser
- **Export System**: Downloadable ZIP files with complete games
- **Credit System**: Integration with SurrealPilot's credit tracking
- **Mobile Optimization**: Touch-friendly games by default

## Security Considerations

- GDevelop CLI tools run in sandboxed environments
- Game sessions are isolated per user/workspace
- File uploads are validated and size-limited
- Generated content is sanitized before execution
- Export files are automatically cleaned up after 24 hours