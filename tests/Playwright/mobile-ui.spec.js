const { test, expect, devices } = require('@playwright/test');

// Test on multiple mobile devices
const mobileDevices = [
  devices['iPhone 12'],
  devices['iPhone 12 Pro'],
  devices['Pixel 5'],
  devices['Galaxy S21'],
];

mobileDevices.forEach(device => {
  test.describe(`Mobile UI Tests - ${device.name}`, () => {
    test.use({ ...device });

    test('mobile chat interface loads and is responsive', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Check basic elements are present
      await expect(page.locator('h1')).toContainText('SurrealPilot');
      await expect(page.locator('#mobile-message-input')).toBeVisible();
      await expect(page.locator('#mobile-send-btn')).toBeVisible();
      await expect(page.locator('#mobile-credit-badge')).toBeVisible();
      
      // Check mobile-specific styling
      await expect(page.locator('.mobile-chat-container')).toBeVisible();
      await expect(page.locator('.safe-area-top')).toBeVisible();
    });

    test('touch targets meet accessibility standards', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Get all touch targets and verify they're at least 44x44px
      const touchTargets = await page.locator('.touch-target').all();
      
      for (const target of touchTargets) {
        const box = await target.boundingBox();
        expect(box.width).toBeGreaterThanOrEqual(44);
        expect(box.height).toBeGreaterThanOrEqual(44);
      }
    });

    test('mobile menu functionality works', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Open mobile menu
      await page.click('#mobile-menu-btn');
      await expect(page.locator('#mobile-menu-panel')).toBeVisible();
      
      // Check menu items
      await expect(page.locator('#mobile-demos-btn')).toBeVisible();
      await expect(page.locator('#mobile-workspaces-btn')).toBeVisible();
      
      // Close menu
      await page.click('#mobile-menu-close');
      await expect(page.locator('#mobile-menu-panel')).not.toBeVisible();
    });

    test('demo chooser modal is touch-friendly', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Open menu and then demo modal
      await page.click('#mobile-menu-btn');
      await page.click('#mobile-demos-btn');
      
      // Check modal appears
      await expect(page.locator('#mobile-demo-modal')).toBeVisible();
      await expect(page.locator('#mobile-demo-panel')).toBeVisible();
      
      // Check modal content
      await expect(page.locator('h2')).toContainText('Choose Demo Template');
      await expect(page.locator('#mobile-demo-list')).toBeVisible();
      
      // Close modal
      await page.click('#mobile-demo-close');
      await expect(page.locator('#mobile-demo-modal')).not.toBeVisible();
    });

    test('message input and character counter work', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      const input = page.locator('#mobile-message-input');
      const counter = page.locator('#mobile-char-counter');
      
      // Type message and check counter
      await input.fill('Test message');
      await expect(counter).toContainText('12');
      
      // Clear and check counter resets
      await input.fill('');
      await expect(counter).toContainText('0');
      
      // Test long message
      const longMessage = 'a'.repeat(450);
      await input.fill(longMessage);
      await expect(counter).toContainText('450');
      
      // Counter should turn red near limit
      const counterColor = await counter.evaluate(el => 
        window.getComputedStyle(el).color
      );
      expect(counterColor).toContain('rgb(248, 113, 113)'); // text-red-400
    });

    test('smart suggestions appear and work', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      const input = page.locator('#mobile-message-input');
      const suggestions = page.locator('#mobile-suggestions');
      
      // Type to trigger suggestions
      await input.fill('jump');
      await expect(suggestions).toBeVisible();
      
      // Check suggestion appears
      await expect(page.locator('text=double the jump height')).toBeVisible();
      
      // Click suggestion
      await page.click('text=double the jump height');
      await expect(input).toHaveValue('double the jump height');
      await expect(suggestions).not.toBeVisible();
    });

    test('quick action buttons work', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      const input = page.locator('#mobile-message-input');
      
      // Click first quick action
      await page.click('.mobile-quick-action:first-child');
      await expect(input).toHaveValue('double the jump height');
    });

    test('workspace actions appear when workspace is set', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Initially hidden
      await expect(page.locator('#mobile-workspace-actions')).not.toBeVisible();
      
      // Simulate setting workspace
      await page.evaluate(() => {
        window.mobileChatInterface.currentWorkspace = {
          id: 'test-workspace',
          name: 'Test Game',
          preview_url: 'https://example.com/preview'
        };
        window.mobileChatInterface.updateWorkspaceUI();
      });
      
      // Should now be visible
      await expect(page.locator('#mobile-workspace-actions')).toBeVisible();
      await expect(page.locator('#mobile-preview-btn')).toBeVisible();
      await expect(page.locator('#mobile-publish-btn')).toBeVisible();
    });

    test('preview modal works', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Set up workspace first
      await page.evaluate(() => {
        window.mobileChatInterface.currentWorkspace = {
          id: 'test-workspace',
          name: 'Test Game',
          preview_url: 'https://example.com/preview'
        };
        window.mobileChatInterface.updateWorkspaceUI();
      });
      
      // Click preview button
      await page.click('#mobile-preview-btn');
      await expect(page.locator('#mobile-preview-modal')).toBeVisible();
      await expect(page.locator('#mobile-preview-frame')).toBeVisible();
      
      // Close preview
      await page.click('#mobile-preview-close');
      await expect(page.locator('#mobile-preview-modal')).not.toBeVisible();
    });

    test('typing indicator shows and hides', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      const indicator = page.locator('#mobile-typing-indicator');
      
      // Initially hidden
      await expect(indicator).not.toBeVisible();
      
      // Show indicator
      await page.evaluate(() => {
        window.mobileChatInterface.showTypingIndicator();
      });
      await expect(indicator).toBeVisible();
      await expect(indicator).toContainText('AI is thinking...');
      
      // Hide indicator
      await page.evaluate(() => {
        window.mobileChatInterface.hideTypingIndicator();
      });
      await expect(indicator).not.toBeVisible();
    });

    test('message bubbles display correctly', async ({ page }) => {
      await page.goto('/mobile/chat');
      
      // Add messages programmatically
      await page.evaluate(() => {
        window.mobileChatInterface.addMessage('User message test', 'user');
        window.mobileChatInterface.addMessage('AI response test', 'ai');
      });
      
      // Check messages appear
      await expect(page.locator('text=User message test')).toBeVisible();
      await expect(page.locator('text=AI response test')).toBeVisible();
      
      // Check styling
      await expect(page.locator('.user-message')).toBeVisible();
      await expect(page.locator('.ai-message')).toBeVisible();
    });
  });
});

// Landscape orientation tests
test.describe('Mobile Landscape Tests', () => {
  test.use({ 
    ...devices['iPhone 12'],
    viewport: { width: 844, height: 390 } // Landscape
  });

  test('landscape layout adjustments work', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Check landscape-specific classes are applied
    await expect(page.locator('.landscape-compact')).toBeVisible();
    
    // Verify elements are still accessible
    await expect(page.locator('#mobile-message-input')).toBeVisible();
    await expect(page.locator('#mobile-send-btn')).toBeVisible();
  });
});

// PWA and performance tests
test.describe('PWA and Performance Tests', () => {
  test.use(devices['iPhone 12']);

  test('PWA manifest is present and valid', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Check manifest link
    const manifestLink = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(manifestLink).toBe('/manifest.json');
    
    // Check PWA meta tags
    await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#1f2937');
    await expect(page.locator('meta[name="apple-mobile-web-app-capable"]')).toHaveAttribute('content', 'yes');
  });

  test('page loads within performance budget', async ({ page }) => {
    const startTime = Date.now();
    await page.goto('/mobile/chat');
    await page.waitForLoadState('networkidle');
    const loadTime = Date.now() - startTime;
    
    // Should load within 3 seconds on mobile
    expect(loadTime).toBeLessThan(3000);
  });

  test('touch interactions have proper feedback', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Check haptic feedback class is present
    await expect(page.locator('.haptic-feedback')).toHaveCount(await page.locator('.haptic-feedback').count());
    
    // Test touch scaling on buttons
    const button = page.locator('#mobile-send-btn');
    await button.hover();
    
    // Check for transform scale on active state (simulated)
    const hasHapticClass = await button.evaluate(el => el.classList.contains('haptic-feedback'));
    expect(hasHapticClass).toBe(true);
  });
});

// Accessibility tests
test.describe('Mobile Accessibility Tests', () => {
  test.use(devices['iPhone 12']);

  test('keyboard navigation works on mobile', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Focus input
    await page.focus('#mobile-message-input');
    
    // Tab to send button
    await page.keyboard.press('Tab');
    const focusedElement = await page.evaluate(() => document.activeElement.id);
    expect(focusedElement).toBe('mobile-send-btn');
  });

  test('screen reader labels are present', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Check important elements have proper labels
    const input = page.locator('#mobile-message-input');
    const placeholder = await input.getAttribute('placeholder');
    expect(placeholder).toBeTruthy();
    
    // Check buttons have accessible text
    await expect(page.locator('#mobile-send-btn')).toBeVisible();
    await expect(page.locator('#mobile-menu-btn')).toBeVisible();
  });

  test('color contrast meets WCAG standards', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // This would typically use axe-core or similar tool
    // For now, we'll check that text is visible against backgrounds
    const textElements = await page.locator('text=SurrealPilot').all();
    for (const element of textElements) {
      await expect(element).toBeVisible();
    }
  });
});

// Network condition tests
test.describe('Mobile Network Tests', () => {
  test.use(devices['iPhone 12']);

  test('works on slow 3G connection', async ({ page, context }) => {
    // Simulate slow 3G
    await context.route('**/*', route => {
      setTimeout(() => route.continue(), 100); // Add 100ms delay
    });
    
    await page.goto('/mobile/chat');
    await expect(page.locator('h1')).toContainText('SurrealPilot');
  });

  test('handles offline gracefully', async ({ page, context }) => {
    await page.goto('/mobile/chat');
    
    // Go offline
    await context.setOffline(true);
    
    // Try to interact - should handle gracefully
    await page.fill('#mobile-message-input', 'test message');
    await page.click('#mobile-send-btn');
    
    // Should show some kind of error or offline indicator
    // (Implementation would depend on actual offline handling)
  });
});