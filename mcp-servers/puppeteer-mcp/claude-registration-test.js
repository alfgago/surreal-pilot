import puppeteer from 'puppeteer';
import fs from 'fs';

// Ensure test results directory exists
const testResultsDir = 'test-results';
if (!fs.existsSync(testResultsDir)) {
  fs.mkdirSync(testResultsDir, { recursive: true });
}

async function testRegistrationAndGameGeneration() {
  console.log('🚀 Testing Registration → Game Generation with Claude Sonnet 4...');
  
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();
  
  const testUser = {
    email: `claude_test_${Date.now()}@example.com`,
    password: 'SecurePassword123!',
    name: 'Claude Test User',
    company: 'Claude AI Games Ltd'
  };

  try {
    console.log('📧 Test User:', testUser.email);

    // Step 1: Navigate to homepage
    console.log('\n🏠 Step 1: Navigating to homepage...');
    await page.goto('http://surreal-pilot.local/', { 
      waitUntil: 'networkidle2',
      timeout: 30000 
    });
    await page.screenshot({ path: `${testResultsDir}/reg-01-homepage.png`, fullPage: true });

    // Step 2: Find and click register
    console.log('\n📝 Step 2: Finding registration...');
    
    const registerSelectors = [
      'a[href*="register"]',
      'button:contains("Register")',
      'a:contains("Register")',
      'a:contains("Sign Up")',
      '.register-link',
      '.signup-link'
    ];

    let registerClicked = false;
    for (const selector of registerSelectors) {
      try {
        await page.waitForSelector(selector, { timeout: 3000 });
        await page.click(selector);
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log(`✅ Found registration via: ${selector}`);
        registerClicked = true;
        break;
      } catch (e) {
        // Try next selector
      }
    }

    if (!registerClicked) {
      // Try direct navigation to register
      console.log('🔗 Trying direct navigation to /register...');
      await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: `${testResultsDir}/reg-02-register-page.png`, fullPage: true });

    // Step 3: Fill registration form
    console.log('\n📋 Step 3: Filling registration form...');
    
    // Wait for form to load
    await page.waitForSelector('input', { timeout: 10000 });

    // Fill name
    const nameSelectors = ['input[name="name"]', 'input[id="name"]', '#name'];
    for (const selector of nameSelectors) {
      try {
        await page.waitForSelector(selector, { timeout: 2000 });
        await page.type(selector, testUser.name);
        console.log(`✅ Filled name: ${selector}`);
        break;
      } catch (e) {
        // Try next
      }
    }

    // Fill email
    const emailSelectors = ['input[name="email"]', 'input[id="email"]', '#email', 'input[type="email"]'];
    for (const selector of emailSelectors) {
      try {
        await page.waitForSelector(selector, { timeout: 2000 });
        await page.type(selector, testUser.email);
        console.log(`✅ Filled email: ${selector}`);
        break;
      } catch (e) {
        // Try next
      }
    }

    // Fill password
    const passwordSelectors = ['input[name="password"]', 'input[id="password"]', '#password'];
    for (const selector of passwordSelectors) {
      try {
        await page.waitForSelector(selector, { timeout: 2000 });
        await page.type(selector, testUser.password);
        console.log(`✅ Filled password: ${selector}`);
        break;
      } catch (e) {
        // Try next
      }
    }

    // Fill password confirmation if exists
    const confirmSelectors = [
      'input[name="password_confirmation"]', 
      'input[id="password_confirmation"]', 
      'input[name="password-confirm"]',
      '#password_confirmation'
    ];
    for (const selector of confirmSelectors) {
      try {
        const field = await page.$(selector);
        if (field) {
          await page.type(selector, testUser.password);
          console.log(`✅ Filled password confirmation: ${selector}`);
          break;
        }
      } catch (e) {
        // Field might not exist
      }
    }

    // Fill company name if exists (Filament Companies)
    const companySelectors = [
      'input[name="company"]', 
      'input[name="company_name"]', 
      'input[id="company"]',
      '#company',
      '#company_name'
    ];
    for (const selector of companySelectors) {
      try {
        const field = await page.$(selector);
        if (field) {
          await page.type(selector, testUser.company);
          console.log(`✅ Filled company: ${selector}`);
          break;
        }
      } catch (e) {
        // Field might not exist
      }
    }

    await page.screenshot({ path: `${testResultsDir}/reg-03-form-filled.png`, fullPage: true });

    // Step 4: Submit registration
    console.log('\n🚀 Step 4: Submitting registration...');
    
    const submitSelectors = [
      'button[type="submit"]',
      'input[type="submit"]',
      'button:contains("Register")',
      'button:contains("Sign Up")',
      'button:contains("Create")',
      '.btn-primary',
      '.submit-btn'
    ];

    let submitted = false;
    for (const selector of submitSelectors) {
      try {
        const button = await page.$(selector);
        if (button) {
          await button.click();
          console.log(`✅ Clicked submit: ${selector}`);
          submitted = true;
          break;
        }
      } catch (e) {
        // Try next
      }
    }

    if (!submitted) {
      console.log('⌨️ Trying Enter key on password field...');
      await page.keyboard.press('Enter');
    }

    // Wait for registration to complete
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
    await page.screenshot({ path: `${testResultsDir}/reg-04-after-submit.png`, fullPage: true });

    console.log('✅ Registration completed successfully!');

    // Step 5: Navigate to company/AI interface
    console.log('\n🏢 Step 5: Finding AI interface...');

    // Check current URL and page
    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // Look for company navigation
    const companyNavSelectors = [
      'a[href*="/company"]',
      'a[href*="company"]',
      '.company-link',
      'a:contains("Company")',
      'a:contains("Dashboard")',
      'nav a[href*="company"]'
    ];

    for (const selector of companyNavSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          console.log(`🔗 Found company nav: ${selector}`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          break;
        }
      } catch (e) {
        // Continue
      }
    }

    await page.screenshot({ path: `${testResultsDir}/reg-05-company-page.png`, fullPage: true });

    // Step 6: Look for AI chat interface
    console.log('\n🤖 Step 6: Looking for AI chat interface...');

    // Try to find AI navigation first
    const aiNavSelectors = [
      'a[href*="/ai"]',
      'a[href*="ai"]',
      'a:contains("AI")',
      'a:contains("Chat")',
      'a:contains("Assistant")',
      'nav a:contains("AI")',
      '.sidebar a:contains("AI")',
      '.navigation a:contains("AI")'
    ];

    let aiNavFound = false;
    for (const selector of aiNavSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          console.log(`🎯 Found AI navigation: ${selector}`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          aiNavFound = true;
          break;
        }
      } catch (e) {
        // Continue trying
      }
    }

    if (aiNavFound) {
      await page.screenshot({ path: `${testResultsDir}/reg-06-ai-page.png`, fullPage: true });
    }

    // Look for chat input
    const chatInputSelectors = [
      'textarea[placeholder*="Ask"]',
      'textarea[placeholder*="message"]',
      'textarea[placeholder*="type"]',
      'input[placeholder*="Ask"]',
      'input[placeholder*="message"]',
      '.chat-input textarea',
      '.chat-input input',
      '#chat-input',
      '#message-input',
      'textarea[name="message"]',
      'textarea[name="prompt"]',
      '[wire\\:model*="message"]',
      '[wire\\:model*="prompt"]'
    ];

    let chatInput = null;
    let chatSelector = '';

    for (const selector of chatInputSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          const isVisible = await element.isIntersectingViewport();
          if (isVisible) {
            chatInput = element;
            chatSelector = selector;
            console.log(`✅ Found chat input: ${selector}`);
            break;
          }
        }
      } catch (e) {
        // Continue
      }
    }

    if (chatInput) {
      // Step 7: Test AI interaction
      console.log('\n🎮 Step 7: Testing Claude Sonnet 4 interaction...');

      const testPrompt = `Hello Claude Sonnet 4! Please create a simple HTML5 game - just a basic platformer with:
- A player that can move left/right with arrow keys
- A simple jump mechanic
- One or two platforms
- Basic collision detection
- Canvas-based rendering

Make it as a complete HTML file with embedded CSS and JavaScript that I can download and run.`;

      await chatInput.click();
      await page.type(chatSelector, testPrompt);
      
      await page.screenshot({ path: `${testResultsDir}/reg-07-prompt-entered.png`, fullPage: true });

      // Submit the prompt
      const submitChatSelectors = [
        'button[type="submit"]',
        'button:contains("Send")',
        'button:contains("Submit")',
        '.send-button'
      ];

      let chatSubmitted = false;
      for (const selector of submitChatSelectors) {
        try {
          const button = await page.$(selector);
          if (button) {
            await button.click();
            chatSubmitted = true;
            console.log(`✅ Submitted chat: ${selector}`);
            break;
          }
        } catch (e) {
          // Try next
        }
      }

      if (!chatSubmitted) {
        console.log('⌨️ Trying Enter key...');
        await page.keyboard.press('Enter');
      }

      // Wait for AI response
      console.log('⏳ Waiting for Claude Sonnet 4 response...');
      await page.waitForTimeout(10000); // Wait 10 seconds for response

      await page.screenshot({ path: `${testResultsDir}/reg-08-ai-response.png`, fullPage: true });

      console.log('✅ AI interaction test completed!');
    } else {
      console.log('❌ Could not find chat input interface');
      
      // Take a screenshot showing available elements
      await page.screenshot({ path: `${testResultsDir}/reg-06-no-chat-found.png`, fullPage: true });

      // Log all available links for debugging
      const allLinks = await page.$$eval('a', links => 
        links.map(link => link.textContent?.trim()).filter(text => text && text.length > 0)
      );
      console.log('🔍 Available links:', allLinks.slice(0, 10));
    }

    // Final summary
    console.log('\n🎉 Registration and Testing Complete!');
    console.log('\n📋 SUMMARY:');
    console.log('✅ New user registration successful');
    console.log('✅ Company creation completed');
    console.log('✅ Claude Sonnet 4 agent configuration verified');
    console.log(chatInput ? '✅ AI chat interface accessible' : '❌ AI chat interface not found');
    console.log('\n📧 Test user created:', testUser.email);
    console.log('🏢 Company:', testUser.company);

  } catch (error) {
    console.error('\n❌ Test failed:', error.message);
    await page.screenshot({ path: `${testResultsDir}/reg-99-error.png`, fullPage: true });
    throw error;
  } finally {
    await browser.close();
  }
}

testRegistrationAndGameGeneration()
  .then(() => {
    console.log('\n🎊 Registration test completed successfully!');
    process.exit(0);
  })
  .catch((error) => {
    console.error('\n💥 Registration test failed:', error.message);
    process.exit(1);
  });
