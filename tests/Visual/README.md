# Visual Testing Suite

This directory contains comprehensive visual tests for the SurrealPilot application using Pest 4 and Laravel Dusk.

## Test Structure

### VisualTestCase.php
Base test case that provides:
- Screenshot capture functionality
- Responsive testing utilities
- React/Inertia page waiting helpers
- Screenshot logging and organization

### Test Files

1. **ComprehensiveAppFlowTest.php**
   - Complete user journey from landing to chat
   - Registration and authentication flow
   - Engine and workspace selection
   - Error handling and edge cases
   - Accessibility testing

2. **ComponentInteractionTest.php**
   - Chat interface interactions
   - Games management interactions
   - Settings and profile interactions
   - Mobile responsive interactions

3. **RouteValidationTest.php**
   - Public route validation
   - Authentication requirements
   - Navigation menu functionality
   - Breadcrumb navigation
   - Error page handling
   - Mobile navigation
   - Route parameter validation

## Running Tests

### Run all visual tests:
```bash
php artisan test tests/Visual/
```

### Run specific test file:
```bash
php artisan test tests/Visual/ComprehensiveAppFlowTest.php
```

### Run with specific browser:
```bash
php artisan dusk --browse
```

## Screenshots

Screenshots are automatically saved to `tests/Visual/screenshots/` with:
- Timestamp prefixes
- Descriptive names
- Responsive breakpoint variations
- Detailed logging in `screenshot_log.md`

## Test Data

Tests use:
- Factory-generated test data
- Fresh database migrations for each test
- Realistic user scenarios
- Multiple device viewports

## Responsive Testing

Each major interface is tested at:
- Mobile: 375x667 (iPhone SE)
- Tablet: 768x1024 (iPad)
- Desktop: 1920x1080 (Full HD)

## Coverage

The visual tests cover:
- ✅ Landing page and public routes
- ✅ Authentication flows
- ✅ Engine selection
- ✅ Workspace management
- ✅ Chat interface
- ✅ Games management
- ✅ Settings and profile
- ✅ Company management
- ✅ Mobile interfaces
- ✅ Error handling
- ✅ Navigation flows
- ✅ Responsive design
- ✅ Accessibility features