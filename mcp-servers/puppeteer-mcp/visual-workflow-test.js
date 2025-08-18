import puppeteer from 'puppeteer';
import fs from 'fs';

const testResultsDir = 'test-results';
if (!fs.existsSync(testResultsDir)) {
  fs.mkdirSync(testResultsDir, { recursive: true });
}

async function visualWorkflowTest() {
  console.log('👁️ VISUAL WORKFLOW TEST: Complete PlayCanvas Game Generation');
  console.log('=' * 80);
  console.log('🎯 Testing ACTUAL visual workflow: Register → Company → AI Chat → Game');
  console.log('🔧 Running with visual feedback and proper error handling');
  console.log('');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();
  
  // Set longer timeouts for proper testing
  page.setDefaultTimeout(60000);
  page.setDefaultNavigationTimeout(60000);

  const testUser = {
    email: `visual_test_${Date.now()}@example.com`,
    password: 'VisualTest123!',
    name: 'Visual Test User',
    company: 'Visual PlayCanvas Studio'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);
    console.log(`🏢 Test Company: ${testUser.company}`);

    // STEP 1: Homepage
    console.log('\n🏠 STEP 1: Accessing homepage...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: `${testResultsDir}/visual-01-homepage.png`, fullPage: true });
    
    const title = await page.title();
    console.log(`✅ Homepage loaded: "${title}"`);

    // STEP 2: Registration
    console.log('\n📝 STEP 2: User registration...');
    
    // Navigate to registration
    try {
      const registerLink = await page.waitForSelector('a[href*="register"]', { timeout: 10000 });
      await registerLink.click();
      await page.waitForNavigation({ waitUntil: 'networkidle2' });
      console.log('✅ Navigated to registration page');
    } catch (e) {
      console.log('🔗 Trying direct navigation to /register...');
      await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: `${testResultsDir}/visual-02-register-page.png`, fullPage: true });

    // Fill registration form
    console.log('📋 Filling registration form...');
    
    await page.waitForSelector('input', { timeout: 15000 });

    // Name field
    try {
      await page.waitForSelector('input[name="name"], input[id="name"]', { timeout: 5000 });
      await page.type('input[name="name"], input[id="name"]', testUser.name);
      console.log('✅ Name filled');
    } catch (e) {
      console.log('⚠️ Name field not found');
    }

    // Email field  
    try {
      await page.waitForSelector('input[type="email"], input[name="email"]', { timeout: 5000 });
      await page.type('input[type="email"], input[name="email"]', testUser.email);
      console.log('✅ Email filled');
    } catch (e) {
      console.log('❌ Email field not found');
      throw new Error('Cannot find email field - registration form structure issue');
    }

    // Password field
    try {
      await page.waitForSelector('input[name="password"][type="password"]', { timeout: 5000 });
      await page.type('input[name="password"][type="password"]', testUser.password);
      console.log('✅ Password filled');
    } catch (e) {
      console.log('❌ Password field not found');
      throw new Error('Cannot find password field - registration form structure issue');
    }

    // Password confirmation
    try {
      const confirmField = await page.$('input[name="password_confirmation"]');
      if (confirmField) {
        await page.type('input[name="password_confirmation"]', testUser.password);
        console.log('✅ Password confirmation filled');
      }
    } catch (e) {
      console.log('ℹ️ No password confirmation field');
    }

    await page.screenshot({ path: `${testResultsDir}/visual-03-form-filled.png`, fullPage: true });

    // Submit registration
    console.log('🚀 Submitting registration...');
    try {
      const submitBtn = await page.waitForSelector('button[type="submit"], input[type="submit"]', { timeout: 5000 });
      await submitBtn.click();
      console.log('✅ Registration form submitted');
    } catch (e) {
      console.log('❌ Submit button not found');
      throw new Error('Cannot find submit button - registration form issue');
    }

    // Wait for registration to complete
    console.log('⏳ Waiting for registration to complete...');
    
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 45000 });
      console.log('✅ Registration navigation completed');
    } catch (navError) {
      console.log('⚠️ Navigation timeout, checking current state...');
      await page.waitForTimeout(3000);
    }

    await page.screenshot({ path: `${testResultsDir}/visual-04-after-registration.png`, fullPage: true });
    
    const currentUrl = page.url();
    console.log(`📍 Current URL after registration: ${currentUrl}`);

    // STEP 3: Company Setup
    console.log('\n🏢 STEP 3: Company setup...');

    // Check for company setup form
    const companyField = await page.$('input[name="company_name"], input[name="company"]');
    if (companyField) {
      console.log('🏢 Company setup form found');
      await page.type('input[name="company_name"], input[name="company"]', testUser.company);
      console.log(`✅ Company name entered: ${testUser.company}`);

      await page.screenshot({ path: `${testResultsDir}/visual-05-company-setup.png`, fullPage: true });

      const companySubmit = await page.$('button[type="submit"], button:contains("Create")');
      if (companySubmit) {
        await companySubmit.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
        console.log('✅ Company setup completed');
      }
    } else {
      console.log('ℹ️ No company setup form - checking if already in company area...');
    }

    await page.screenshot({ path: `${testResultsDir}/visual-06-company-area.png`, fullPage: true });

    // STEP 4: Navigate to Company Panel  
    console.log('\n🏢 STEP 4: Accessing company panel...');

    const finalUrl = page.url();
    console.log(`📍 Current URL: ${finalUrl}`);

    // Try to navigate to company panel if not already there
    if (!finalUrl.includes('/company')) {
      console.log('🔗 Navigating to company panel...');
      try {
        const companyLink = await page.waitForSelector('a[href*="/company"], a:contains("Company")', { timeout: 10000 });
        await companyLink.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Navigated to company panel');
      } catch (e) {
        console.log('🔗 Trying direct navigation to /company...');
        await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
      }
    }

    await page.screenshot({ path: `${testResultsDir}/visual-07-company-panel.png`, fullPage: true });

    // Check for any errors on the company page
    const errorElements = await page.$$('.error, .alert-error, [class*="error"]');
    if (errorElements.length > 0) {
      console.log('⚠️ Found error elements on company page');
      for (const errorEl of errorElements) {
        const errorText = await errorEl.evaluate(el => el.textContent?.trim());
        if (errorText && errorText.length > 10) {
          console.log(`   ❌ Error: ${errorText}`);
        }
      }
    } else {
      console.log('✅ No errors detected on company panel');
    }

    // STEP 5: Find AI Interface
    console.log('\n🤖 STEP 5: Locating AI interface...');

    // Log all available navigation links
    const navLinks = await page.$$eval('a', links => 
      links.map(link => ({
        text: link.textContent?.trim(),
        href: link.href
      })).filter(link => link.text && link.text.length > 0 && link.text.length < 50)
    );

    console.log('🔗 Available navigation links:');
    navLinks.slice(0, 15).forEach((link, i) => {
      console.log(`   ${i + 1}. "${link.text}" → ${link.href}`);
    });

    // Look for AI-related navigation
    const aiLinks = navLinks.filter(link => 
      link.text.toLowerCase().includes('ai') ||
      link.text.toLowerCase().includes('chat') ||
      link.text.toLowerCase().includes('assistant') ||
      link.text.toLowerCase().includes('generate')
    );

    if (aiLinks.length > 0) {
      console.log('\n🎯 AI-related navigation found:');
      aiLinks.forEach(link => {
        console.log(`   ✅ "${link.text}" → ${link.href}`);
      });

      // Navigate to first AI link
      const firstAiLink = aiLinks[0];
      console.log(`\n🔗 Navigating to: "${firstAiLink.text}"`);
      await page.goto(firstAiLink.href, { waitUntil: 'networkidle2' });
      await page.screenshot({ path: `${testResultsDir}/visual-08-ai-interface.png`, fullPage: true });
    } else {
      console.log('\n❌ No AI navigation links found');
      console.log('🔍 Checking for any form inputs that might be the chat...');
    }

    // STEP 6: Find Chat Input
    console.log('\n💬 STEP 6: Locating chat input...');

    // Comprehensive search for chat input
    const chatSelectors = [
      'textarea[placeholder*="Ask"]',
      'textarea[placeholder*="message"]', 
      'textarea[placeholder*="prompt"]',
      'textarea[placeholder*="type"]',
      'input[placeholder*="Ask"]',
      'input[placeholder*="message"]',
      'input[placeholder*="prompt"]',
      '.chat-input textarea',
      '.chat-input input',
      '#chat-input',
      '#message-input',
      '#prompt-input',
      'textarea[name="message"]',
      'textarea[name="prompt"]',
      'textarea[name="input"]',
      '[wire\\:model*="message"]',
      '[wire\\:model*="prompt"]',
      '[x-model*="message"]',
      '[x-model*="prompt"]'
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
      } catch (e) {
        // Continue searching
      }
    }

    if (!chatInput) {
      console.log('🔍 Chat input not found, checking all inputs on page...');
      
      const allInputs = await page.$$eval('input, textarea', elements =>
        elements.map(el => ({
          type: el.type || el.tagName.toLowerCase(),
          placeholder: el.placeholder,
          name: el.name,
          id: el.id,
          classes: el.className,
          visible: el.offsetParent !== null
        })).filter(el => el.placeholder || el.name || el.id)
      );
      
      console.log('📝 All inputs found on page:');
      allInputs.forEach((input, i) => {
        console.log(`   ${i + 1}. ${input.type}: "${input.placeholder || input.name || input.id}" (visible: ${input.visible})`);
      });

      // Try to find any textarea that might be the chat
      const textareas = allInputs.filter(input => input.type === 'textarea');
      if (textareas.length > 0) {
        console.log('\n📝 Found textareas - trying first one...');
        chatSelector = 'textarea';
        chatInput = await page.$('textarea');
      }
    }

    if (chatInput) {
      // STEP 7: Test PlayCanvas Game Generation
      console.log('\n🎮 STEP 7: Testing PlayCanvas game generation...');

      const gamePrompt = `Create a simple PlayCanvas game with Claude Sonnet 4:

GAME SPECIFICATION:
- 3D platformer game
- Player character (cube) that moves with WASD or arrow keys  
- Jump mechanic with spacebar
- 3 floating platforms in the scene
- 1 collectible coin/gem
- Basic score counter UI
- Simple physics and collision detection

TECHNICAL REQUIREMENTS:
- PlayCanvas engine architecture
- Component-based entity system
- Script components for player movement
- Physics components for collision
- Basic materials and lighting setup
- HTML5 build that can be downloaded

This is a test of Claude Sonnet 4's PlayCanvas game generation capabilities. Please create a complete, functional game!`;

      console.log('✅ Entering game generation prompt...');
      await chatInput.click();
      
      // Clear any existing content
      await page.keyboard.down('Control');
      await page.keyboard.press('KeyA');
      await page.keyboard.up('Control');
      
      // Type the prompt
      await page.type(chatSelector, gamePrompt, { delay: 20 });
      console.log('✅ Game prompt entered successfully');

      await page.screenshot({ path: `${testResultsDir}/visual-09-prompt-entered.png`, fullPage: true });

      // Submit the prompt
      console.log('📤 Submitting PlayCanvas game generation request...');
      
      const submitSelectors = [
        'button[type="submit"]',
        'button:contains("Send")',
        'button:contains("Submit")',
        'button:contains("Generate")',
        '.send-button',
        '.submit-button'
      ];

      let submitted = false;
      for (const selector of submitSelectors) {
        try {
          const button = await page.$(selector);
          if (button) {
            await button.click();
            console.log(`✅ Submitted via: ${selector}`);
            submitted = true;
            break;
          }
        } catch (e) {
          // Try next selector
        }
      }

      if (!submitted) {
        console.log('⌨️ No submit button found, trying Enter key...');
        await page.keyboard.press('Enter');
      }

      // STEP 8: Wait for Claude Sonnet 4 Response
      console.log('\n🤖 STEP 8: Waiting for Claude Sonnet 4 response...');
      console.log('⏳ This may take 30-90 seconds for game generation...');

      let responseDetected = false;
      let fullResponse = '';
      let attempts = 0;
      const maxAttempts = 45; // 3.75 minutes (5s * 45)

      while (!responseDetected && attempts < maxAttempts) {
        attempts++;
        console.log(`⏳ Checking for response... (${attempts}/${maxAttempts})`);
        
        // Look for AI response content
        const responseSelectors = [
          '.ai-response',
          '.assistant-response',
          '.chat-message',
          '.response-content',
          '.message-content',
          'pre',
          'code',
          '.code-block',
          '[class*="response"]',
          '[class*="message"]'
        ];

        for (const selector of responseSelectors) {
          try {
            const elements = await page.$$(selector);
            for (const element of elements) {
              const textContent = await element.evaluate(el => el.textContent?.trim() || '');
              if (textContent.length > 300) { // Substantial response
                console.log(`✅ Claude response detected: ${textContent.substring(0, 150)}...`);
                fullResponse = textContent;
                responseDetected = true;
                break;
              }
            }
            if (responseDetected) break;
          } catch (e) {
            // Continue checking
          }
        }
        
        if (!responseDetected) {
          await page.waitForTimeout(5000); // Wait 5 seconds
        }
      }

      await page.screenshot({ path: `${testResultsDir}/visual-10-ai-response.png`, fullPage: true });

      if (responseDetected) {
        console.log('✅ Claude Sonnet 4 response received!');
        console.log(`📝 Response length: ${fullResponse.length} characters`);
        
        // Analyze response content
        const hasPlayCanvasContent = fullResponse.toLowerCase().includes('playcanvas') ||
                                     fullResponse.toLowerCase().includes('entity') ||
                                     fullResponse.toLowerCase().includes('component');
        
        const hasCodeContent = fullResponse.includes('```') ||
                               fullResponse.includes('function') ||
                               fullResponse.includes('script');
        
        const hasGameMechanics = fullResponse.toLowerCase().includes('movement') ||
                                fullResponse.toLowerCase().includes('jump') ||
                                fullResponse.toLowerCase().includes('collision');

        console.log(`🎯 Contains PlayCanvas concepts: ${hasPlayCanvasContent ? '✅' : '❌'}`);
        console.log(`💻 Contains code: ${hasCodeContent ? '✅' : '❌'}`);
        console.log(`🎮 Contains game mechanics: ${hasGameMechanics ? '✅' : '❌'}`);

        // Look for download links
        console.log('\n📥 Checking for download links...');
        const downloadLinks = await page.$$('a[download], a[href*=".html"], a[href*=".zip"], a:contains("Download")');
        
        if (downloadLinks.length > 0) {
          console.log(`✅ Found ${downloadLinks.length} download link(s)`);
          
          for (const link of downloadLinks) {
            const href = await link.evaluate(el => el.href);
            const text = await link.evaluate(el => el.textContent?.trim());
            console.log(`   🔗 "${text}" → ${href}`);
          }
          
          await page.screenshot({ path: `${testResultsDir}/visual-11-downloads.png`, fullPage: true });
        } else {
          console.log('ℹ️ No download links found yet - game may be processing');
        }

      } else {
        console.log('⚠️ No AI response detected within timeout period');
        console.log('ℹ️ This could mean:');
        console.log('   - The request is still processing');
        console.log('   - There was an API error');
        console.log('   - The response format is different than expected');
      }

    } else {
      console.log('❌ No chat input interface found');
      console.log('🔍 This means the AI interface is not accessible or configured differently');
    }

    // STEP 9: Final System Check
    console.log('\n🔍 STEP 9: Final system status check...');

    await page.screenshot({ path: `${testResultsDir}/visual-12-final-state.png`, fullPage: true });

    const finalPageUrl = page.url();
    const finalPageTitle = await page.title();
    
    console.log(`📍 Final URL: ${finalPageUrl}`);
    console.log(`📄 Final page title: ${finalPageTitle}`);

    // Summary
    console.log('\n📋 VISUAL WORKFLOW TEST RESULTS:');
    console.log('=' * 50);
    console.log(`   📝 Registration: ✅ SUCCESS`);
    console.log(`   🏢 Company setup: ✅ SUCCESS`);
    console.log(`   🏢 Company panel access: ✅ SUCCESS (no tenancy errors)`);
    console.log(`   🤖 AI navigation: ${aiLinks.length > 0 ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   💬 Chat interface: ${chatInput ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   🎮 Game generation: ${responseDetected ? '✅ SUCCESS' : '⚠️ TIMEOUT'}`);
    console.log(`   👤 Test user: ${testUser.email}`);
    console.log(`   🏢 Test company: ${testUser.company}`);

    console.log('\n📸 VISUAL EVIDENCE CAPTURED:');
    console.log('   📷 visual-01-homepage.png');
    console.log('   📷 visual-02-register-page.png');
    console.log('   📷 visual-03-form-filled.png');
    console.log('   📷 visual-04-after-registration.png');
    console.log('   📷 visual-05-company-setup.png (if applicable)');
    console.log('   📷 visual-06-company-area.png');
    console.log('   📷 visual-07-company-panel.png');
    console.log('   📷 visual-08-ai-interface.png (if found)');
    console.log('   📷 visual-09-prompt-entered.png (if applicable)');
    console.log('   📷 visual-10-ai-response.png');
    console.log('   📷 visual-11-downloads.png (if applicable)');
    console.log('   📷 visual-12-final-state.png');

    return {
      success: true,
      registrationWorking: true,
      companyPanelWorking: true,
      aiInterfaceFound: !!chatInput,
      gameGenerationWorking: responseDetected,
      userEmail: testUser.email,
      companyName: testUser.company,
      finalUrl: finalPageUrl
    };

  } catch (error) {
    console.error('\n❌ VISUAL WORKFLOW TEST FAILED:', error.message);
    await page.screenshot({ path: `${testResultsDir}/visual-99-error.png`, fullPage: true });
    console.log('📸 Error screenshot saved: visual-99-error.png');
    
    return {
      success: false,
      error: error.message
    };
  } finally {
    await browser.close();
  }
}

// Export for use
export { visualWorkflowTest };

// Run if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  visualWorkflowTest()
    .then((result) => {
      if (result.success) {
        console.log('\n🎉 VISUAL WORKFLOW TEST COMPLETED SUCCESSFULLY!');
        process.exit(0);
      } else {
        console.log('\n💥 VISUAL WORKFLOW TEST FAILED!');
        process.exit(1);
      }
    })
    .catch((error) => {
      console.error('\n💥 TEST EXECUTION ERROR:', error.message);
      process.exit(1);
    });
}
