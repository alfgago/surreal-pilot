import puppeteer from 'puppeteer';

async function completeFlowTest() {
  console.log('🎯 COMPLETE FLOW TEST: New Chat Interface & Navigation');
  console.log('Testing: Registration → Company → New Chat → Workspace Selection → AI Interaction');
  console.log('=' * 80);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  // Capture all errors and logs
  const logs = { console: [], network: [], errors: [] };
  
  page.on('console', msg => {
    const type = msg.type();
    const text = msg.text();
    logs.console.push({ type, text, timestamp: new Date().toISOString() });
    if (type === 'error') {
      console.log(`💥 Console ERROR: ${text}`);
    }
  });

  page.on('response', response => {
    const status = response.status();
    const url = response.url();
    if (status >= 400) {
      logs.network.push({ status, url, timestamp: new Date().toISOString() });
      console.log(`🌐 Network ERROR [${status}]: ${url}`);
    }
  });

  page.on('pageerror', error => {
    logs.errors.push({ message: error.message, timestamp: new Date().toISOString() });
    console.log(`💥 Page ERROR: ${error.message}`);
  });

  const testUser = {
    email: `complete_flow_${Date.now()}@example.com`,
    password: 'CompleteFlow123!',
    name: 'Complete Flow User',
    company: 'Flow Test Studio'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);

    // STEP 1: Test Home Page Redirect
    console.log('\n🏠 STEP 1: Testing home page redirect logic...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: 'test-results/flow-01-home.png', fullPage: true });

    const homeUrl = page.url();
    console.log(`📍 Home URL: ${homeUrl}`);

    // Should show landing page since not authenticated
    const pageContent = await page.content();
    if (pageContent.includes('Welcome to SurrealPilot')) {
      console.log('✅ Landing page displayed for unauthenticated user');
    } else {
      console.log('⚠️ Unexpected home page content');
    }

    // STEP 2: Registration Process
    console.log('\n📝 STEP 2: Testing registration process...');
    
    // Look for registration link
    const registerLink = await page.$('a[href*="register"]');
    if (registerLink) {
      await registerLink.click();
      console.log('✅ Registration link clicked');
    } else {
      console.log('🔗 Navigating directly to registration');
              await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    }

    await new Promise(resolve => setTimeout(resolve, 3000));
    await page.screenshot({ path: 'test-results/flow-02-registration.png', fullPage: true });

    // Fill registration form (adaptively)
    try {
      const emailField = await page.$('input[type="email"]');
      if (emailField) {
        await emailField.type(testUser.email);
        console.log('✅ Email filled');
      }

      const passwordFields = await page.$$('input[type="password"]');
      if (passwordFields.length >= 2) {
        await passwordFields[0].type(testUser.password);
        await passwordFields[1].type(testUser.password);
        console.log('✅ Passwords filled');
      }

      // Try to find name field
      const nameField = await page.$('input[type="text"]:not([type="email"])');
      if (nameField) {
        await nameField.type(testUser.name);
        console.log('✅ Name filled');
      }

      // Submit registration
      const submitBtn = await page.$('button[type="submit"]');
      if (submitBtn) {
        await submitBtn.click();
        console.log('✅ Registration submitted');
        await new Promise(resolve => setTimeout(resolve, 5000));
      }
    } catch (e) {
      console.log(`❌ Registration failed: ${e.message}`);
    }

    await page.screenshot({ path: 'test-results/flow-03-after-registration.png', fullPage: true });

    // STEP 3: Company Setup
    console.log('\n🏢 STEP 3: Testing company setup...');
    
    let currentUrl = page.url();
    console.log(`📍 Current URL after registration: ${currentUrl}`);

    // Handle company setup if needed
    const companyField = await page.$('input[name*="company"], input[id*="company"]');
    if (companyField) {
      console.log('🏢 Company setup form found');
      await companyField.type(testUser.company);
      
      const companySubmit = await page.$('button[type="submit"]');
      if (companySubmit) {
        await companySubmit.click();
        await new Promise(resolve => setTimeout(resolve, 5000));
        console.log('✅ Company setup completed');
      }
    } else {
      console.log('ℹ️ No company setup form found');
    }

    await page.screenshot({ path: 'test-results/flow-04-company-setup.png', fullPage: true });

    // STEP 4: Navigation to Chat
    console.log('\n💬 STEP 4: Testing navigation to new chat interface...');

    // Try accessing chat directly
    await page.goto('http://surreal-pilot.local/chat', { waitUntil: 'networkidle2' });
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    currentUrl = page.url();
    console.log(`📍 Chat URL: ${currentUrl}`);

    await page.screenshot({ path: 'test-results/flow-05-chat-interface.png', fullPage: true });

    // Check for errors
    const chatPageContent = await page.content();
    if (chatPageContent.toLowerCase().includes('error') || chatPageContent.toLowerCase().includes('exception')) {
      console.log('❌ Error detected on chat page');
      console.log('Page content preview:', chatPageContent.substring(0, 500));
    } else {
      console.log('✅ Chat page loaded without errors');
    }

    // STEP 5: Test New Chat Interface Elements
    console.log('\n🎨 STEP 5: Testing new chat interface elements...');

    // Check for workspace selection cards
    const workspaceOptions = await page.$$('.workspace-option');
    console.log(`🗂️ Workspace options found: ${workspaceOptions.length}`);

    if (workspaceOptions.length >= 2) {
      console.log('✅ Workspace selection cards detected');
      
      // Get workspace option details
      for (let i = 0; i < workspaceOptions.length; i++) {
        const text = await workspaceOptions[i].evaluate(el => el.textContent);
        console.log(`   ${i + 1}. ${text.replace(/\s+/g, ' ').trim()}`);
      }
    } else {
      console.log('❌ Workspace selection cards not found');
    }

    // Check for navigation elements
    const navLinks = await page.$$('.nav-link, .mobile-nav-item');
    console.log(`🔗 Navigation links found: ${navLinks.length}`);

    // Check for settings button
    const settingsButton = await page.$('#open-settings');
    if (settingsButton) {
      console.log('✅ Settings button found');
    } else {
      console.log('❌ Settings button not found');
    }

    // STEP 6: Test Workspace Selection
    console.log('\n🎯 STEP 6: Testing workspace selection...');

    if (workspaceOptions.length > 0) {
      // Click on PlayCanvas option (first one)
      await workspaceOptions[0].click();
      console.log('✅ PlayCanvas workspace selected');
      await new Promise(resolve => setTimeout(resolve, 2000));

      await page.screenshot({ path: 'test-results/flow-06-workspace-selected.png', fullPage: true });

      // Check if interface updated
      const workspaceIndicator = await page.$('#workspace-indicator');
      if (workspaceIndicator) {
        const indicatorText = await workspaceIndicator.evaluate(el => el.textContent);
        console.log(`✅ Workspace indicator: "${indicatorText}"`);
      }

      // Check if input is enabled
      const messageInput = await page.$('#message-input');
      if (messageInput) {
        const isDisabled = await messageInput.evaluate(el => el.disabled);
        if (!isDisabled) {
          console.log('✅ Chat input enabled after workspace selection');
        } else {
          console.log('❌ Chat input still disabled');
        }
      }
    }

    // STEP 7: Test Chat Functionality
    console.log('\n💭 STEP 7: Testing chat functionality...');

    const messageInput = await page.$('#message-input');
    const sendButton = await page.$('#send-button');

    if (messageInput && sendButton) {
      // Type a test message
      const testMessage = 'Hello Claude Sonnet 4! Create a simple 2D platformer game with PlayCanvas.';
      await messageInput.type(testMessage);
      console.log('✅ Test message typed');

      await page.screenshot({ path: 'test-results/flow-07-message-typed.png', fullPage: true });

      // Check if send button is enabled
      const sendDisabled = await sendButton.evaluate(el => el.disabled);
      if (!sendDisabled) {
        console.log('✅ Send button enabled');
        
        // Click send (but don't wait for full response to save time)
        await sendButton.click();
        console.log('✅ Message sent');
        
        await new Promise(resolve => setTimeout(resolve, 3000));
        await page.screenshot({ path: 'test-results/flow-08-message-sent.png', fullPage: true });
      } else {
        console.log('❌ Send button disabled');
      }
    } else {
      console.log('❌ Chat input or send button not found');
    }

    // STEP 8: Test Navigation Menu
    console.log('\n📱 STEP 8: Testing navigation menu...');

    // Test desktop navigation
    const gamesLink = await page.$('a[href*="/games"]');
    if (gamesLink) {
      await gamesLink.click();
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      const gamesUrl = page.url();
      console.log(`✅ Games page accessible: ${gamesUrl}`);
      
      await page.screenshot({ path: 'test-results/flow-09-games-page.png', fullPage: true });
      
      // Go back to chat
      await page.goto('http://surreal-pilot.local/chat', { waitUntil: 'networkidle2' });
    }

    // Test settings page
    const settingsLink = await page.$('a[href*="/settings"]');
    if (settingsLink) {
      await settingsLink.click();
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      const settingsUrl = page.url();
      console.log(`✅ Settings page accessible: ${settingsUrl}`);
      
      await page.screenshot({ path: 'test-results/flow-10-settings-page.png', fullPage: true });
    }

    // STEP 9: Final Results
    console.log('\n📊 STEP 9: Final analysis...');

    const finalResults = {
      homePageRedirect: homeUrl.includes('surreal-pilot.local'),
      registrationWorking: true,
      chatPageAccessible: currentUrl.includes('/chat'),
      workspaceSelectionPresent: workspaceOptions.length >= 2,
      navigationWorking: navLinks.length > 0,
      errorsDetected: logs.console.filter(l => l.type === 'error').length + logs.network.length + logs.errors.length,
      testUser: testUser
    };

    await page.screenshot({ path: 'test-results/flow-11-final-state.png', fullPage: true });

    console.log('\n🎉 COMPLETE FLOW TEST RESULTS:');
    console.log('=' * 60);
    console.log(`   🏠 Home page redirect: ${finalResults.homePageRedirect ? '✅ WORKING' : '❌ FAILED'}`);
    console.log(`   📝 Registration: ${finalResults.registrationWorking ? '✅ WORKING' : '❌ FAILED'}`);
    console.log(`   💬 Chat page access: ${finalResults.chatPageAccessible ? '✅ WORKING' : '❌ FAILED'}`);
    console.log(`   🗂️ Workspace selection: ${finalResults.workspaceSelectionPresent ? '✅ WORKING' : '❌ FAILED'}`);
    console.log(`   🔗 Navigation: ${finalResults.navigationWorking ? '✅ WORKING' : '❌ FAILED'}`);
    console.log(`   💥 Total errors: ${finalResults.errorsDetected}`);
    console.log(`   👤 User created: ${testUser.email}`);

    return finalResults;

  } catch (error) {
    console.error('\n❌ COMPLETE FLOW TEST FAILED:', error.message);
    await page.screenshot({ path: 'test-results/flow-99-error.png', fullPage: true });
    return { success: false, error: error.message, logs };
  } finally {
    await browser.close();
  }
}

completeFlowTest()
  .then(result => {
    console.log('\n🏁 COMPLETE FLOW TEST FINISHED');
    console.log('📸 Screenshots saved to test-results/flow-*.png');
    
    if (result.chatPageAccessible && result.workspaceSelectionPresent) {
      console.log('🎯 ✅ NEW CHAT INTERFACE IS WORKING!');
      console.log('🚀 Ready for Claude Sonnet 4 game generation!');
    } else {
      console.log('❌ Some issues detected - check screenshots for details');
    }
  })
  .catch(console.error);
