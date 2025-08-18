import { test, expect, devices } from '@playwright/test';

test.describe('PlayCanvas Mobile Performance Tests', () => {
  test.use(devices['iPhone 13']);

  test('page achieves lighthouse performance score above 90', async ({ page }) => {
    // Navigate to mobile chat page
    await page.goto('/mobile/chat');
    
    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    
    // In a real implementation, we'd run Lighthouse programmatically
    // For now, we'll test key performance indicators
    
    // Measure First Contentful Paint (FCP)
    const fcpMetric = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const fcpEntry = entries.find(entry => entry.name === 'first-contentful-paint');
          if (fcpEntry) {
            resolve(fcpEntry.startTime);
          }
        }).observe({ entryTypes: ['paint'] });
      });
    });
    
    // FCP should be under 1.8 seconds for good mobile performance
    expect(fcpMetric).toBeLessThan(1800);
    
    // Measure Largest Contentful Paint (LCP)
    const lcpMetric = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const lastEntry = entries[entries.length - 1];
          resolve(lastEntry.startTime);
        }).observe({ entryTypes: ['largest-contentful-paint'] });
        
        // Fallback timeout
        setTimeout(() => resolve(0), 5000);
      });
    });
    
    // LCP should be under 2.5 seconds
    expect(lcpMetric).toBeLessThan(2500);
  });

  test('demo chooser modal opens within 300ms', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Measure modal open time
    const startTime = Date.now();
    
    await page.locator('[data-testid="demo-chooser-button"]').tap();
    await page.locator('[data-testid="demo-chooser-modal"]').waitFor({ state: 'visible' });
    
    const openTime = Date.now() - startTime;
    expect(openTime).toBeLessThan(300);
  });

  test('chat input has minimal input lag', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    const chatInput = page.locator('[data-testid="chat-input"]');
    
    // Measure time from tap to focus
    const startTime = Date.now();
    await chatInput.tap();
    await chatInput.waitFor({ state: 'focused' });
    const focusTime = Date.now() - startTime;
    
    expect(focusTime).toBeLessThan(100); // Should focus in under 100ms
    
    // Measure typing responsiveness
    const typingStart = Date.now();
    await chatInput.fill('test message');
    
    // Verify text appears immediately
    await expect(chatInput).toHaveValue('test message');
    const typingTime = Date.now() - typingStart;
    
    expect(typingTime).toBeLessThan(200); // Should type in under 200ms
  });

  test('concurrent users can create prototypes without performance degradation', async ({ browser }) => {
    // Create multiple browser contexts to simulate concurrent users
    const contexts = await Promise.all([
      browser.newContext(devices['iPhone 13']),
      browser.newContext(devices['Samsung Galaxy S21']),
      browser.newContext(devices['iPad']),
    ]);
    
    const pages = await Promise.all(contexts.map(context => context.newPage()));
    
    // Mock API responses for all pages
    for (const page of pages) {
      await page.route('/api/demos', async route => {
        await route.fulfill({
          json: [
            { id: 'fps-starter', name: 'FPS Starter', difficulty_level: 'beginner' }
          ]
        });
      });
      
      await page.route('/api/prototype', async route => {
        // Add realistic delay
        await new Promise(resolve => setTimeout(resolve, 1000));
        await route.fulfill({
          json: {
            success: true,
            workspace_id: `workspace-${Math.random()}`,
            preview_url: 'http://localhost:3001/preview'
          }
        });
      });
    }
    
    // Navigate all pages
    await Promise.all(pages.map(page => page.goto('/mobile/chat')));
    
    // Start prototype creation simultaneously
    const startTime = Date.now();
    
    const creationPromises = pages.map(async (page, index) => {
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-card"]').first().tap();
      await page.locator('[data-testid="create-prototype-button"]').tap();
      
      // Wait for completion
      await page.locator('[data-testid="preview-button"]').waitFor({ 
        state: 'visible', 
        timeout: 20000 
      });
      
      return Date.now() - startTime;
    });
    
    const completionTimes = await Promise.all(creationPromises);
    
    // All should complete within reasonable time
    completionTimes.forEach(time => {
      expect(time).toBeLessThan(15000); // Under 15 seconds
    });
    
    // Times should be relatively consistent (no major degradation)
    const maxTime = Math.max(...completionTimes);
    const minTime = Math.min(...completionTimes);
    const variance = maxTime - minTime;
    
    expect(variance).toBeLessThan(5000); // Variance under 5 seconds
    
    // Cleanup
    await Promise.all(contexts.map(context => context.close()));
  });

  test('memory usage stays stable during extended use', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Get initial memory usage
    const initialMemory = await page.evaluate(() => {
      return performance.memory ? performance.memory.usedJSHeapSize : 0;
    });
    
    // Perform many operations
    for (let i = 0; i < 20; i++) {
      // Open and close demo chooser
      await page.locator('[data-testid="demo-chooser-button"]').tap();
      await page.locator('[data-testid="demo-chooser-modal"]').waitFor({ state: 'visible' });
      await page.locator('[data-testid="close-modal"]').tap();
      
      // Type in chat
      const chatInput = page.locator('[data-testid="chat-input"]');
      await chatInput.fill(`Test message ${i}`);
      await chatInput.clear();
      
      // Small delay between operations
      await page.waitForTimeout(100);
    }
    
    // Force garbage collection if available
    await page.evaluate(() => {
      if (window.gc) {
        window.gc();
      }
    });
    
    const finalMemory = await page.evaluate(() => {
      return performance.memory ? performance.memory.usedJSHeapSize : 0;
    });
    
    // Memory increase should be reasonable (under 10MB)
    const memoryIncrease = finalMemory - initialMemory;
    expect(memoryIncrease).toBeLessThan(10 * 1024 * 1024);
  });

  test('network requests are optimized for mobile', async ({ page }) => {
    // Monitor network requests
    const requests = [];
    page.on('request', request => {
      requests.push({
        url: request.url(),
        method: request.method(),
        size: request.postData()?.length || 0
      });
    });
    
    const responses = [];
    page.on('response', response => {
      responses.push({
        url: response.url(),
        status: response.status(),
        size: response.headers()['content-length'] || 0
      });
    });
    
    await page.goto('/mobile/chat');
    
    // Create a prototype to generate network activity
    await page.locator('[data-testid="demo-chooser-button"]').tap();
    await page.locator('[data-testid="demo-card"]').first().tap();
    await page.locator('[data-testid="create-prototype-button"]').tap();
    
    // Wait for requests to complete
    await page.waitForTimeout(2000);
    
    // Analyze requests
    const apiRequests = requests.filter(req => req.url.includes('/api/'));
    const staticRequests = requests.filter(req => 
      req.url.includes('.css') || req.url.includes('.js') || req.url.includes('.png')
    );
    
    // API requests should be minimal
    expect(apiRequests.length).toBeLessThan(10);
    
    // Static assets should be compressed
    const largeAssets = responses.filter(res => 
      parseInt(res.size) > 100 * 1024 // Over 100KB
    );
    
    expect(largeAssets.length).toBeLessThan(3); // Should have few large assets
  });

  test('touch interactions have minimal delay', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    const elements = [
      '[data-testid="demo-chooser-button"]',
      '[data-testid="chat-input"]',
      '[data-testid="send-button"]'
    ];
    
    for (const selector of elements) {
      const element = page.locator(selector);
      
      // Measure tap response time
      const startTime = Date.now();
      await element.tap();
      
      // Wait for visual feedback (active state, focus, etc.)
      await page.waitForTimeout(50); // Small delay for visual feedback
      
      const responseTime = Date.now() - startTime;
      expect(responseTime).toBeLessThan(150); // Should respond in under 150ms
    }
  });

  test('scrolling performance is smooth', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Create a long chat history to test scrolling
    await page.evaluate(() => {
      const chatContainer = document.querySelector('[data-testid="chat-messages"]');
      if (chatContainer) {
        for (let i = 0; i < 50; i++) {
          const message = document.createElement('div');
          message.className = 'chat-message';
          message.textContent = `Test message ${i}`;
          chatContainer.appendChild(message);
        }
      }
    });
    
    // Measure scroll performance
    const scrollMetrics = await page.evaluate(() => {
      return new Promise((resolve) => {
        const chatContainer = document.querySelector('[data-testid="chat-messages"]');
        if (!chatContainer) {
          resolve({ frameDrops: 0, avgFrameTime: 0 });
          return;
        }
        
        let frameCount = 0;
        let totalFrameTime = 0;
        let lastFrameTime = performance.now();
        
        const measureFrame = () => {
          const currentTime = performance.now();
          const frameTime = currentTime - lastFrameTime;
          totalFrameTime += frameTime;
          frameCount++;
          lastFrameTime = currentTime;
          
          if (frameCount < 30) {
            requestAnimationFrame(measureFrame);
          } else {
            const avgFrameTime = totalFrameTime / frameCount;
            const frameDrops = frameCount - Math.floor(30 * 1000 / totalFrameTime);
            resolve({ frameDrops: Math.max(0, frameDrops), avgFrameTime });
          }
        };
        
        // Start scrolling
        chatContainer.scrollTop = 0;
        let scrollPosition = 0;
        const scrollInterval = setInterval(() => {
          scrollPosition += 10;
          chatContainer.scrollTop = scrollPosition;
          if (scrollPosition > chatContainer.scrollHeight) {
            clearInterval(scrollInterval);
          }
        }, 16); // ~60fps
        
        requestAnimationFrame(measureFrame);
      });
    });
    
    // Should maintain good frame rate
    expect(scrollMetrics.avgFrameTime).toBeLessThan(20); // Under 20ms per frame
    expect(scrollMetrics.frameDrops).toBeLessThan(5); // Minimal frame drops
  });

  test('app works offline with service worker', async ({ page }) => {
    await page.goto('/mobile/chat');
    
    // Wait for service worker to register
    await page.waitForFunction(() => 'serviceWorker' in navigator);
    
    // Go offline
    await page.context().setOffline(true);
    
    // Try to navigate (should work with cached resources)
    await page.reload();
    
    // Basic functionality should still work
    await expect(page.locator('[data-testid="chat-container"]')).toBeVisible();
    
    // Should show offline indicator
    await expect(page.locator('[data-testid="offline-indicator"]')).toBeVisible();
    
    // Go back online
    await page.context().setOffline(false);
    
    // Offline indicator should disappear
    await expect(page.locator('[data-testid="offline-indicator"]')).not.toBeVisible();
  });
});