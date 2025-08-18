# My Games Component

The My Games component is a comprehensive frontend component for displaying and managing user games in the SurrealPilot application. It provides a rich interface for viewing games in both grid and list formats, launching games, and managing game lifecycle.

## Features

### Display Features
- **Grid and List Views**: Toggle between card-based grid view and compact list view
- **Game Thumbnails**: Display game preview images or engine-specific icons
- **Game Metadata**: Show titles, descriptions, creation dates, and engine types
- **Status Indicators**: Visual indicators for published vs draft games
- **Workspace Information**: Optional display of workspace context
- **Empty State**: Helpful empty state with call-to-action buttons

### Interactive Features
- **Game Launch**: Direct launching of games with preview URLs
- **Game Deletion**: Secure deletion with confirmation modal
- **Game Details**: Detailed modal view with game information and preview
- **Refresh Functionality**: Manual refresh of game list
- **Error Handling**: Graceful error states with retry options
- **Responsive Design**: Mobile-optimized interface

## Usage

### Basic Usage

```blade
<x-my-games />
```

### Workspace Specific

```blade
<x-my-games :workspace-id="$workspace->id" />
```

### Custom Configuration

```blade
<x-my-games 
    :workspace-id="$workspace->id"
    :show-workspace-info="true"
    :limit="20"
    container-class="bg-gray-800 rounded-lg border border-gray-700"
    header-class="p-4 border-b border-gray-700"
    grid-class="p-4"
    empty-state-class="text-center py-12"
/>
```

## Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `workspaceId` | `int\|null` | `null` | Filter games by specific workspace |
| `showWorkspaceInfo` | `bool` | `false` | Show workspace information for each game |
| `limit` | `int` | `20` | Maximum number of games to display |
| `containerClass` | `string` | `'bg-gray-800 rounded-lg'` | CSS classes for main container |
| `headerClass` | `string` | `'p-4 border-b border-gray-700'` | CSS classes for header section |
| `gridClass` | `string` | `'p-4'` | CSS classes for games grid/list container |
| `emptyStateClass` | `string` | `'text-center py-12'` | CSS classes for empty state |

## JavaScript API

### Initialization

```javascript
// Auto-initialization (recommended)
// Component automatically initializes when DOM is ready

// Manual initialization
import { MyGamesComponent } from './components/my-games.js';
const myGames = new MyGamesComponent('my-games-component', {
    workspaceId: '123',
    limit: 10,
    viewMode: 'list',
    showWorkspaceInfo: true
});
```

### Public Methods

#### `refresh()`
Reload games from the API.

```javascript
myGames.refresh();
```

#### `getGames()`
Get a copy of the current games array.

```javascript
const games = myGames.getGames();
```

#### `addGame(game)`
Add a new game to the component.

```javascript
myGames.addGame({
    id: 123,
    title: 'New Game',
    description: 'A new game',
    engine_type: 'playcanvas'
});
```

#### `updateGame(gameId, updates)`
Update an existing game.

```javascript
myGames.updateGame(123, {
    title: 'Updated Title',
    is_published: true
});
```

#### `removeGame(gameId)`
Remove a game from the component.

```javascript
myGames.removeGame(123);
```

#### `setViewMode(mode)`
Change the view mode.

```javascript
myGames.setViewMode('list'); // or 'grid'
```

### Events

The component dispatches custom events that you can listen for:

#### `gamesLoaded`
Fired when games are successfully loaded from the API.

```javascript
document.addEventListener('gamesLoaded', (e) => {
    console.log('Games loaded:', e.detail.games);
});
```

#### `gameDeleted`
Fired when a game is successfully deleted.

```javascript
document.addEventListener('gameDeleted', (e) => {
    console.log('Game deleted:', e.detail.gameId);
});
```

#### `gameLaunched`
Fired when a game is launched.

```javascript
document.addEventListener('gameLaunched', (e) => {
    console.log('Game launched:', e.detail.game);
});
```

#### `viewModeChanged`
Fired when the view mode is changed.

```javascript
document.addEventListener('viewModeChanged', (e) => {
    console.log('View mode changed to:', e.detail.viewMode);
});
```

#### `gameDetailsShown`
Fired when the game details modal is shown.

```javascript
document.addEventListener('gameDetailsShown', (e) => {
    console.log('Showing details for:', e.detail.game);
});
```

## API Integration

The component integrates with the following Laravel API endpoints:

### Get Recent Games
```
GET /api/games/recent?limit={limit}
```

### Get Workspace Games
```
GET /api/workspaces/{workspaceId}/games
```

### Delete Game
```
DELETE /api/games/{gameId}
```

## Game Data Structure

The component expects games to have the following structure:

```javascript
{
    id: 1,
    title: "Game Title",
    description: "Game description",
    engine_type: "playcanvas", // or "unreal"
    thumbnail_url: "https://example.com/thumb.jpg", // optional
    preview_url: "https://example.com/preview", // optional
    published_url: "https://example.com/published", // optional
    display_url: "https://example.com/game", // preview_url or published_url
    is_published: true,
    has_preview: true,
    has_thumbnail: false,
    created_at: "2023-01-01T00:00:00Z",
    updated_at: "2023-01-01T00:00:00Z",
    workspace: { // optional
        id: 1,
        name: "Workspace Name",
        engine_type: "playcanvas"
    }
}
```

## Styling

The component uses Tailwind CSS classes and includes custom CSS for enhanced styling:

### CSS Classes
- `.game-item`: Individual game card/list item
- `.launch-game-btn`: Game launch button
- `.delete-game-btn`: Game delete button
- `.grid-view-icon`: Grid view toggle icon
- `.list-view-icon`: List view toggle icon

### Custom CSS
The component includes custom CSS in `resources/css/components/my-games.css` for:
- Hover effects and animations
- Line clamping for text truncation
- Modal styling
- Responsive design
- Loading states

## Accessibility

The component includes accessibility features:

- **ARIA Labels**: Proper labeling for screen readers
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Proper focus handling in modals
- **Semantic HTML**: Proper use of semantic elements
- **Color Contrast**: High contrast colors for readability

## Testing

The component includes comprehensive tests:

### Unit Tests
- Component initialization
- State management
- Event handling
- Utility functions
- Error handling

### Integration Tests
- API integration
- User interactions
- Event dispatching
- Error scenarios

### Test Files
- `tests/js/my-games.test.js` - Comprehensive unit tests
- `tests/js/my-games-integration.test.js` - Integration tests
- `tests/js/my-games-simple.test.js` - Basic functionality tests

## Browser Support

The component supports modern browsers with ES6+ features:
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Performance Considerations

- **Lazy Loading**: Games are loaded on demand
- **Efficient Rendering**: Minimal DOM manipulation
- **Event Delegation**: Efficient event handling
- **Memory Management**: Proper cleanup on destroy
- **Responsive Images**: Optimized image loading

## Security

- **CSRF Protection**: Includes CSRF tokens in API requests
- **XSS Prevention**: HTML escaping for user content
- **Input Validation**: Client-side validation
- **Secure Deletion**: Confirmation required for destructive actions

## Troubleshooting

### Common Issues

#### Games Not Loading
1. Check API endpoints are accessible
2. Verify CSRF token is present
3. Check browser console for errors
4. Ensure user has proper permissions

#### Styling Issues
1. Verify Tailwind CSS is loaded
2. Check custom CSS is included
3. Ensure proper CSS class names
4. Check for CSS conflicts

#### JavaScript Errors
1. Check browser console for errors
2. Verify component is properly initialized
3. Ensure all required DOM elements exist
4. Check for JavaScript conflicts

### Debug Mode

Enable debug mode by setting `window.myGamesDebug = true` before component initialization:

```javascript
window.myGamesDebug = true;
// Component will log debug information to console
```

## Contributing

When contributing to the My Games component:

1. Follow the existing code style
2. Add tests for new features
3. Update documentation
4. Ensure accessibility compliance
5. Test across different browsers
6. Verify mobile responsiveness

## Changelog

### Version 1.0.0
- Initial release
- Grid and list view modes
- Game launch and deletion
- Responsive design
- Comprehensive testing
- Full accessibility support