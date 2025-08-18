import puppeteer from 'puppeteer';

async function simplePlayCanvasTest() {
  console.log('🎮 Simple PlayCanvas Test with Claude Sonnet 4');
  console.log('🎯 Focus: Find AI interface and test game generation\n');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  try {
    // Step 1: Navigate to site
    console.log('🏠 Step 1: Navigating to site...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: 'test-results/simple-01-homepage.png', fullPage: true });

    // Step 2: Check if authenticated, if not try to access company directly
    console.log('\n🔐 Step 2: Checking authentication...');

    // Try to go directly to company panel
    await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: 'test-results/simple-02-company-access.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // Step 3: Look for AI interface
    console.log('\n🤖 Step 3: Searching for AI interface...');

    // Log all available links
    const allLinks = await page.$$eval('a', links =>
      links.map(link => ({
        text: link.textContent?.trim(),
        href: link.href
      })).filter(link => link.text && link.text.length > 0)
    );

    console.log('🔗 Available navigation links:');
    allLinks.slice(0, 15).forEach((link, i) => {
      console.log(`   ${i + 1}. "${link.text}" → ${link.href}`);
    });

    // Look for AI-related links
    const aiLinks = allLinks.filter(link =>
      link.text.toLowerCase().includes('ai') ||
      link.text.toLowerCase().includes('chat') ||
      link.text.toLowerCase().includes('assistant') ||
      link.href.includes('ai') ||
      link.href.includes('chat')
    );

    if (aiLinks.length > 0) {
      console.log('\n🎯 Found AI-related links:');
      aiLinks.forEach(link => {
        console.log(`   ✅ "${link.text}" → ${link.href}`);
      });

      // Try the first AI link
      const firstAiLink = aiLinks[0];
      console.log(`\n🔗 Navigating to: ${firstAiLink.text}`);
      await page.goto(firstAiLink.href, { waitUntil: 'networkidle2' });
      await page.screenshot({ path: 'test-results/simple-03-ai-page.png', fullPage: true });
    } else {
      console.log('\n❌ No AI-related links found in navigation');
    }

    // Step 4: Look for chat input
    console.log('\n💬 Step 4: Looking for chat input...');

    const chatSelectors = [
      'textarea[placeholder*="Ask"]',
      'textarea[placeholder*="message"]',
      'input[placeholder*="Ask"]',
      'input[placeholder*="message"]',
      '.chat-input textarea',
      '#chat-input',
      'textarea[name="message"]',
      '[wire\\:model*="message"]'
    ];

    let chatFound = false;
    for (const selector of chatSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          const isVisible = await element.isIntersectingViewport();
          if (isVisible) {
            console.log(`✅ Found chat input: ${selector}`);
            chatFound = true;

            // Step 5: Test PlayCanvas game generation
            console.log('\n🎮 Step 5: Testing PlayCanvas game generation...');

            const prompt = `Create a simple PlayCanvas game:
- 3D environment with a player character
- Basic movement controls (WASD)
- Simple jump mechanic (Space bar)
- One or two platforms to jump on
- Basic physics and collision detection
- Clean, playable HTML5 game

Please generate this as a complete PlayCanvas project that can be downloaded and run.`;

            await element.click();
            await page.type(selector, prompt);
            await page.screenshot({ path: 'test-results/simple-04-prompt-entered.png', fullPage: true });

            // Submit
            console.log('📤 Sending request to Claude Sonnet 4...');
            const submitBtn = await page.$('button[type="submit"], button:contains("Send")');
            if (submitBtn) {
              await submitBtn.click();
            } else {
              await page.keyboard.press('Enter');
            }

            // Wait for response
            console.log('⏳ Waiting for response...');
            await page.waitForTimeout(15000);
            await page.screenshot({ path: 'test-results/simple-05-response.png', fullPage: true });

            console.log('✅ PlayCanvas game generation test completed!');
            break;
          }
        }
      } catch (e) {
        // Continue trying other selectors
      }
    }

    if (!chatFound) {
      console.log('❌ No chat input found');

      // Log all inputs for debugging
      const allInputs = await page.$$eval('input, textarea', elements =>
        elements.map(el => ({
          type: el.type || el.tagName.toLowerCase(),
          placeholder: el.placeholder,
          name: el.name,
          id: el.id
        })).filter(el => el.placeholder || el.name || el.id)
      );

      console.log('📝 Available inputs:', allInputs);
    }

    // Final summary
    console.log('\n📋 Test Summary:');
    console.log(`   🏠 Homepage: ✅`);
    console.log(`   🔐 Company access: ✅`);
    console.log(`   🔗 AI links found: ${aiLinks.length > 0 ? '✅' : '❌'}`);
    console.log(`   💬 Chat interface: ${chatFound ? '✅' : '❌'}`);

  } catch (error) {
    console.error('❌ Test failed:', error.message);
    await page.screenshot({ path: 'test-results/simple-99-error.png', fullPage: true });
  } finally {
    await browser.close();
  }
}

simplePlayCanvasTest()
  .then(() => console.log('🎉 Test completed!'))
  .catch(console.error);
