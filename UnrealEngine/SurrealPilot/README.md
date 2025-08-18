# SurrealPilot Unreal Engine Plugin

SurrealPilot is an AI copilot plugin for Unreal Engine that provides intelligent assistance for Blueprint development, error resolution, and code optimization.

## Features

- **AI-Powered Assistance**: Get intelligent suggestions and fixes for your Blueprint code
- **Multiple AI Providers**: Support for OpenAI, Anthropic, Google Gemini, and local Ollama
- **Context-Aware**: Automatically exports Blueprint context and build errors for AI analysis
- **Streaming Responses**: Real-time AI responses with Server-Sent Events
- **Desktop Integration**: Works with SurrealPilot desktop application for offline usage
- **Credit Management**: Integrated with SurrealPilot's credit system for usage tracking

## Installation

1. Copy the `SurrealPilot` folder to your project's `Plugins` directory
2. Regenerate project files
3. Build your project
4. Enable the plugin in the Unreal Engine Plugin Manager

## Configuration

The plugin can be configured through:
- **Editor Preferences**: Go to Edit → Editor Preferences → Plugins → SurrealPilot
- **Local Config**: Settings are stored in `~/.surrealpilot/config.json`

### Settings

- **Preferred AI Provider**: Choose your default AI provider
- **API Configuration**: Set up connection to SurrealPilot desktop app or SaaS
- **Context Export**: Configure automatic context export behavior
- **Debug Options**: Enable logging for troubleshooting

## Usage

### Chat Window
Access the AI chat through:
- **Menu**: Window → SurrealPilot Chat
- **Toolbar**: Click the SurrealPilot icon in the toolbar

### Automatic Context Export
The plugin automatically exports context when:
- Blueprint compilation errors occur (if enabled)
- Blueprint selection changes (if enabled)

### Manual Context Export
Access context export through **Tools → SurrealPilot**:

#### Blueprint Context Export
- Select a Blueprint asset in the Content Browser
- Choose **Export Blueprint Context**
- Blueprint structure, variables, functions, and nodes are exported as JSON

#### Selection Context Export  
- Select any objects in the editor (actors, components, nodes)
- Choose **Export Selection Context**
- Selected objects' information is exported as JSON

#### Build Error Capture
- Choose **Start Error Capture** to begin monitoring build messages
- Compile Blueprints or build your project
- Choose **Stop Error Capture** to finish monitoring
- Choose **Export Build Errors** to export captured errors as JSON

### JSON Export Formats

**Blueprint Context:**
```json
{
  "name": "MyBlueprint",
  "path": "/Game/Blueprints/MyBlueprint", 
  "type": "Blueprint",
  "parentClass": "Actor",
  "variables": [...],
  "functions": [...],
  "graphs": [...]
}
```

**Build Errors:**
```json
{
  "type": "BuildErrors",
  "errorCount": 2,
  "errors": [
    {
      "severity": "Error",
      "description": "Blueprint compilation failed",
      "file": "...",
      "line": "..."
    }
  ]
}
```

## API Integration

The plugin communicates with:
1. **Desktop App**: `http://127.0.0.1:8000` (default)
2. **SaaS API**: Fallback to cloud service

### Authentication
- Desktop mode: Uses local API keys stored in config
- SaaS mode: Requires valid API token

## Development

### Building from Source
1. Ensure you have Unreal Engine 5.0+ installed
2. Place the plugin in your project's `Plugins` folder
3. Generate Visual Studio project files
4. Build the project

### Dependencies
- Unreal Engine 5.0+
- HTTP module
- JSON support
- Editor scripting utilities

## Troubleshooting

### Common Issues

**Plugin not loading**
- Check that all dependencies are available
- Verify the plugin is enabled in Plugin Manager
- Check the Output Log for error messages

**API connection failed**
- Verify SurrealPilot desktop app is running
- Check firewall settings
- Validate API key configuration

**Context export not working**
- Enable debug logging in settings
- Check that auto-export options are enabled
- Verify Blueprint is properly selected

### Debug Logging
Enable debug logging in plugin settings to troubleshoot:
- HTTP request/response logging
- Context export logging
- API communication details

## Support

For support and documentation:
- Documentation: https://docs.surrealpilot.com
- Support: https://support.surrealpilot.com
- Issues: Report through the SurrealPilot dashboard

## License

This plugin is part of the SurrealPilot ecosystem. See LICENSE file for details.