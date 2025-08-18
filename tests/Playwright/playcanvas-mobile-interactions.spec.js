import { test, expect, devices } from '@playwright/test';

// Test on multiple mobile devices
const mobileDevices = [
  devices['iPhone 13'],
  devices['iPhone 13 Pro'],
  devices['Samsung Galaxy S21'],
  devices['iPad'],
];

mobileDevices.forEach(device => {
  test.describe(`PlayCanvas Mobile Interactions - ${device.name}`, () => {
    test.use({ ...device });

    test.beforeEach(async ({ page }) => {
      // Mock API responses
      await page.route('/api/demos', async route => {
        await route.fulfill({
          json: [
            {
              id: 'fps-starter',
              name: 'FPS Starter',
              description: 'First-person shooter template',
              preview_image: '/images/fps-preview.jpg',
              difficulty_level: 'beginner'
            },
            {
              id: 'platformer-2d',
              name: '2D Platformer',
              description: 'Side-scrolling platformer game',
              preview_image: '/images/platformer-preview.jpg',
              difficulty_level: 'intermediate'
            }
          ]
        });
      });

      await page.route('/api/prototype', async route => {
        await route.fulfill({
          json: {
            success: true,
            workspace_id: 'test-workspace-123',
            preview_url: 'http://localhost:3001/preview',
            credits_remaining: 95.5
          }
        });
      });

      await page.goto('/mobile/chat');
    });

    test('demo chooser modal opens with touch-friendly interface', async ({ page }) => {
      // Tap the demo chooser button
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      
      // Verify modal opens
      await expect(page.locator('[data-testid="demo-chooser-modal"]')).toBeVisible();
      
      // Check that demo cards are large enough for touch
      const demoCards = page.locator('[data-testid="demo-card"]');
      await expect(demoCards).toHaveCount(2);
      
      // Verify minimum touch target size (44px recommended)
      const firstCard = demoCards.first();
      const boundingBox = await firstCard.boundingBox();
      expect(boundingBox.height).toBeGreaterThanOrEqual(44);
      expect(boundingBox.width).toBeGreaterThanOrEqual(44);
      
      // Test tap interaction
      await firstCard.tap();
      
      // Verify selection feedback
      await expect(firstCard).toHaveClass(/selected|active/);
    });

    test('can create prototype with touch interactions', async ({ page }) => {
      // Open demo chooser
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      
      // Select FPS starter template
      await page.locator('[data-testid="demo-card"][data-demo-id="fps-starter"]').tap();
      
      // Tap create prototype button
      await page.locator('[data-testid="create-prototype-button"]').tap();
      
      // Verify loading state
      await expect(page.locator('[data-testid="loading-spinner"]')).toBeVisible();
      
      // Wait for prototype creation
      await expect(page.locator('[data-testid="preview-button"]')).toBeVisible({ timeout: 20000 });
      
      // Verify preview button is touch-friendly
      const previewButton = page.locator('[data-testid="preview-button"]');
      const buttonBox = await previewButton.boundingBox();
      expect(buttonBox.height).toBeGreaterThanOrEqual(44);
      
      // Test preview button tap
      await previewButton.tap();
      
      // Verify preview opens (could be new tab or modal)
      await expect(page.locator('[data-testid="game-preview"]')).toBeVisible();
    });

    test('chat interface is optimized for mobile typing', async ({ page }) => {
      // Focus on chat input
      const chatInput = page.locator('[data-testid="chat-input"]');
      await chatInput.tap();
      
      // Verify input is focused and keyboard appears
      await expect(chatInput).toBeFocused();
      
      // Type a game modification request
      await chatInput.fill('Make the player jump higher');
      
      // Test send button (should be large enough for thumb)
      const sendButton = page.locator('[data-testid="send-button"]');
      const sendButtonBox = await sendButton.boundingBox();
      expect(sendButtonBox.height).toBeGreaterThanOrEqual(44);
      expect(sendButtonBox.width).toBeGreaterThanOrEqual(44);
      
      await sendButton.tap();
      
      // Verify message was sent
      await expect(page.locator('[data-testid="chat-message"]').last()).toContainText('Make the player jump higher');
    });

    test('auto-complete suggestions work with touch', async ({ page }) => {
      const chatInput = page.locator('[data-testid="chat-input"]');
      await chatInput.tap();
      
      // Type partial command to trigger suggestions
      await chatInput.fill('add enemy');
      
      // Wait for suggestions to appear
      await expect(page.locator('[data-testid="suggestion-list"]')).toBeVisible();
      
      // Verify suggestions are touch-friendly
      const suggestions = page.locator('[data-testid="suggestion-item"]');
      const firstSuggestion = suggestions.first();
      const suggestionBox = await firstSuggestion.boundingBox();
      expect(suggestionBox.height).toBeGreaterThanOrEqual(44);
      
      // Tap on suggestion
      await firstSuggestion.tap();
      
      // Verify suggestion was applied
      await expect(chatInput).toHaveValue(/add enemy/);
    });

    test('publish command is accessible in mobile toolbar', async ({ page }) => {
      // Create a workspace first
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      await expect(page.locator('[data-testid="preview-button"]')).toBeVisible({ timeout: 20000 });
      
      // Look for publish button in mobile toolbar
      const publishButton = page.locator('[data-testid="publish-button"]');
      await expect(publishButton).toBeVisible();
      
      // Verify it's positioned for thumb access
      const publishBox = await publishButton.boundingBox();
      expect(publishBox.height).toBeGreaterThanOrEqual(44);
      
      // Test publish action
      await publishButton.tap();
      
      // Verify publish modal or confirmation
      await expect(page.locator('[data-testid="publish-modal"]')).toBeVisible();
    });

    test('interface works in both portrait and landscape', async ({ page }) => {
      // Test in portrait (default)
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await expect(page.locator('[data-testid="demo-chooser-modal"]')).toBeVisible();
      
      // Close modal
      await page.locator('[data-testid="close-modal"]').tap();
      
      // Rotate to landscape
      await page.setViewportSize({ width: 812, height: 375 }); // iPhone landscape
      
      // Test demo chooser in landscape
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await expect(page.locator('[data-testid="demo-chooser-modal"]')).toBeVisible();
      
      // Verify layout adapts to landscape
      const modal = page.locator('[data-testid="demo-chooser-modal"]');
      const modalBox = await modal.boundingBox();
      expect(modalBox.width).toBeGreaterThan(modalBox.height); // Should be wider than tall
    });

    test('touch gestures work for game preview', async ({ page }) => {
      // Create prototype and open preview
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      await expect(page.locator('[data-testid="preview-button"]')).toBeVisible({ timeout: 20000 });
      await page.locator('[data-testid="preview-button"]').tap();
      
      const gameCanvas = page.locator('[data-testid="game-canvas"]');
      await expect(gameCanvas).toBeVisible();
      
      // Test touch interactions on game canvas
      await gameCanvas.tap(); // Single tap
      
      // Test touch and hold
      await gameCanvas.hover();
      await page.mouse.down();
      await page.waitForTimeout(500); // Hold for 500ms
      await page.mouse.up();
      
      // Test swipe gesture (if applicable)
      const canvasBox = await gameCanvas.boundingBox();
      await page.mouse.move(canvasBox.x + 50, canvasBox.y + 50);
      await page.mouse.down();
      await page.mouse.move(canvasBox.x + 150, canvasBox.y + 50);
      await page.mouse.up();
    });

    test('error states are mobile-friendly', async ({ page }) => {
      // Mock error response
      await page.route('/api/prototype', async route => {
        await route.fulfill({
          status: 500,
          json: {
            success: false,
            error: 'Template repository not found'
          }
        });
      });
      
      // Try to create prototype
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      
      // Verify error message is displayed properly on mobile
      const errorMessage = page.locator('[data-testid="error-message"]');
      await expect(errorMessage).toBeVisible();
      await expect(errorMessage).toContainText('Template repository not found');
      
      // Verify error message is readable on mobile
      const errorBox = await errorMessage.boundingBox();
      expect(errorBox.width).toBeLessThanOrEqual(page.viewportSize().width - 40); // Account for padding
      
      // Test retry button
      const retryButton = page.locator('[data-testid="retry-button"]');
      await expect(retryButton).toBeVisible();
      const retryBox = await retryButton.boundingBox();
      expect(retryBox.height).toBeGreaterThanOrEqual(44);
      
      await retryButton.tap();
    });

    test('loading states provide good mobile UX', async ({ page }) => {
      // Mock slow response to test loading states
      await page.route('/api/prototype', async route => {
        await new Promise(resolve => setTimeout(resolve, 2000)); // 2 second delay
        await route.fulfill({
          json: {
            success: true,
            workspace_id: 'test-workspace',
            preview_url: 'http://localhost:3001/preview'
          }
        });
      });
      
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      
      // Verify loading spinner is visible and appropriately sized
      const loadingSpinner = page.locator('[data-testid="loading-spinner"]');
      await expect(loadingSpinner).toBeVisible();
      
      const spinnerBox = await loadingSpinner.boundingBox();
      expect(spinnerBox.width).toBeGreaterThanOrEqual(32);
      expect(spinnerBox.height).toBeGreaterThanOrEqual(32);
      
      // Verify loading text is readable
      const loadingText = page.locator('[data-testid="loading-text"]');
      await expect(loadingText).toBeVisible();
      await expect(loadingText).toContainText(/creating|loading|building/i);
      
      // Wait for completion
      await expect(page.locator('[data-testid="preview-button"]')).toBeVisible({ timeout: 5000 });
    });

    test('credits display updates in real-time on mobile', async ({ page }) => {
      // Verify initial credits display
      const creditsDisplay = page.locator('[data-testid="credits-display"]');
      await expect(creditsDisplay).toBeVisible();
      
      // Create prototype (should deduct credits)
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      
      // Wait for prototype creation
      await expect(page.locator('[data-testid="preview-button"]')).toBeVisible({ timeout: 20000 });
      
      // Verify credits were updated
      await expect(creditsDisplay).toContainText('95.5'); // From mocked response
      
      // Verify credits display is readable on mobile
      const creditsBox = await creditsDisplay.boundingBox();
      expect(creditsBox.height).toBeGreaterThanOrEqual(24); // Minimum readable size
    });
  });
});

// Performance tests for mobile
test.describe('PlayCanvas Mobile Performance', () => {
  test.use(devices['iPhone 13']);

  test('page loads quickly on mobile', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/mobile/chat');
    
    // Wait for main content to be visible
    await expect(page.locator('[data-testid="chat-container"]')).toBeVisible();
    
    const loadTime = Date.now() - startTime;
    expect(loadTime).toBeLessThan(3000); // Should load in under 3 seconds
  });

  test('demo chooser modal opens quickly', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    const startTime = Date.now();
    await page.locator('[data-testid="demo-chooser-button"]').tap();
    await expect(page.locator('[data-testid="demo-chooser-modal"]')).toBeVisible();
    
    const openTime = Date.now() - startTime;
    expect(openTime).toBeLessThan(500); // Should open in under 500ms
  });

  test('chat input responds immediately to touch', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    const chatInput = page.locator('[data-testid="chat-input"]');
    
    const startTime = Date.now();
    await chatInput.tap();
    await expect(chatInput).toBeFocused();
    
    const focusTime = Date.now() - startTime;
    expect(focusTime).toBeLessThan(200); // Should focus in under 200ms
  });
});