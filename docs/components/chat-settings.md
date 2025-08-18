# Chat Settings Component

A comprehensive modal component for managing AI chat settings including model selection, temperature, max tokens, and streaming options.

## Features

- **AI Model Selection**: Choose from available AI models including PlayCanvas-specific models
- **Temperature Control**: Adjust creativity level with visual slider
- **Token Limit**: Set maximum response length
- **Streaming Options**: Enable/disable real-time response streaming
- **Validation**: Client-side and server-side validation
- **Persistence**: Settings are saved to the server and cached
- **Accessibility**: Full keyboard navigation and screen reader support
- **Responsive**: Works on desktop and mobile devices

## Usage

### Basic Implementation

```blade
<!-- Include the component in your Blade template -->
<x-chat-settings />

<!-- Add a trigger button -->
<button id="open-chat-settings" class="btn btn-primary">
    Open Chat Settings
</button>
```

### Custom Configuration

```blade
<x-chat-settings 
    modal-id="custom-settings-modal"
    trigger-id="custom-trigger"
    container-class="fixed inset-0 bg-black bg-opacity-75 z-50"
    modal-class="bg-gray-900 rounded-xl max-w-3xl w-full"
/>
```

### JavaScript Integration

```javascript
import { ChatSettingsComponent } from './components/chat-settings.js';

// Initialize with default options
const chatSettings = new ChatSettingsComponent();

// Initialize with custom options
const chatSettings = new ChatSettingsComponent({
    modalId: 'my-settings-modal',
    autoLoad: true,
    showToasts: true,
    onSettingsSaved: (settings) => {
        console.log('Settings saved:', settings);
    },
    onModelChanged: (modelId, model) => {
        console.log('Model changed to:', modelId);
    }
});
```

## Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `modalId` | string | `'chat-settings-modal'` | ID of the modal element |
| `triggerId` | string | `'open-chat-settings'` | ID of the trigger button |
| `containerClass` | string | `'fixed inset-0 bg-black bg-opacity-50 z-50 hidden'` | CSS classes for modal container |
| `modalClass` | string | `'bg-gray-800 rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto'` | CSS classes for modal content |
| `headerClass` | string | `'p-6 border-b border-gray-700'` | CSS classes for modal header |
| `bodyClass` | string | `'p-6 space-y-6'` | CSS classes for modal body |
| `footerClass` | string | `'p-6 border-t border-gray-700 flex justify-end space-x-3'` | CSS classes for modal footer |

## JavaScript Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `modalId` | string | `'chat-settings-modal'` | ID of the modal element |
| `triggerId` | string | `'open-chat-settings'` | ID of the trigger button |
| `autoLoad` | boolean | `true` | Automatically load settings on initialization |
| `validateOnChange` | boolean | `true` | Validate settings as user types |
| `showToasts` | boolean | `true` | Show success/error toast notifications |
| `onSettingsSaved` | function | `null` | Callback when settings are saved |
| `onSettingsChanged` | function | `null` | Callback when settings change |
| `onModelChanged` | function | `null` | Callback when AI model changes |
| `onError` | function | `null` | Callback when errors occur |

## API Endpoints

The component interacts with the following API endpoints:

### GET /api/chat/settings
Retrieve current user chat settings.

**Response:**
```json
{
    "success": true,
    "settings": {
        "ai_model": "claude-sonnet-4-20250514",
        "temperature": 0.7,
        "max_tokens": 1024,
        "streaming_enabled": true
    }
}
```

### POST /api/chat/settings
Save user chat settings.

**Request:**
```json
{
    "ai_model": "gpt-4",
    "temperature": 0.8,
    "max_tokens": 2048,
    "streaming_enabled": false
}
```

**Response:**
```json
{
    "success": true,
    "message": "Chat settings saved successfully",
    "settings": {
        "ai_model": "gpt-4",
        "temperature": 0.8,
        "max_tokens": 2048,
        "streaming_enabled": false
    }
}
```

### GET /api/chat/models
Get available AI models.

**Response:**
```json
{
    "success": true,
    "models": [
        {
            "id": "claude-sonnet-4-20250514",
            "name": "PlayCanvas Model (claude-sonnet-4-20250514)",
            "provider": "anthropic",
            "description": "Optimized for PlayCanvas game development",
            "available": true,
            "engine_type": "playcanvas"
        },
        {
            "id": "gpt-4",
            "name": "GPT-4",
            "provider": "openai",
            "description": "OpenAI GPT-4 model",
            "available": true,
            "engine_type": null
        }
    ]
}
```

### POST /api/chat/settings/reset
Reset settings to defaults.

**Response:**
```json
{
    "success": true,
    "message": "Chat settings reset to defaults",
    "settings": {
        "ai_model": "claude-sonnet-4-20250514",
        "temperature": 0.7,
        "max_tokens": 1024,
        "streaming_enabled": true
    }
}
```

## Events

The component dispatches custom events that you can listen to:

```javascript
const modal = document.getElementById('chat-settings-modal');

modal.addEventListener('settingsOpened', () => {
    console.log('Settings modal opened');
});

modal.addEventListener('settingsClosed', () => {
    console.log('Settings modal closed');
});

modal.addEventListener('settingsLoaded', (e) => {
    console.log('Settings loaded:', e.detail.settings);
});

modal.addEventListener('settingsSaved', (e) => {
    console.log('Settings saved:', e.detail.settings);
});

modal.addEventListener('modelChanged', (e) => {
    console.log('Model changed:', e.detail.modelId, e.detail.model);
});

modal.addEventListener('settingsChanged', (e) => {
    console.log('Settings changed (unsaved):', e.detail.settings);
});
```

## Public Methods

### `open()`
Open the settings modal.

```javascript
chatSettings.open();
```

### `close()`
Close the settings modal.

```javascript
chatSettings.close();
```

### `refresh()`
Reload settings from the server.

```javascript
await chatSettings.refresh();
```

### `getCurrentSettings()`
Get the current settings object.

```javascript
const settings = chatSettings.getCurrentSettings();
```

### `getAvailableModels()`
Get the list of available AI models.

```javascript
const models = chatSettings.getAvailableModels();
```

### `getSelectedModel()`
Get the currently selected AI model.

```javascript
const model = chatSettings.getSelectedModel();
```

### `setSettings(settings)`
Programmatically update settings.

```javascript
chatSettings.setSettings({
    temperature: 0.8,
    max_tokens: 2048
});
```

### `isOpen()`
Check if the modal is currently open.

```javascript
if (chatSettings.isOpen()) {
    console.log('Modal is open');
}
```

### `hasChanges()`
Check if there are unsaved changes.

```javascript
if (chatSettings.hasChanges()) {
    console.log('There are unsaved changes');
}
```

## Styling

The component uses Tailwind CSS classes and can be customized through CSS variables or by overriding the default classes.

### CSS Custom Properties

```css
:root {
    --chat-settings-bg: theme('colors.gray.800');
    --chat-settings-border: theme('colors.gray.700');
    --chat-settings-text: theme('colors.white');
    --chat-settings-accent: theme('colors.indigo.600');
}
```

### Custom Styling

```css
/* Override modal background */
.chat-settings-modal-content {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
}

/* Custom model selection styling */
.model-option label {
    border: 2px solid transparent;
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

.model-option input:checked + label {
    border-color: #6366f1;
    background: linear-gradient(135deg, #312e81 0%, #1e1b4b 100%);
}
```

## AI_MODEL_PLAYCANVAS Integration

The component automatically includes the `AI_MODEL_PLAYCANVAS` environment variable as an available model option:

```env
AI_MODEL_PLAYCANVAS=claude-sonnet-4-20250514
```

This model will appear in the selection list with:
- Engine type: `playcanvas`
- Special styling to indicate it's optimized for PlayCanvas
- Priority placement in the model list

## Validation Rules

### Temperature
- **Range**: 0.0 to 2.0
- **Step**: 0.1
- **Default**: 0.7

### Max Tokens
- **Range**: 1 to 8000
- **Default**: 1024

### AI Model
- Must be selected from available models
- Must be currently available (not disabled)
- Validates against server-side model list

### Streaming
- Boolean value
- **Default**: true

## Error Handling

The component handles various error scenarios:

1. **Network Errors**: Shows retry option
2. **Validation Errors**: Displays specific error messages
3. **API Errors**: Shows user-friendly error messages
4. **Missing Models**: Gracefully handles empty model lists
5. **Unsaved Changes**: Warns user before closing

## Accessibility

The component is fully accessible:

- **Keyboard Navigation**: Tab through all interactive elements
- **Screen Readers**: Proper ARIA labels and descriptions
- **Focus Management**: Maintains focus within modal
- **High Contrast**: Supports high contrast mode
- **Reduced Motion**: Respects user motion preferences

## Testing

The component includes comprehensive tests:

```bash
# Run unit tests
npm test chat-settings.test.js

# Run integration tests
npm test chat-settings-integration.test.js

# Run simple tests
npm test chat-settings-simple.test.js
```

## Browser Support

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile**: iOS Safari 14+, Chrome Mobile 90+
- **Features**: Uses modern JavaScript (ES6+) and CSS Grid/Flexbox

## Performance

- **Lazy Loading**: Models loaded only when needed
- **Caching**: Settings cached for 30 days
- **Debouncing**: Input changes debounced to prevent excessive API calls
- **Memory Management**: Proper cleanup on component destruction

## Security

- **CSRF Protection**: All API requests include CSRF tokens
- **Input Validation**: Client and server-side validation
- **XSS Prevention**: All user input properly escaped
- **API Authentication**: Requires authenticated user

## Migration Guide

### From v1.x to v2.x

1. Update component initialization:
```javascript
// Old
const settings = new ChatSettings();

// New
const settings = new ChatSettingsComponent();
```

2. Update event listeners:
```javascript
// Old
settings.on('saved', callback);

// New
modal.addEventListener('settingsSaved', callback);
```

3. Update CSS classes:
```css
/* Old */
.chat-settings { }

/* New */
.chat-settings-modal-content { }
```

## Troubleshooting

### Common Issues

1. **Modal not opening**: Check that trigger element exists and has correct ID
2. **Settings not saving**: Verify API endpoints are accessible and CSRF token is present
3. **Models not loading**: Check network connectivity and API response format
4. **Styling issues**: Ensure Tailwind CSS is properly loaded

### Debug Mode

Enable debug logging:

```javascript
const chatSettings = new ChatSettingsComponent({
    debug: true
});
```

This will log all API calls, state changes, and events to the console.