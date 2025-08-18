# Recent Chats Component

The Recent Chats component is a reusable frontend component for displaying and managing chat conversations in the SurrealPilot application. It provides a comprehensive interface for viewing, selecting, and managing conversations with support for both workspace-specific and global conversation views.

## Features

- **Conversation Listing**: Display conversations with titles, previews, timestamps, and message counts
- **Conversation Selection**: Click to select conversations with visual feedback
- **Conversation Deletion**: Delete conversations with confirmation modal
- **Real-time Updates**: Support for auto-refresh and manual refresh
- **Workspace Context**: Show conversations for specific workspaces or across all workspaces
- **Responsive Design**: Mobile-friendly interface with proper accessibility
- **Error Handling**: Graceful error states and retry functionality
- **Loading States**: Proper loading indicators and empty states

## Usage

### Basic Usage

```blade
<x-recent-chats 
    :workspace-id="$workspace->id"
    :limit="10"
/>
```

### Advanced Usage

```blade
<x-recent-chats 
    :workspace-id="$workspace->id"
    :show-workspace-info="true"
    :limit="20"
    container-class="bg-gray-800 rounded-lg border border-gray-700"
    header-class="p-4 border-b border-gray-700"
    list-class="p-4 space-y-2 max-h-80 overflow-y-auto"
    item-class="p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group"
    empty-state-class="text-center py-8"
/>
```

### JavaScript Integration

```javascript
// Initialize the component
const recentChats = new RecentChatsComponent({
    containerId: 'recent-chats-component',
    workspaceId: '1',
    limit: 10,
    showWorkspaceInfo: true,
    autoRefresh: true,
    refreshInterval: 30000,
    onConversationSelected: (conversationId, conversation) => {
        console.log('Conversation selected:', conversationId);
    },
    onConversationDeleted: (conversationId) => {
        console.log('Conversation deleted:', conversationId);
    },
    onError: (error) => {
        console.error('Component error:', error);
    }
});

// Public API methods
recentChats.refresh();
recentChats.addConversation(newConversation);
recentChats.updateConversation(conversationId, updates);
recentChats.removeConversation(conversationId);
recentChats.setWorkspaceId(newWorkspaceId);
```

## Component Props

### Blade Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `workspace-id` | `int\|null` | `null` | ID of workspace to filter conversations |
| `show-workspace-info` | `bool` | `false` | Show workspace information for each conversation |
| `limit` | `int` | `10` | Maximum number of conversations to display |
| `container-class` | `string` | `'bg-gray-800 rounded-lg'` | CSS classes for container |
| `header-class` | `string` | `'p-4 border-b border-gray-700'` | CSS classes for header |
| `list-class` | `string` | `'p-4 space-y-2'` | CSS classes for conversation list |
| `item-class` | `string` | `'p-3 bg-gray-700...'` | CSS classes for conversation items |
| `empty-state-class` | `string` | `'text-center py-8'` | CSS classes for empty state |

### JavaScript Component Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `containerId` | `string` | `'recent-chats-component'` | ID of container element |
| `workspaceId` | `string\|null` | `null` | Workspace ID for filtering |
| `limit` | `number` | `10` | Maximum conversations to load |
| `showWorkspaceInfo` | `boolean` | `false` | Show workspace info |
| `autoRefresh` | `boolean` | `false` | Enable automatic refresh |
| `refreshInterval` | `number` | `30000` | Auto-refresh interval in ms |
| `onConversationSelected` | `function` | `null` | Callback for conversation selection |
| `onConversationDeleted` | `function` | `null` | Callback for conversation deletion |
| `onError` | `function` | `null` | Callback for error handling |

## API Endpoints

The component interacts with the following API endpoints:

### Get Workspace Conversations
```
GET /api/workspaces/{workspaceId}/conversations
```

### Get Recent Conversations (All Workspaces)
```
GET /api/conversations/recent?limit={limit}
```

### Create Conversation
```
POST /api/workspaces/{workspaceId}/conversations
Content-Type: application/json

{
    "title": "Optional conversation title",
    "description": "Optional conversation description"
}
```

### Update Conversation
```
PUT /api/conversations/{conversationId}
Content-Type: application/json

{
    "title": "Updated title",
    "description": "Updated description"
}
```

### Delete Conversation
```
DELETE /api/conversations/{conversationId}
```

## Events

The component dispatches custom events that can be listened to:

### conversationSelected
Fired when a conversation is selected.

```javascript
container.addEventListener('conversationSelected', (event) => {
    const { conversationId, conversation } = event.detail;
    console.log('Selected conversation:', conversationId, conversation);
});
```

### conversationDeleted
Fired when a conversation is deleted.

```javascript
container.addEventListener('conversationDeleted', (event) => {
    const { conversationId } = event.detail;
    console.log('Deleted conversation:', conversationId);
});
```

### conversationsLoaded
Fired when conversations are loaded from the API.

```javascript
container.addEventListener('conversationsLoaded', (event) => {
    const { conversations } = event.detail;
    console.log('Loaded conversations:', conversations.length);
});
```

## Styling

The component uses Tailwind CSS classes and can be customized through the provided class props. Key styling features:

- **Dark Theme**: Designed for dark backgrounds with gray color scheme
- **Hover Effects**: Smooth transitions on hover and focus
- **Selection Feedback**: Visual indicators for selected conversations
- **Responsive Design**: Works on mobile and desktop
- **Accessibility**: Proper ARIA labels and keyboard navigation

### Custom CSS Classes

```css
/* Custom styles can be added to override defaults */
.recent-chats-component {
    /* Container styles */
}

.conversation-item {
    /* Individual conversation item styles */
}

.conversation-item.selected {
    /* Selected conversation styles */
}

.delete-conversation-btn {
    /* Delete button styles */
}
```

## Accessibility

The component includes proper accessibility features:

- **ARIA Labels**: Descriptive labels for screen readers
- **Keyboard Navigation**: Tab navigation and Enter/Space selection
- **Focus Management**: Proper focus indicators and management
- **Screen Reader Support**: Semantic HTML and ARIA attributes
- **High Contrast**: Sufficient color contrast for readability

## Error Handling

The component handles various error scenarios:

- **Network Errors**: Displays error state with retry option
- **API Errors**: Shows appropriate error messages
- **Loading Failures**: Graceful fallback to error state
- **Invalid Data**: Handles malformed API responses
- **Authentication Errors**: Proper error messaging

## Testing

The component includes comprehensive tests:

### Backend Tests
```bash
php artisan test --filter=RecentChatsComponentTest
```

### Frontend Tests
```bash
npm run test:jest -- tests/js/recent-chats-simple.test.js
```

### Integration Tests
```bash
npm run test:jest -- tests/js/recent-chats-integration.test.js
```

## Performance Considerations

- **Lazy Loading**: Conversations are loaded on demand
- **Pagination**: Supports limiting conversation count
- **Caching**: Browser caching for API responses
- **Debouncing**: Prevents excessive API calls
- **Memory Management**: Proper cleanup of event listeners

## Browser Support

- **Modern Browsers**: Chrome, Firefox, Safari, Edge (latest versions)
- **Mobile Browsers**: iOS Safari, Chrome Mobile
- **JavaScript**: ES6+ features with Babel transpilation
- **CSS**: Modern CSS features with fallbacks

## Migration Guide

### From Inline Implementation

If you're migrating from an inline conversation list implementation:

1. Replace the inline HTML with the component:
```blade
<!-- Old -->
<div id="conversations-list">
    @foreach($conversations as $conversation)
        <!-- conversation HTML -->
    @endforeach
</div>

<!-- New -->
<x-recent-chats :workspace-id="$workspace->id" />
```

2. Update JavaScript event handling:
```javascript
// Old
document.addEventListener('click', (e) => {
    if (e.target.closest('.conversation-item')) {
        // handle selection
    }
});

// New
container.addEventListener('conversationSelected', (event) => {
    const { conversationId, conversation } = event.detail;
    // handle selection
});
```

3. Update API calls to use the component's methods:
```javascript
// Old
fetch('/api/conversations').then(/* handle response */);

// New
recentChats.refresh(); // Component handles API calls
```

## Troubleshooting

### Common Issues

1. **Component not loading**: Check that the container element exists and has the correct ID
2. **API errors**: Verify authentication and API endpoint availability
3. **Styling issues**: Ensure Tailwind CSS is properly loaded
4. **Event handling**: Check that event listeners are attached after DOM ready

### Debug Mode

Enable debug logging:
```javascript
const recentChats = new RecentChatsComponent({
    // ... other options
    onError: (error) => {
        console.error('Recent Chats Error:', error);
    }
});
```

### Performance Issues

- Reduce the `limit` prop for better performance
- Disable `autoRefresh` if not needed
- Use `showWorkspaceInfo: false` for simpler rendering

## Contributing

When contributing to the Recent Chats component:

1. **Follow the existing code style** and patterns
2. **Add tests** for new functionality
3. **Update documentation** for any API changes
4. **Test accessibility** features
5. **Verify mobile compatibility**

### Development Setup

1. Install dependencies: `npm install`
2. Run tests: `npm run test:jest`
3. Build assets: `npm run build`
4. Start development server: `npm run dev`

## Changelog

### Version 1.0.0
- Initial implementation
- Basic conversation listing and selection
- Delete functionality with confirmation
- API integration
- Comprehensive testing
- Accessibility features
- Mobile responsiveness