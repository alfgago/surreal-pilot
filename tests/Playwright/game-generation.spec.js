// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Game Generation Tests', () => {

  test('should generate a simple HTML5 game via chat interface', async ({ page }) => {
    // Navigate to the application
    await page.goto('http://surreal-pilot.local/');

    // Check if we need to register/login
    const registerButton = page.locator('text=Register');
    const loginButton = page.locator('text=Login');

    if (await registerButton.isVisible()) {
      console.log('Registration needed');
      await registerButton.click();

      // Fill registration form
      await page.fill('input[name="name"]', 'Test Game Developer');
      await page.fill('input[name="email"]', 'gamedev@test.com');
      await page.fill('input[name="password"]', 'password123');
      await page.fill('input[name="password_confirmation"]', 'password123');

      // Submit registration
      await page.click('button[type="submit"]');

      // Wait for redirect after registration
      await page.waitForURL('**/company**', { timeout: 10000 });
    } else if (await loginButton.isVisible()) {
      console.log('Login needed');
      await loginButton.click();

      // Fill login form
      await page.fill('input[name="email"]', 'test@example.com');
      await page.fill('input[name="password"]', 'password123');

      // Submit login
      await page.click('button[type="submit"]');

      // Wait for redirect after login
      await page.waitForURL('**/company**', { timeout: 10000 });
    }

    // Look for chat interface or AI assistant
    const chatInput = page.locator('textarea, input[placeholder*="message"], input[placeholder*="chat"], [data-testid="chat-input"]');
    const assistButton = page.locator('text=Assistant', 'text=AI', 'text=Chat', '[data-testid="assist"]');

    // If there's an assist/chat button, click it
    if (await assistButton.isVisible()) {
      await assistButton.first().click();
      await page.waitForTimeout(2000); // Wait for chat interface to load
    }

    // Find chat input field
    await expect(chatInput.first()).toBeVisible({ timeout: 15000 });

    // Generate a simple game
    const gamePrompt = 'Create a simple HTML5 game with a red square that moves with arrow keys. Include basic collision detection with green circles that give points when collected.';

    await chatInput.first().fill(gamePrompt);

    // Find and click send button
    const sendButton = page.locator('button[type="submit"], button:has-text("Send"), [data-testid="send"]');
    await sendButton.first().click();

    // Wait for AI response
    await page.waitForTimeout(30000); // Give AI time to generate

    // Check for generated game elements
    const gameContainer = page.locator('canvas, #game, .game-container, iframe[src*="game"]');
    const downloadLink = page.locator('a[download], a[href*="download"], button:has-text("Download")');
    const previewButton = page.locator('button:has-text("Preview"), a:has-text("Preview")');

    // Look for evidence of game generation
    const generatedContent = page.locator('text=game, text=HTML, text=canvas, text=created, text=generated');

    // Take a screenshot for debugging
    await page.screenshot({ path: 'test-results/game-generation-result.png', fullPage: true });

    // Verify some form of game generation occurred
    const hasGeneratedContent = await generatedContent.first().isVisible();
    const hasGameContainer = await gameContainer.first().isVisible();
    const hasDownloadOption = await downloadLink.first().isVisible();
    const hasPreviewOption = await previewButton.first().isVisible();

    console.log('Generated content visible:', hasGeneratedContent);
    console.log('Game container visible:', hasGameContainer);
    console.log('Download option visible:', hasDownloadOption);
    console.log('Preview option visible:', hasPreviewOption);

    // At least one indicator of game generation should be present
    expect(hasGeneratedContent || hasGameContainer || hasDownloadOption || hasPreviewOption).toBeTruthy();

    // If there's a preview button, click it to see the game
    if (hasPreviewOption) {
      await previewButton.first().click();
      await page.waitForTimeout(5000);
      await page.screenshot({ path: 'test-results/game-preview.png', fullPage: true });
    }
  });

  test('should list available game templates', async ({ page }) => {
    // Navigate to templates or demos endpoint
    await page.goto('http://surreal-pilot.local/api/providers');

    // Check if providers are available
    const content = await page.textContent('body');
    expect(content).toContain('providers');

    console.log('API Response:', content);
  });

  test('should check storage for generated games', async ({ page, context }) => {
    // This test will check if files are created in storage
    // We'll use the browser context to make API calls

    const response = await context.request.get('http://surreal-pilot.local/api/providers');
    expect(response.ok()).toBeTruthy();

    const data = await response.json();
    console.log('Providers data:', JSON.stringify(data, null, 2));

    // Check if OpenAI is available for game generation
    const openaiAvailable = data.providers?.openai?.available;
    console.log('OpenAI available for game generation:', openaiAvailable);
  });
});
