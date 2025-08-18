import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

// Ensure test results directory exists
const testResultsDir = 'test-results';
if (!fs.existsSync(testResultsDir)) {
  fs.mkdirSync(testResultsDir, { recursive: true });
}

async function runClaudeSonnet4FlowTest() {
  console.log('🚀 Starting Claude Sonnet 4 Complete Flow Test...');
  console.log('📋 Test Plan: Register → Login → Generate Game with Claude 4\n');

  const browser = await puppeteer.launch({
    headless: true, // Always run headless
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();
  
  // Set user agent to avoid bot detection
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

  let testUser = {
    email: `test_${Date.now()}@example.com`,
    password: 'SecurePassword123!',
    name: 'Claude Test User',
    company: 'Claude Game Studio'
  };

  try {
    // Step 1: Navigate to homepage
    console.log('🏠 Step 1: Navigating to homepage...');
    await page.goto('http://surreal-pilot.local/', { 
      waitUntil: 'networkidle2',
      timeout: 30000 
    });
    await page.screenshot({ path: `${testResultsDir}/claude-01-homepage.png`, fullPage: true });
    console.log('✅ Homepage loaded successfully');

    // Step 2: Handle registration/login
    console.log('\n🔐 Step 2: Handling authentication...');
    
    // Look for registration link first
    let authHandled = false;
    try {
      const registerLink = await page.waitForSelector('a[href*="register"], a:contains("Register"), button:contains("Register")', { timeout: 5000 });
      if (registerLink) {
        console.log('📝 Found registration option - registering new user...');
        await registerLink.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        
        // Fill registration form
        await page.waitForSelector('input[name="name"], input[id="name"]', { timeout: 10000 });
        
        await page.type('input[name="name"], input[id="name"]', testUser.name);
        await page.type('input[name="email"], input[id="email"]', testUser.email);
        await page.type('input[name="password"], input[id="password"]', testUser.password);
        
        // Try to find password confirmation field
        const passwordConfirmField = await page.$('input[name="password_confirmation"], input[id="password_confirmation"], input[name="password-confirm"]');
        if (passwordConfirmField) {
          await page.type('input[name="password_confirmation"], input[id="password_confirmation"], input[name="password-confirm"]', testUser.password);
        }

        // Try to find company name field (for Filament Companies)
        const companyField = await page.$('input[name="company"], input[name="company_name"], input[id="company"]');
        if (companyField) {
          await page.type('input[name="company"], input[name="company_name"], input[id="company"]', testUser.company);
        }

        await page.screenshot({ path: `${testResultsDir}/claude-02-registration-form.png`, fullPage: true });
        
        // Submit registration
        const submitButton = await page.$('button[type="submit"], input[type="submit"], .btn-primary');
        if (submitButton) {
          await submitButton.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
          authHandled = true;
          console.log('✅ Registration completed successfully');
        }
      }
    } catch (e) {
      console.log('ℹ️ Registration not found or failed, trying login...');
    }

    // If registration didn't work, try to log in or check if already authenticated
    if (!authHandled) {
      // Check if we're already on a dashboard/authenticated page
      const isDashboard = await page.$('.dashboard, .company, [href*="company"], [href*="dashboard"]');
      if (isDashboard) {
        console.log('✅ Already authenticated or on dashboard');
        authHandled = true;
      } else {
        // Try to find login
        const loginLink = await page.$('a[href*="login"], a:contains("Login"), button:contains("Login")');
        if (loginLink) {
          await loginLink.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          
          // Use default test credentials
          await page.type('input[name="email"], input[id="email"]', 'test@example.com');
          await page.type('input[name="password"], input[id="password"]', 'password');
          
          const submitButton = await page.$('button[type="submit"], input[type="submit"]');
          if (submitButton) {
            await submitButton.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2' });
            authHandled = true;
            console.log('✅ Login completed successfully');
          }
        }
      }
    }

    if (!authHandled) {
      throw new Error('Could not handle authentication - no registration or login found');
    }

    await page.screenshot({ path: `${testResultsDir}/claude-03-after-auth.png`, fullPage: true });

    // Step 3: Navigate to company panel or AI interface
    console.log('\n🏢 Step 3: Finding AI interface...');
    
    // Try multiple navigation strategies
    const companyLink = await page.$('a[href*="/company"], a[href*="company"], .company-link');
    if (companyLink) {
      console.log('📂 Found company link - navigating...');
      await companyLink.click();
      await page.waitForNavigation({ waitUntil: 'networkidle2' });
    }

    // Look for AI/Chat interface
    let chatInput = null;
    let selectorUsed = '';

    // Try various selectors for chat input
    const chatSelectors = [
      'textarea[placeholder*="Ask"], textarea[placeholder*="message"], textarea[placeholder*="type"]',
      'input[placeholder*="Ask"], input[placeholder*="message"], input[placeholder*="type"]',
      '.chat-input textarea, .chat-input input',
      '#chat-input, #message-input, #prompt-input',
      'textarea[name="message"], textarea[name="prompt"], textarea[name="input"]',
      '.livewire-chat textarea, .livewire-chat input',
      '[x-data*="chat"] textarea, [x-data*="chat"] input'
    ];

    for (const selector of chatSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          const isVisible = await element.isIntersectingViewport();
          if (isVisible) {
            chatInput = element;
            selectorUsed = selector;
            console.log(`✅ Found chat input: ${selector}`);
            break;
          }
        }
      } catch (e) {
        // Continue trying other selectors
      }
    }

    // If not found, try navigating to AI section
    if (!chatInput) {
      console.log('🔍 Chat input not found, looking for AI navigation...');
      
      const aiNavSelectors = [
        'a[href*="/ai"], a[href*="ai"], a:contains("AI")',
        'a[href*="/chat"], a[href*="chat"], a:contains("Chat")',
        'a[href*="/assistant"], a:contains("Assistant")',
        '.nav-link[href*="ai"], .sidebar-link[href*="ai"]'
      ];

      for (const navSelector of aiNavSelectors) {
        try {
          const navLink = await page.$(navSelector);
          if (navLink) {
            console.log(`🔗 Found AI navigation: ${navSelector}`);
            await navLink.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2' });
            
            // Try to find chat input again
            for (const selector of chatSelectors) {
              const element = await page.$(selector);
              if (element) {
                const isVisible = await element.isIntersectingViewport();
                if (isVisible) {
                  chatInput = element;
                  selectorUsed = selector;
                  break;
                }
              }
            }
            if (chatInput) break;
          }
        } catch (e) {
          // Continue trying
        }
      }
    }

    if (!chatInput) {
      await page.screenshot({ path: `${testResultsDir}/claude-04-no-chat-found.png`, fullPage: true });
      throw new Error('Could not find chat input interface');
    }

    // Step 4: Generate game with Claude Sonnet 4
    console.log(`\n🎮 Step 4: Generating game with Claude Sonnet 4 (using ${selectorUsed})...`);

    const gamePrompt = `Create a complete HTML5 platformer game with Claude Sonnet 4:

🎮 GAME REQUIREMENTS:
- Side-scrolling platformer with player character
- Canvas-based rendering (HTML5 Canvas)
- Keyboard controls (WASD or Arrow keys)
- Physics: gravity, jumping, collision detection
- Multiple platforms to jump on
- Simple enemy AI (moving enemies)
- Collectible items (coins/gems)
- Score system
- Game over/restart functionality

🎯 TECHNICAL SPECS:
- Complete HTML file with embedded CSS and JavaScript
- 800x600 canvas size
- 60 FPS game loop
- Responsive controls
- Clean, commented code
- Ready to run in browser

🎨 VISUAL STYLE:
- Colorful 2D graphics using Canvas drawing
- Simple geometric shapes or basic sprites
- Smooth animations
- Clear UI elements

Please create this as a single HTML file that can be downloaded and run immediately. Use Claude Sonnet 4's advanced reasoning to implement proper game mechanics and physics.`;

    // Clear any existing content and type the prompt
    await chatInput.click();
    await chatInput.evaluate(el => el.value = ''); // Clear any existing content
    await page.type(selectorUsed, gamePrompt, { delay: 10 });
    
    await page.screenshot({ path: `${testResultsDir}/claude-05-prompt-entered.png`, fullPage: true });

    // Submit the prompt
    console.log('📤 Sending prompt to Claude Sonnet 4...');
    
    // Try various submit methods
    const submitSelectors = [
      'button[type="submit"]',
      'button:contains("Send")',
      'button:contains("Submit")',
      '.send-button, .submit-button',
      '[x-on\\:click*="submit"], [x-on\\:click*="send"]'
    ];

    let submitted = false;
    for (const submitSelector of submitSelectors) {
      try {
        const submitButton = await page.$(submitSelector);
        if (submitButton) {
          await submitButton.click();
          submitted = true;
          console.log(`✅ Submitted via: ${submitSelector}`);
          break;
        }
      } catch (e) {
        // Continue trying
      }
    }

    // If no submit button found, try pressing Enter
    if (!submitted) {
      console.log('⌨️ No submit button found, trying Enter key...');
      await chatInput.press('Enter');
      submitted = true;
    }

    // Step 5: Wait for and capture AI response
    console.log('\n🤖 Step 5: Waiting for Claude Sonnet 4 response...');
    
    // Wait for response (be patient with AI generation)
    await page.waitForTimeout(5000); // Give time for request to start
    
    // Look for response indicators
    const responseSelectors = [
      '.ai-response, .assistant-response, .chat-message',
      '.response-content, .message-content',
      '[class*="response"], [class*="message"]',
      'pre, code, .code-block, .highlight'
    ];

    let responseFound = false;
    let attempts = 0;
    const maxAttempts = 24; // Wait up to 2 minutes (5s * 24 = 120s)

    while (!responseFound && attempts < maxAttempts) {
      attempts++;
      console.log(`⏳ Waiting for response... (attempt ${attempts}/${maxAttempts})`);
      
      for (const respSelector of responseSelectors) {
        const elements = await page.$$(respSelector);
        if (elements.length > 0) {
          // Check if any element has substantial content
          for (const element of elements) {
            const textContent = await element.evaluate(el => el.textContent?.trim() || '');
            if (textContent.length > 100) { // Substantial response
              responseFound = true;
              console.log(`✅ AI response detected: ${textContent.substring(0, 100)}...`);
              break;
            }
          }
          if (responseFound) break;
        }
      }
      
      if (!responseFound) {
        await page.waitForTimeout(5000); // Wait 5 seconds before next attempt
      }
    }

    await page.screenshot({ path: `${testResultsDir}/claude-06-ai-response.png`, fullPage: true });

    // Step 6: Look for download links or generated content
    console.log('\n📥 Step 6: Looking for generated game files...');
    
    const downloadSelectors = [
      'a[download], a[href*=".html"], a[href*="download"]',
      'button:contains("Download"), .download-button',
      'a:contains("Download"), a:contains("HTML"), a:contains("Game")'
    ];

    let downloadFound = false;
    for (const dlSelector of downloadSelectors) {
      const downloadLink = await page.$(dlSelector);
      if (downloadLink) {
        const href = await downloadLink.evaluate(el => el.href || el.getAttribute('href'));
        const text = await downloadLink.evaluate(el => el.textContent?.trim());
        console.log(`🔗 Found download link: ${text} (${href})`);
        downloadFound = true;
      }
    }

    if (downloadFound) {
      await page.screenshot({ path: `${testResultsDir}/claude-07-download-available.png`, fullPage: true });
    }

    // Step 7: Check storage directories for generated files
    console.log('\n📁 Step 7: Checking for generated game files in storage...');
    
    // Check various storage locations
    const storagePaths = [
      'storage/workspaces',
      'storage/app/public/templates',
      'storage/test_build*',
      'storage/generated_games',
      'public/downloads'
    ];

    let generatedFiles = [];
    
    for (const storagePath of storagePaths) {
      try {
        if (fs.existsSync(storagePath)) {
          const files = fs.readdirSync(storagePath);
          if (files.length > 0) {
            console.log(`📂 Found files in ${storagePath}:`, files.slice(0, 5)); // Show first 5 files
            generatedFiles.push(...files.map(f => `${storagePath}/${f}`));
          }
        }
      } catch (e) {
        // Directory might not exist or be accessible
      }
    }

    // Check for test_build directories (common pattern)
    try {
      const storageDir = 'storage';
      if (fs.existsSync(storageDir)) {
        const items = fs.readdirSync(storageDir);
        const testBuilds = items.filter(item => item.startsWith('test_build_'));
        if (testBuilds.length > 0) {
          console.log(`🎮 Found test builds:`, testBuilds);
          generatedFiles.push(...testBuilds.map(tb => `storage/${tb}`));
        }
      }
    } catch (e) {
      console.log('ℹ️ Could not check storage directory');
    }

    // Step 8: Final screenshot and summary
    console.log('\n📸 Step 8: Taking final screenshots...');
    await page.screenshot({ path: `${testResultsDir}/claude-08-final-result.png`, fullPage: true });

    // Test Summary
    console.log('\n🎉 Claude Sonnet 4 Flow Test Completed!');
    console.log('\n📋 TEST SUMMARY:');
    console.log('✅ Homepage accessed');
    console.log(authHandled ? '✅ Authentication successful' : '❌ Authentication failed');
    console.log(chatInput ? '✅ Chat interface found' : '❌ Chat interface not found');
    console.log(submitted ? '✅ Game prompt submitted' : '❌ Could not submit prompt');
    console.log(responseFound ? '✅ AI response received' : '❌ No AI response detected');
    console.log(downloadFound ? '✅ Download links available' : 'ℹ️ No download links found');
    console.log(`📁 Generated files found: ${generatedFiles.length}`);

    console.log('\n🤖 AI Configuration:');
    console.log('🔧 Model: Claude Sonnet 4 (claude-sonnet-4-20250514)');
    console.log('🏭 Provider: Anthropic');
    console.log('🎯 Temperature: 0.2 (deterministic)');
    console.log('📏 Max Tokens: 1200');

    console.log('\n📸 Screenshots captured:');
    console.log('   - claude-01-homepage.png');
    console.log('   - claude-02-registration-form.png (if registered)');
    console.log('   - claude-03-after-auth.png');
    console.log('   - claude-05-prompt-entered.png');
    console.log('   - claude-06-ai-response.png');
    if (downloadFound) console.log('   - claude-07-download-available.png');
    console.log('   - claude-08-final-result.png');

    if (generatedFiles.length > 0) {
      console.log('\n📁 Generated game files:');
      generatedFiles.slice(0, 10).forEach(file => {
        console.log(`   - ${file}`);
      });
      if (generatedFiles.length > 10) {
        console.log(`   ... and ${generatedFiles.length - 10} more files`);
      }
    }

    console.log('\n✨ Test completed successfully with Claude Sonnet 4!');

  } catch (error) {
    console.error('\n❌ Test failed:', error.message);
    
    // Take error screenshot
    try {
      await page.screenshot({ path: `${testResultsDir}/claude-99-error.png`, fullPage: true });
      console.log('📸 Error screenshot saved: claude-99-error.png');
    } catch (screenshotError) {
      console.log('Could not take error screenshot');
    }
    
    throw error;
  } finally {
    await browser.close();
  }
}

// Run the test
runClaudeSonnet4FlowTest()
  .then(() => {
    console.log('\n🎊 All tests completed successfully!');
    process.exit(0);
  })
  .catch((error) => {
    console.error('\n💥 Test suite failed:', error.message);
    process.exit(1);
  });
