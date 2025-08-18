# Model Context Protocol (MCP) Configuration

## Overview
SurrealPilot uses MCP servers to extend AI capabilities with specialized tools for development workflows, testing, and Laravel development.

## Configured MCP Servers

### Puppeteer MCP
- **Purpose**: Browser automation and end-to-end testing
- **Location**: Local server at `mcp-servers/puppeteer-mcp/server.js`
- **Key Tools**:
  - `launch_browser`: Start browser instances for testing
  - `navigate_to_url`: Navigate to specific URLs
  - `take_screenshot`: Capture page screenshots
  - `click_element`: Interact with page elements
  - `type_text`: Input text into forms
  - `get_page_content`: Extract page content
  - `test_game_generation`: Specialized SurrealPilot workflow testing

### Laravel Boost MCP
- **Purpose**: Laravel development acceleration with context-aware tools and documentation
- **Repository**: https://github.com/laravel/boost
- **Installation**: Installed via Composer (`laravel/boost --dev`) and configured with `php artisan boost:install`
- **Key Tools**:
  - `application_info`: Read PHP & Laravel versions, database engine, ecosystem packages, and Eloquent models
  - `browser_logs`: Read logs and errors from the browser
  - `database_connections`: Inspect available database connections
  - `database_query`: Execute queries against the database
  - `database_schema`: Read the database schema
  - `get_absolute_url`: Convert relative path URIs to absolute URLs
  - `get_config`: Get configuration values using dot notation
  - `last_error`: Read the last error from application logs
  - `list_artisan_commands`: Inspect available Artisan commands
  - `list_available_config_keys`: Inspect available configuration keys
  - `list_available_env_vars`: Inspect available environment variable keys
  - `list_routes`: Inspect the application's routes
  - `read_log_entries`: Read the last N log entries
  - `report_feedback`: Share Boost & Laravel AI feedback with the team
  - `search_docs`: Query Laravel hosted documentation API with semantic search
  - `tinker`: Execute arbitrary code within the application context

## Usage Guidelines

### Laravel Development Workflow
When working on Laravel features:
1. Use `application_info` to understand the current Laravel setup and installed packages
2. Use `database_schema` to understand the current database structure
3. Use `search_docs` to find relevant Laravel documentation for your specific package versions
4. Use `tinker` to test code snippets and explore the application context
5. Use `list_routes` and `list_artisan_commands` to understand available functionality

### Testing Workflow
When implementing testing features:
1. Use Puppeteer MCP for browser automation
2. Create comprehensive test scenarios
3. Capture screenshots for visual verification
4. Test both desktop and mobile interfaces

### Best Practices
- Use `application_info` at the start of development sessions to understand the current setup
- Leverage `search_docs` for version-specific Laravel documentation
- Use `database_query` for exploring data patterns before writing code
- Use `tinker` to prototype and test code before implementation
- Use `get_config` to understand application configuration
- Check `last_error` and `browser_logs` when debugging issues

## Auto-Approved Tools
Both servers have comprehensive auto-approval for development efficiency:
- All Laravel Boost tools are auto-approved for rapid development
- All Puppeteer tools are auto-approved for seamless testing

## Installation Requirements
- **Laravel Boost**: Installed via Composer as a dev dependency, configured with `php artisan boost:install`
- **Puppeteer**: Uses local Node.js server (already configured)

## Configuration Location
MCP servers are configured in `.kiro/settings/mcp.json` with environment variables and auto-approval settings.