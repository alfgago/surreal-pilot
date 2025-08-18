# Mobile-Optimized UI Components

This document describes the mobile-optimized UI components implemented for the PlayCanvas integration in SurrealPilot.

## Overview

The mobile UI components provide a touch-friendly, responsive interface optimized for mobile game development using PlayCanvas. The implementation follows mobile-first design principles and includes PWA capabilities.

## Components

### 1. Mobile Chat Interface (`resources/views/mobile/chat.blade.php`)

**Features:**
- Touch-optimized message input with auto-resize
- Smart suggestions for PlayCanvas commands
- Character counter with visual feedback
- Haptic feedback simulation
- Responsive design for portrait/landscape orientations

**Key Elements:**
- `#mobile-message-input`: Main chat input with 16px font size to prevent iOS zoom
- `#mobile-send-btn`: 44x44px touch target for send button
- `#mobile-suggestions`: Dynamic suggestion chips
- `#mobile-typing-indicator`: Animated typing indicator

### 2. Demo Chooser Modal

**Features:**
- Bottom sheet modal design optimized for mobile
- Large touch targets (minimum 44x44px)
- Smooth animations with hardware acceleration
- Template cards with preview images and metadata

**Key Elements:**
- `#mobile-demo-modal`: Full-screen overlay
- `#mobile-demo-panel`: Sliding bottom panel
- `.demo-card-mobile`: Touch-friendly template cards

### 3. Workspace Actions Toolbar

**Features:**
- Contextual toolbar that appears when workspace is active
- Preview and Publish buttons optimized for thumb navigation
- Status indicators with color coding
- Responsive layout for different screen sizes

**Key Elements:**
- `#mobile-workspace-actions`: Collapsible action bar
- `#mobile-preview-btn`: Preview game button
- `#mobile-publish-btn`: One-tap publish button

### 4. Mobile Navigation

**Features:**
- Slide-out menu with smooth animations
- Credit balance display with color-coded status
- Provider selection optimized for mobile
- Safe area handling for notched devices

**Key Elements:**
- `#mobile-menu-btn`: Hamburger menu trigger
- `#mobile-menu-panel`: Sliding navigation panel
- `#mobile-credit-badge`: Real-time credit display

### 5. Smart Suggestions System

**Features:**
- Context-aware PlayCanvas command suggestions
- Auto-complete functionality
- Touch-friendly suggestion chips
- Filtered suggestions based on user input

**API Endpoint:** `/api/mobile/playcanvas-suggestions`

**Common Suggestions:**
- "double the jump height"
- "make enemies faster"
- "change the lighting to sunset"
- "add more particles"
- "increase player speed"

## CSS Classes and Utilities

### Touch Optimization
```css
.touch-target          /* Minimum 44x44px touch targets */
.haptic-feedback       /* Visual feedback on touch */
.mobile-transition     /* Smooth animations */
```

### Layout
```css
.mobile-chat-container /* Full viewport height container */
.mobile-message-bubble /* Optimized message styling */
.mobile-modal          /* Bottom sheet modal */
.landscape-compact     /* Landscape orientation adjustments */
```

### Safe Area Support
```css
.safe-area-top         /* Top safe area padding */
.safe-area-bottom      /* Bottom safe area padding */
```

## PWA Features

### Manifest (`/manifest.json`)
- Standalone display mode
- Portrait orientation preference
- Custom theme colors
- App icons for home screen

### Service Worker Ready
- Offline capability structure
- Cache strategies for assets
- Background sync support

## Responsive Design

### Breakpoints
- **Mobile Portrait**: 320px - 414px width
- **Mobile Landscape**: 568px - 896px width (height < 500px)
- **Tablet**: 768px+ width

### Orientation Handling
- Portrait: Standard mobile layout
- Landscape: Compact mode with reduced padding and font sizes

## Accessibility Features

### Touch Targets
- Minimum 44x44px size (WCAG AA compliance)
- Adequate spacing between interactive elements
- Visual feedback on touch

### Typography
- 16px minimum font size to prevent iOS zoom
- High contrast text colors
- Readable line heights

### Navigation
- Keyboard navigation support
- Screen reader friendly labels
- Focus indicators

## Performance Optimizations

### CSS
- Hardware-accelerated animations
- Efficient selectors
- Minimal repaints and reflows

### JavaScript
- Event delegation
- Debounced input handlers
- Lazy loading for suggestions

### Network
- Compressed assets
- Efficient API calls
- Offline fallbacks

## Testing

### Unit Tests
- Component rendering
- API endpoint responses
- Device detection logic

### Integration Tests (Playwright)
- Touch interactions
- Responsive design
- Cross-browser compatibility
- Performance metrics

### Test Commands
```bash
# Laravel tests
php artisan test tests/Feature/MobileUIComponentsTest.php

# Playwright mobile tests
npm run test:mobile

# Playwright with browser UI
npm run test:mobile:headed

# Generate test report
npm run test:mobile:report
```

## Browser Support

### Mobile Browsers
- **iOS Safari**: 14.0+
- **Chrome Mobile**: 90+
- **Firefox Mobile**: 88+
- **Samsung Internet**: 14.0+

### PWA Support
- **iOS**: 14.3+ (limited PWA features)
- **Android**: Full PWA support
- **Desktop**: Chrome, Edge, Firefox

## API Endpoints

### Mobile-Specific APIs
```
GET /api/mobile/demos                    # Get demo templates
GET /api/mobile/device-info              # Device detection
GET /api/mobile/workspace/{id}/status    # Workspace status
GET /api/mobile/playcanvas-suggestions   # Command suggestions
```

### Response Formats
All mobile APIs return JSON with consistent structure:
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

## Usage Examples

### Basic Chat Interaction
```javascript
// Send message
mobileChatInterface.sendMessage();

// Add suggestion
mobileChatInterface.applySuggestion('double the jump height');

// Show typing indicator
mobileChatInterface.showTypingIndicator();
```

### Demo Selection
```javascript
// Open demo modal
mobileChatInterface.openDemoModal();

// Select demo template
mobileChatInterface.selectDemo('starter-fps');
```

### Workspace Actions
```javascript
// Preview game
mobileChatInterface.openPreview();

// Publish workspace
mobileChatInterface.publishWorkspace();
```

## Customization

### Theme Colors
Update in `resources/views/mobile/layout.blade.php`:
```html
<meta name="theme-color" content="#1f2937">
```

### Touch Target Sizes
Modify in `resources/css/mobile.css`:
```css
.touch-target {
    min-height: 44px;
    min-width: 44px;
}
```

### Animation Durations
Adjust in CSS variables:
```css
.mobile-transition {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
```

## Troubleshooting

### Common Issues

1. **iOS Zoom on Input Focus**
   - Ensure font-size is 16px or larger
   - Use `user-scalable=no` in viewport meta tag

2. **Touch Targets Too Small**
   - Verify minimum 44x44px size
   - Check spacing between elements

3. **Landscape Layout Issues**
   - Test with `.landscape-compact` class
   - Verify safe area handling

4. **PWA Installation Issues**
   - Check manifest.json validity
   - Verify HTTPS requirement
   - Test service worker registration

### Debug Tools
- Chrome DevTools mobile emulation
- Safari Web Inspector for iOS
- Lighthouse PWA audit
- Playwright test reports

## Future Enhancements

### Planned Features
- Voice input for commands
- Gesture controls for game preview
- Offline mode with sync
- Push notifications for build status
- Advanced haptic feedback patterns

### Performance Improvements
- Virtual scrolling for large lists
- Image lazy loading
- Code splitting for mobile bundle
- Service worker caching strategies