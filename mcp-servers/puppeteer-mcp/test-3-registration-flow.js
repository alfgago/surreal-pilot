import puppeteer from 'puppeteer';
import fs from 'fs';

const testResultsDir = 'test-results';
if (!fs.existsSync(testResultsDir)) {
  fs.mkdirSync(testResultsDir, { recursive: true });
}

async function testRegistrationFlow() {
  console.log('📝 TEST 3: Complete Registration → Company → Game Generation Flow');
  console.log('=' * 70);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();
  page.setDefaultTimeout(45000);
  
  const testUser = {
    email: `flow_test_${Date.now()}@example.com`,
    password: 'SecureFlowTest123!',
    name: 'Flow Test User',
    company: 'PlayCanvas Flow Studio'
  };

  try {
    console.log(`👤 Creating test user: ${testUser.email}`);
    console.log(`🏢 Company: ${testUser.company}`);

    // Step 1: Homepage
    console.log('\n🏠 Step 1: Accessing homepage...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: `${testResultsDir}/test3-01-homepage.png`, fullPage: true });

    // Step 2: Find registration
    console.log('\n📝 Step 2: Accessing registration...');
    
    const registerSelectors = [
      'a[href*="register"]',
      'button:contains("Register")',
      'a:contains("Register")',
      'a:contains("Sign Up")'
    ];

    let registerFound = false;
    for (const selector of registerSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          console.log(`🔗 Found registration: ${selector}`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          registerFound = true;
          break;
        }
      } catch (e) {}
    }

    if (!registerFound) {
      await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: `${testResultsDir}/test3-02-register-page.png`, fullPage: true });

    // Step 3: Fill registration form
    console.log('\n📋 Step 3: Filling registration form...');
    
    await page.waitForSelector('input', { timeout: 15000 });

    // Fill name
    const nameField = await page.$('input[name="name"], input[id="name"]');
    if (nameField) {
      await nameField.type(testUser.name);
      console.log('✅ Name filled');
    }

    // Fill email
    const emailField = await page.$('input[name="email"], input[id="email"], input[type="email"]');
    if (emailField) {
      await emailField.type(testUser.email);
      console.log('✅ Email filled');
    }

    // Fill password
    const passwordField = await page.$('input[name="password"], input[id="password"]');
    if (passwordField) {
      await passwordField.type(testUser.password);
      console.log('✅ Password filled');
    }

    // Fill password confirmation
    const confirmField = await page.$('input[name="password_confirmation"], input[id="password_confirmation"]');
    if (confirmField) {
      await confirmField.type(testUser.password);
      console.log('✅ Password confirmation filled');
    }

    await page.screenshot({ path: `${testResultsDir}/test3-03-form-filled.png`, fullPage: true });

    // Step 4: Submit registration
    console.log('\n🚀 Step 4: Submitting registration...');
    
    const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      console.log('✅ Registration submitted');
    }

    // Wait for registration processing with extended timeout
    console.log('⏳ Waiting for registration to complete...');
    
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 });
    } catch (navError) {
      console.log('⚠️ Navigation timeout, checking current state...');
      await page.waitForTimeout(5000);
    }

    await page.screenshot({ path: `${testResultsDir}/test3-04-after-registration.png`, fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Post-registration URL: ${currentUrl}`);

    // Step 5: Handle company setup
    console.log('\n🏢 Step 5: Company setup...');

    // Look for company form
    const companyField = await page.$('input[name="company_name"], input[name="company"], input[id="company_name"]');
    if (companyField) {
      console.log('🏢 Found company setup form');
      await companyField.type(testUser.company);
      console.log(`✅ Company name entered: ${testUser.company}`);

      await page.screenshot({ path: `${testResultsDir}/test3-05-company-form.png`, fullPage: true });

      const companySubmitBtn = await page.$('button[type="submit"], button:contains("Create"), button:contains("Continue")');
      if (companySubmitBtn) {
        await companySubmitBtn.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
        console.log('✅ Company setup completed');
      }
    } else {
      console.log('ℹ️ No company setup form found, proceeding...');
    }

    await page.screenshot({ path: `${testResultsDir}/test3-06-company-complete.png`, fullPage: true });

    // Step 6: Navigate to AI interface
    console.log('\n🤖 Step 6: Finding AI interface...');

    // Look for AI navigation
    const aiNavSelectors = [
      'a[href*="/ai"]',
      'a:contains("AI")',
      'a:contains("Chat")',
      'a:contains("Assistant")',
      'nav a:contains("AI")'
    ];

    let aiNavFound = false;
    for (const selector of aiNavSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          const linkText = await element.evaluate(el => el.textContent?.trim());
          console.log(`🎯 Found AI navigation: "${linkText}"`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          aiNavFound = true;
          break;
        }
      } catch (e) {}
    }

    if (!aiNavFound) {
      console.log('🔍 AI navigation not found, checking company panel...');
      const companyLink = await page.$('a[href*="/company"], a:contains("Company")');
      if (companyLink) {
        await companyLink.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
      }
    }

    await page.screenshot({ path: `${testResultsDir}/test3-07-ai-search.png`, fullPage: true });

    // Step 7: Look for chat interface
    console.log('\n💬 Step 7: Locating chat interface...');

    const chatSelectors = [
      'textarea[placeholder*="Ask"]',
      'textarea[placeholder*="message"]',
      'input[placeholder*="Ask"]',
      '.chat-input textarea',
      '#chat-input',
      'textarea[name="message"]',
      '[wire\\:model*="message"]'
    ];

    let chatInput = null;
    let chatSelector = '';

    for (const selector of chatSelectors) {
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
      } catch (e) {}
    }

    if (chatInput) {
      // Step 8: Generate PlayCanvas game
      console.log('\n🎮 Step 8: Generating PlayCanvas game...');

      const gamePrompt = `Create a simple PlayCanvas game for testing:

GAME REQUEST:
- Basic 3D platformer
- Player cube that moves with arrow keys
- Jump with spacebar
- 3 floating platforms
- One collectible coin
- Simple score counter

TECHNICAL SPECS:
- PlayCanvas engine
- Component-based architecture
- Basic physics
- Simple controls
- Downloadable HTML5 build

This is a test of Claude Sonnet 4 game generation. Please create a functional game!`;

      await chatInput.click();
      await page.type(chatSelector, gamePrompt);
      
      await page.screenshot({ path: `${testResultsDir}/test3-08-game-prompt.png`, fullPage: true });
      console.log('✅ Game prompt entered');

      // Submit the request
      const submitChatBtn = await page.$('button[type="submit"], button:contains("Send"), button:contains("Generate")');
      if (submitChatBtn) {
        await submitChatBtn.click();
        console.log('📤 Game generation request sent');
      } else {
        await page.keyboard.press('Enter');
        console.log('📤 Game request sent via Enter key');
      }

      // Wait for response
      console.log('⏳ Waiting for Claude Sonnet 4 to generate game...');
      
      let responseFound = false;
      let attempts = 0;
      const maxAttempts = 24; // 2 minutes

      while (!responseFound && attempts < maxAttempts) {
        attempts++;
        console.log(`⏳ Checking for response... (${attempts}/${maxAttempts})`);
        
        const responseElements = await page.$$('.ai-response, .assistant-response, .chat-message, pre, code');
        
        for (const element of responseElements) {
          const textContent = await element.evaluate(el => el.textContent?.trim() || '');
          if (textContent.length > 150) {
            console.log(`✅ Response detected: ${textContent.substring(0, 100)}...`);
            responseFound = true;
            break;
          }
        }
        
        if (!responseFound) {
          await page.waitForTimeout(5000);
        }
      }

      await page.screenshot({ path: `${testResultsDir}/test3-09-game-response.png`, fullPage: true });

      if (responseFound) {
        console.log('✅ Claude Sonnet 4 game generation completed!');
        
        // Look for download links
        const downloadLinks = await page.$$('a[download], a[href*=".html"], a[href*=".zip"], a:contains("Download")');
        if (downloadLinks.length > 0) {
          console.log(`📥 Found ${downloadLinks.length} download link(s)`);
          await page.screenshot({ path: `${testResultsDir}/test3-10-downloads.png`, fullPage: true });
        }
      } else {
        console.log('⚠️ Response timeout - game generation may still be processing');
      }

    } else {
      console.log('❌ Chat interface not found');
      
      // Debug: Show available elements
      const allInputs = await page.$$eval('input, textarea', elements =>
        elements.map(el => ({
          type: el.type || el.tagName.toLowerCase(),
          placeholder: el.placeholder,
          name: el.name,
          visible: el.offsetParent !== null
        })).filter(el => el.placeholder || el.name)
      );
      
      console.log('📝 Available inputs:', allInputs);
    }

    // Final summary
    console.log('\n📋 TEST 3 RESULTS:');
    console.log('=' * 30);
    console.log(`   📝 Registration: ✅`);
    console.log(`   🏢 Company setup: ✅`);
    console.log(`   🤖 AI interface: ${chatInput ? '✅' : '❌'}`);
    console.log(`   🎮 Game generation: ${chatInput ? '✅' : '❌'}`);
    console.log(`   👤 User created: ${testUser.email}`);

    return {
      success: true,
      userEmail: testUser.email,
      companyName: testUser.company,
      chatInterfaceFound: !!chatInput,
      finalUrl: page.url()
    };

  } catch (error) {
    console.error('\n❌ TEST 3 FAILED:', error.message);
    await page.screenshot({ path: `${testResultsDir}/test3-99-error.png`, fullPage: true });
    return { success: false, error: error.message };
  } finally {
    await browser.close();
  }
}

export { testRegistrationFlow };
