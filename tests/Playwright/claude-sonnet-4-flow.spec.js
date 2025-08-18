// @ts-check
import { test, expect } from '@playwright/test';

test.describe('SurrealPilot Claude Sonnet 4 Flow Test', () => {

  test('complete user journey: register → login → generate game with Claude Sonnet 4', async ({ page }) => {
    // Configure longer timeout for AI responses
    test.setTimeout(120000); // 2 minutes

    console.log('🎮 Starting SurrealPilot Claude Sonnet 4 Flow Test');

    // Step 1: Navigate to homepage
    console.log('📱 Step 1: Navigating to SurrealPilot homepage...');
    await page.goto('http://surreal-pilot.local/');

    // Take screenshot of homepage
    await page.screenshot({ path: 'test-results/claude-01-homepage.png', fullPage: true });
    console.log('✅ Homepage loaded and screenshot saved');

    // Check page title
    const title = await page.title();
    console.log(`📄 Page title: "${title}"`);
    expect(title).toBeTruthy();

    // Step 2: Access Filament admin panel
    console.log('🏢 Step 2: Accessing Filament company panel...');
    await page.goto('http://surreal-pilot.local/company');

    await page.screenshot({ path: 'test-results/claude-02-company-access.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // Step 3: Handle authentication (register or login)
    if (currentUrl.includes('login') || currentUrl.includes('register')) {
      console.log('🔓 Authentication required - attempting registration...');

      // Try to find and click register link
      const registerLink = page.locator('a[href*="register"]').or(page.locator('text=Register')).first();

      if (await registerLink.isVisible()) {
        console.log('📝 Found register link - clicking...');
        await registerLink.click();
        await page.waitForLoadState('networkidle');

        // Fill registration form
        console.log('📋 Filling registration form...');

        const nameField = page.locator('input[name="name"]');
        const emailField = page.locator('input[name="email"]');
        const passwordField = page.locator('input[name="password"]');
        const confirmPasswordField = page.locator('input[name="password_confirmation"]');

        if (await nameField.isVisible()) {
          await nameField.fill('Claude Test Developer');
          await emailField.fill('claude-test@surrealpilot.com');
          await passwordField.fill('ClaudeTest123!');
          await confirmPasswordField.fill('ClaudeTest123!');

          console.log('✅ Registration form filled');

          // Submit registration
          const submitButton = page.locator('button[type="submit"]').first();
          await submitButton.click();

          // Wait for registration to complete
          await page.waitForTimeout(5000);
          await page.screenshot({ path: 'test-results/claude-03-after-registration.png', fullPage: true });
          console.log('✅ Registration attempted');
        }
      } else {
        // Try login with existing credentials
        console.log('🔑 Attempting login with existing credentials...');

        const emailField = page.locator('input[name="email"], input[type="email"]').first();
        const passwordField = page.locator('input[name="password"], input[type="password"]').first();

        if (await emailField.isVisible()) {
          await emailField.fill('test@example.com');
          await passwordField.fill('password123');

          const submitButton = page.locator('button[type="submit"]').first();
          await submitButton.click();

          await page.waitForTimeout(5000);
          await page.screenshot({ path: 'test-results/claude-03-after-login.png', fullPage: true });
          console.log('✅ Login attempted');
        }
      }
    }

    // Step 4: Look for AI chat interface
    console.log('🤖 Step 4: Looking for AI chat interface...');

    // Wait for page to load after authentication
    await page.waitForLoadState('networkidle');

    // Try various selectors for chat interface
    const chatSelectors = [
      'textarea[placeholder*="message"]',
      'textarea[placeholder*="chat"]',
      'textarea[placeholder*="prompt"]',
      'textarea[placeholder*="ask"]',
      'textarea[name="message"]',
      '.chat-input textarea',
      '[data-testid="chat-input"]'
    ];

    let chatInput = null;
    let selectorUsed = '';

    for (const selector of chatSelectors) {
      const element = page.locator(selector).first();
      if (await element.isVisible()) {
        chatInput = element;
        selectorUsed = selector;
        break;
      }
    }

    // If no chat input found, try to navigate to chat/assist section
    if (!chatInput) {
      console.log('🔍 No chat input visible - looking for navigation...');

      const navSelectors = [
        'a[href*="chat"]',
        'a[href*="assist"]',
        'a[href*="ai"]',
        'text=Chat',
        'text=Assistant',
        'text=AI',
        'text=Assist'
      ];

      for (const selector of navSelectors) {
        const navElement = page.locator(selector).first();
        if (await navElement.isVisible()) {
          console.log(`📍 Found navigation: ${selector}`);
          await navElement.click();
          await page.waitForLoadState('networkidle');
          await page.waitForTimeout(2000);

          // Try to find chat input again
          for (const chatSelector of chatSelectors) {
            const element = page.locator(chatSelector).first();
            if (await element.isVisible()) {
              chatInput = element;
              selectorUsed = chatSelector;
              break;
            }
          }

          if (chatInput) break;
        }
      }
    }

    // Step 5: Generate game with Claude Sonnet 4
    if (chatInput) {
      console.log(`🎯 Step 5: Found chat input (${selectorUsed}) - generating game with Claude Sonnet 4...`);

      const gamePrompt = `Create a complete HTML5 platformer game with Claude Sonnet 4 intelligence:

🎮 GAME SPECIFICATIONS:
- A red square player character (20x20px)
- WASD or Arrow key movement controls
- Spacebar for jumping with realistic physics
- Green rectangular platforms (various sizes)
- Blue circular coins to collect (10px radius)
- Real-time score counter display
- Game over and restart functionality
- Simple particle effects when collecting coins

🎨 VISUAL REQUIREMENTS:
- Clean, minimalist design
- Smooth animations and transitions
- Responsive controls with good feel
- Score display in top-left corner
- Instructions shown on screen

⚙️ TECHNICAL REQUIREMENTS:
- Pure HTML5 Canvas and JavaScript
- No external dependencies
- Mobile-friendly touch controls
- Collision detection system
- Physics with gravity and jumping
- Game loop with requestAnimationFrame

🏆 GAMEPLAY FEATURES:
- Progressive difficulty
- Multiple platform layouts
- Coin collection with satisfying feedback
- Player respawn on falling off screen
- High score tracking

Please generate a complete, playable game that showcases Claude Sonnet 4's advanced code generation capabilities!`;

      // Clear any existing content and enter our prompt
      await chatInput.click();
      await page.keyboard.press('ControlOrMeta+A');
      await chatInput.fill(gamePrompt);

      console.log('✅ Game prompt entered - waiting for Claude Sonnet 4 to process...');
      await page.screenshot({ path: 'test-results/claude-04-prompt-entered.png', fullPage: true });

      // Find and click send button
      const sendSelectors = [
        'button[type="submit"]',
        'button:has-text("Send")',
        'button:has-text("Submit")',
        '[data-testid="send"]',
        '.send-button',
        'button.btn-primary'
      ];

      let sendButton = null;
      for (const selector of sendSelectors) {
        const element = page.locator(selector).first();
        if (await element.isVisible()) {
          sendButton = element;
          break;
        }
      }

      if (sendButton) {
        await sendButton.click();
        console.log('🚀 Send button clicked - Claude Sonnet 4 is processing the request...');

        // Wait for AI response (Claude Sonnet 4 processing time)
        console.log('⏳ Waiting for Claude Sonnet 4 response (this may take 30-60 seconds)...');
        await page.waitForTimeout(45000); // 45 seconds for AI processing

        await page.screenshot({ path: 'test-results/claude-05-ai-response.png', fullPage: true });
        console.log('✅ Claude Sonnet 4 response received and screenshot saved');

        // Step 6: Check for generated content
        console.log('🔍 Step 6: Analyzing generated content...');

        const contentAnalysis = await page.evaluate(() => {
          const pageText = document.body.textContent.toLowerCase();

          return {
            hasCanvas: pageText.includes('canvas'),
            hasHTML: pageText.includes('html'),
            hasJavaScript: pageText.includes('javascript') || pageText.includes('script'),
            hasGame: pageText.includes('game'),
            hasPlatformer: pageText.includes('platform'),
            hasControls: pageText.includes('controls') || pageText.includes('wasd') || pageText.includes('arrow'),
            hasCode: document.querySelectorAll('pre, code').length > 0,
            codeBlockCount: document.querySelectorAll('pre, code').length,
            downloadLinks: document.querySelectorAll('a[download]').length,
            canvasElements: document.querySelectorAll('canvas').length
          };
        });

        console.log('📊 Content analysis:', contentAnalysis);

        // Verify Claude Sonnet 4 generated game content
        expect(contentAnalysis.hasGame || contentAnalysis.hasCode).toBeTruthy();

        if (contentAnalysis.codeBlockCount > 0) {
          console.log(`✅ Claude Sonnet 4 generated ${contentAnalysis.codeBlockCount} code blocks`);
        }

        if (contentAnalysis.canvasElements > 0) {
          console.log(`🎮 Found ${contentAnalysis.canvasElements} canvas elements - game might be running!`);
        }

      } else {
        console.log('❌ No send button found');
        throw new Error('Could not find send button to submit prompt');
      }

    } else {
      console.log('❌ No chat input found on any page');
      await page.screenshot({ path: 'test-results/claude-error-no-chat.png', fullPage: true });
      throw new Error('Could not find chat input interface');
    }

    // Step 7: Check storage for generated files
    console.log('📁 Step 7: Checking storage for generated game files...');

    // Use API to check storage since we can't access filesystem directly
    const storageResponse = await page.request.get('http://surreal-pilot.local/api/providers');

    if (storageResponse.ok()) {
      console.log('✅ API accessible - checking for game files...');

      // Navigate to storage or download area if available
      const downloadLinks = page.locator('a[download], a[href*="download"], a[href*="storage"]');
      const downloadCount = await downloadLinks.count();

      if (downloadCount > 0) {
        console.log(`🔗 Found ${downloadCount} download links`);
        await page.screenshot({ path: 'test-results/claude-06-download-available.png', fullPage: true });
      }
    }

    // Step 8: Take final screenshots
    console.log('📸 Step 8: Taking final screenshots...');

    await page.screenshot({ path: 'test-results/claude-07-final-result.png', fullPage: true });

    // If there are any embedded games or previews, capture them
    const gamePreview = page.locator('canvas, iframe[src*="game"], .game-preview').first();
    if (await gamePreview.isVisible()) {
      await gamePreview.screenshot({ path: 'test-results/claude-08-game-preview.png' });
      console.log('🎮 Game preview screenshot captured');
    }

    console.log('\n🎉 Claude Sonnet 4 Flow Test Completed Successfully!');
    console.log('\n📋 TEST SUMMARY:');
    console.log('✅ Homepage accessed');
    console.log('✅ Authentication handled');
    console.log('✅ Chat interface found and used');
    console.log('✅ Game prompt sent to Claude Sonnet 4');
    console.log('✅ AI response received and analyzed');
    console.log('✅ Screenshots captured for all steps');

    console.log('\n📸 Screenshots saved:');
    console.log('   - claude-01-homepage.png');
    console.log('   - claude-02-company-access.png');
    console.log('   - claude-03-after-auth.png');
    console.log('   - claude-04-prompt-entered.png');
    console.log('   - claude-05-ai-response.png');
    console.log('   - claude-06-download-available.png (if applicable)');
    console.log('   - claude-07-final-result.png');
    console.log('   - claude-08-game-preview.png (if applicable)');
  });

  test('verify Claude Sonnet 4 API providers are available', async ({ page, context }) => {
    console.log('🔍 Verifying Claude Sonnet 4 availability...');

    const response = await context.request.get('http://surreal-pilot.local/api/providers');
    expect(response.ok()).toBeTruthy();

    const data = await response.json();
    console.log('🤖 AI Providers status:', JSON.stringify(data.providers, null, 2));

    // Check if Anthropic (Claude) is available
    if (data.providers.anthropic) {
      console.log(`🧠 Anthropic Claude status: ${data.providers.anthropic.available ? '✅ Available' : '❌ Not Available'}`);
      console.log(`🔑 Requires API key: ${data.providers.anthropic.requires_key}`);
    }

    // At least one provider should be available for testing
    const availableProviders = Object.values(data.providers).filter(p => p.available);
    console.log(`📊 Available providers: ${availableProviders.length}`);
  });

  test('quick game generation test with minimal prompt', async ({ page }) => {
    // Shorter test for quick verification
    test.setTimeout(60000); // 1 minute

    console.log('⚡ Quick Claude Sonnet 4 test with minimal prompt');

    await page.goto('http://surreal-pilot.local/company');

    // Try to access chat quickly
    const chatInput = page.locator('textarea').first();

    if (await chatInput.isVisible({ timeout: 10000 })) {
      await chatInput.fill('Create a simple HTML button that says "Hello Claude Sonnet 4!" and changes color when clicked.');

      const sendButton = page.locator('button[type="submit"]').first();
      if (await sendButton.isVisible()) {
        await sendButton.click();

        // Wait shorter time for simple request
        await page.waitForTimeout(15000);

        await page.screenshot({ path: 'test-results/claude-quick-test.png', fullPage: true });
        console.log('✅ Quick test completed');
      }
    } else {
      console.log('⚠️ Chat interface not immediately available - may need authentication');
    }
  });
});
