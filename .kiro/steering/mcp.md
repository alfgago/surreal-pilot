# Model Context Protocol (MCP) Configuration

## Overview
SurrealPilot uses MCP servers to extend AI capabilities with specialized tools for development workflows, testing, and Laravel development.

## Configured MCP Servers

### Ref Tools MCP
- **Purpose**: Access to comprehensive documentation and reference materials
- **Type**: HTTP-based MCP server
- **Key Resources**: Laravel, Inertia.js, PlayCanvas, React, TypeScript
- **Key Tools**:
  - `search_docs`: Search documentation across multiple libraries and frameworks
  - `get_doc`: Retrieve specific documentation pages
  - `list_libraries`: List available documentation libraries
- **Usage**: Always use Ref Tools for documentation lookup instead of general web search

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

### Documentation Lookup Workflow
**ALWAYS use Ref Tools first for documentation:**
1. Use `search_docs` to find relevant documentation for Laravel, Inertia.js, PlayCanvas
2. Use `get_doc` to retrieve specific documentation pages
3. Reference official documentation before providing code examples
4. Focus on these key resources:
   - **Laravel**: Framework documentation, best practices, API reference
   - **Inertia.js**: React integration, routing, data sharing
   - **PlayCanvas**: Game engine API, components, scripting

### Laravel Development Workflow
When working on Laravel features:
1. Use `application_info` to understand the current Laravel setup and installed packages
2. Use `database_schema` to understand the current database structure
3. Use Ref Tools `search_docs` for Laravel documentation lookup
4. Use `tinker` to test code snippets and explore the application context
5. Use `list_routes` and `list_artisan_commands` to understand available functionality

### Testing Workflow
Testing is now handled by Pest 4 with browser testing capabilities:
1. Use Pest 4 for unit, feature, and browser tests
2. Leverage Pest's browser testing for end-to-end scenarios
3. Write expressive, readable tests using Pest's syntax
4. Use Pest's built-in assertions and helpers

### Best Practices
- **Always consult Ref Tools** for documentation before writing code
- Use `application_info` at the start of development sessions
- Use `database_query` for exploring data patterns before writing code
- Use `tinker` to prototype and test code before implementation
- Use `get_config` to understand application configuration
- Check `last_error` and `browser_logs` when debugging issues
- Write comprehensive Pest tests for all new features

## Auto-Approved Tools
All servers have comprehensive auto-approval for development efficiency:
- All Ref Tools are auto-approved for seamless documentation access
- All Laravel Boost tools are auto-approved for rapid development

## Installation Requirements
- **Ref Tools**: HTTP-based server, no local installation required
- **Laravel Boost**: Installed via Composer as a dev dependency, configured with `php artisan boost:install`
- **Pest 4**: Installed via Composer for testing with browser capabilities

## Configuration Location
MCP servers are configured in `.kiro/settings/mcp.json` with environment variables and auto-approval settings.