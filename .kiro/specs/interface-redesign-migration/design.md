# Design Document

## Overview

This design document outlines the architecture and approach for migrating all existing SurrealPilot Laravel Blade views to the new redesign system. The migration will transform the current gray-themed interface to a modern, clean design with mint green accents, improved component architecture, and better user experience patterns while preserving all existing functionality.

## Architecture

### Component Hierarchy

The new design system follows a hierarchical component structure:

```
redesign/resources/views/
├── layouts/
│   ├── app.blade.php (main application layout)
│   ├── auth.blade.php (authentication pages)
│   └── marketing.blade.php (landing pages)
├── components/
│   ├── ui/ (base UI components)
│   │   ├── button.blade.php
│   │   ├── badge.blade.php
│   │   ├── card.blade.php
│   │   └── ...
│   ├── icons/ (SVG icon components)
│   ├── chat/ (chat-specific components)
│   │   ├── composer.blade.php
│   │   ├── thread-list.blade.php
│   │   └── game-preview.blade.php
│   ├── header.blade.php (main header)
│   └── sidebar.blade.php (navigation sidebar)
└── livewire/ (Livewire components)
    ├── surreal-pilot.blade.php (main app)
    ├── auth/
    └── ...
```

### Design System Foundation

#### Color Scheme

-   **Primary**: Dark theme with mint green accents
-   **Background**: `oklch(0.145 0 0)` (dark)
-   **Cards**: `oklch(0.145 0 0)` with subtle borders
-   **Primary Actions**: `oklch(0.985 0 0)` (light)
-   **Text**: High contrast white/light gray on dark backgrounds

#### Typography

-   **Headings**: Space Grotesk font family
-   **Body Text**: DM Sans font family
-   **Code/Monospace**: System monospace fonts

#### Spacing and Layout

-   **Consistent spacing**: Using Tailwind's spacing scale
-   **Border radius**: `0.625rem` (10px) for cards and buttons
-   **Grid system**: CSS Grid and Flexbox for layouts

## Components and Interfaces

### Core UI Components

#### Button Component (`x-ui.button`)

```php
@props([
    'variant' => 'default', // primary, secondary, outline, ghost, destructive
    'size' => 'default',    // sm, lg, icon
    'type' => 'button'
])
```

**Variants:**

-   `primary`: Main action buttons with primary color
-   `secondary`: Secondary actions with muted styling
-   `outline`: Border-only buttons for less prominent actions
-   `ghost`: Minimal buttons for subtle interactions
-   `destructive`: Red-themed buttons for dangerous actions

#### Badge Component (`x-ui.badge`)

```php
@props(['variant' => 'default'])
```

**Variants:**

-   `success`: Green badges for positive states
-   `engine`: Engine type indicators
-   `status`: General status indicators
-   `credits`: Credit balance display
-   `connected`: Connection status

#### Card Component (`x-ui.card`)

```php
@props(['variant' => 'default'])
```

**Variants:**

-   `workspace`: Workspace selection cards
-   `thread`: Chat thread items
-   `chat`: Chat message containers
-   `preview`: Game preview containers

### Layout Components

#### Header Component (`x-header`)

-   Logo and branding
-   Workspace switcher with engine indicators
-   Connection status and credit balance
-   Settings access button

#### Sidebar Component (`x-sidebar`)

-   Navigation tabs (Chat, Preview, Publish, Multiplayer, History)
-   Workspace list with selection state
-   Collapsible sections

### Chat-Specific Components

#### Thread List (`x-chat.thread-list`)

-   Conversation list with search
-   Thread selection and management
-   Responsive scrolling

#### Composer (`x-chat.composer`)

-   Message input with preset options
-   Send button with loading states
-   Token estimation display

#### Game Preview (`x-chat.game-preview`)

-   Live game preview iframe
-   Share and export controls
-   Mobile-responsive design

## Data Models

### View Data Structure

Each migrated view will receive structured data:

```php
// Main application data
$workspaces = [
    [
        'id' => 'workspace-id',
        'name' => 'Workspace Name',
        'engine' => 'playcanvas|unreal',
        'status' => 'Connected|Preview Ready|Publishing',
        'icon' => '🎯', // emoji or icon identifier
    ]
];

$threads = [
    [
        'id' => 1,
        'name' => 'Thread Name',
        'lastMessage' => 'Last message preview',
        'time' => '2m ago',
    ]
];

$patches = [
    [
        'id' => 1,
        'intent' => 'User intent description',
        'result' => 'Success|Failed',
        'time' => '2m ago',
        'canUndo' => true|false,
    ]
];
```

### State Management

#### Alpine.js Integration

```javascript
x-data="{
    credits: 127,
    isConnected: true,
    estimatedCredits: 0.3,
    activeTab: 'chat',
    selectedWorkspace: 'workspace-id'
}"
```

#### Livewire Properties

```php
public $activeTab = 'chat';
public $selectedWorkspace;
public $messageInput = '';
public $searchQuery = '';
```

## Error Handling

### Error Display Patterns

#### Inline Errors

-   Form validation errors using consistent styling
-   API error messages with retry options
-   Connection status indicators

#### Modal Errors

-   Critical errors in overlay modals
-   Confirmation dialogs for destructive actions
-   Loading states with timeout handling

#### Toast Notifications

-   Success confirmations
-   Non-critical error notifications
-   Status updates

### Error Recovery

#### Graceful Degradation

-   Fallback UI states when components fail
-   Progressive enhancement for JavaScript features
-   Offline state handling

#### Retry Mechanisms

-   Automatic retry for failed API calls
-   Manual retry buttons for user-initiated actions
-   Connection restoration handling

## Testing Strategy

### Component Testing

#### Unit Tests

-   Individual component rendering
-   Prop validation and variants
-   Event handling and interactions

#### Integration Tests

-   Component composition and data flow
-   Livewire component interactions
-   Alpine.js state management

### Visual Regression Testing

#### Screenshot Comparisons

-   Before/after migration comparisons
-   Cross-browser compatibility
-   Responsive design validation

#### Accessibility Testing

-   Keyboard navigation
-   Screen reader compatibility
-   Color contrast validation

### User Acceptance Testing

#### Functionality Verification

-   All existing features work as expected
-   New design improves user experience
-   Performance is maintained or improved

#### Cross-Device Testing

-   Desktop browser compatibility
-   Mobile responsive behavior
-   Touch interaction validation

## Migration Strategy

### Phase 1: Foundation Setup

1. Copy redesign CSS and component files
2. Update main layout structure
3. Implement base UI components

### Phase 2: Core Interface Migration

1. Migrate main chat interface
2. Update header and sidebar components
3. Implement workspace switching

### Phase 3: Specialized Views

1. Migrate authentication pages
2. Update settings and profile pages
3. Migrate marketing and landing pages

### Phase 4: Component Refinement

1. Optimize component reusability
2. Implement missing variants
3. Add accessibility improvements

### Phase 5: Testing and Polish

1. Comprehensive testing across all views
2. Performance optimization
3. Final visual polish and bug fixes

## Implementation Considerations

### Backward Compatibility

-   Maintain existing API contracts
-   Preserve URL structures
-   Keep existing JavaScript functionality

### Performance Optimization

-   Minimize CSS bundle size
-   Optimize component rendering
-   Implement lazy loading where appropriate

### Maintenance Strategy

-   Document component usage patterns
-   Create style guide for future development
-   Establish component update procedures
