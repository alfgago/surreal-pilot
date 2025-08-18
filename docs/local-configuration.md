# Local Configuration Management

The SurrealPilot desktop application uses a local configuration system to manage API keys, provider preferences, and server settings. This document explains how the system works and how to use it.

## Configuration File Location

The configuration is stored in a JSON file at:
- **Windows**: `%USERPROFILE%\.surrealpilot\config.json`
- **macOS/Linux**: `~/.surrealpilot/config.json`

## Configuration Structure

```json
{
    "preferred_provider": "openai",
    "api_keys": {
        "openai": null,
        "anthropic": null,
        "gemini": null
    },
    "saas_token": null,
    "saas_url": "https://api.surrealpilot.com",
    "server_port": 8000,
    "created_at": "2025-01-29T10:30:00.000Z",
    "updated_at": "2025-01-29T10:35:00.000Z"
}
```

## LocalConfigManager Service

The `LocalConfigManager` service provides a programmatic interface to manage the configuration:

### Key Methods

- `getConfig()`: Get the complete configuration
- `updateConfig(array $updates)`: Update configuration with new values
- `getApiKeys()`: Get all API keys
- `setApiKey(string $provider, ?string $key)`: Set API key for a provider
- `getPreferredProvider()`: Get the preferred AI provider
- `setPreferredProvider(string $provider)`: Set the preferred AI provider
- `getSaasToken()`: Get the SaaS API token
- `setSaasToken(?string $token)`: Set the SaaS API token
- `getServerPort()`: Get the current server port
- `findAvailablePort()`: Find and set an available port

### Port Collision Handling

The system automatically handles port collisions:

1. Tries the default port (8000)
2. Falls back to port 8001 if 8000 is busy
3. Continues searching for available ports up to 8100
4. Updates the config file with the selected port

This ensures the Unreal Engine plugin can always find the correct port by reading the config file.

## Desktop API Endpoints

The desktop application exposes several API endpoints for configuration management:

### GET `/api/desktop/config`
Returns the current configuration (with masked API keys for security).

### POST `/api/desktop/config`
Updates the configuration. Accepts:
- `preferred_provider`: AI provider preference
- `api_keys`: Object with provider API keys
- `saas_token`: SaaS API token
- `saas_url`: SaaS API URL

### GET `/api/desktop/server-info`
Returns server information including current port and URL.

### POST `/api/desktop/test-connection`
Tests connections to services:
- `service: "ollama"`: Tests local Ollama connection
- `service: "saas"`: Tests SaaS API connection

## Settings UI

The desktop application includes a settings interface at `/settings` that allows users to:

- Configure AI provider preferences
- Set API keys for different providers
- Test connections to Ollama and SaaS services
- View current server configuration
- Reset settings to defaults

## Security Considerations

- API keys are stored in plain text in the local config file
- The config directory is created with 755 permissions
- API keys are masked when returned via API endpoints
- The settings UI clears password fields after saving

## Integration with Unreal Engine Plugin

The UE plugin reads the config file to determine:
- Which port the desktop server is running on
- Fallback URLs for SaaS integration
- User preferences for AI providers

The plugin should check the config file on startup and periodically to handle port changes.

## Error Handling

The LocalConfigManager includes comprehensive error handling:
- Creates config directory and file if they don't exist
- Falls back to defaults if config file is corrupted
- Logs errors without throwing exceptions
- Validates JSON before saving

## Testing

The system includes comprehensive tests:
- Unit tests for LocalConfigManager (`tests/Unit/LocalConfigManagerTest.php`)
- Feature tests for ConfigController (`tests/Feature/Desktop/ConfigControllerTest.php`)

Run tests with:
```bash
php artisan test tests/Unit/LocalConfigManagerTest.php
php artisan test tests/Feature/Desktop/ConfigControllerTest.php
```