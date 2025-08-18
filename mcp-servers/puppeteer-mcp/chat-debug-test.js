import puppeteer from 'puppeteer';

async function chatDebugTest() {
  console.log('🔍 CHAT DEBUG TEST: Comprehensive Error Analysis');
  console.log('=' * 80);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  // Capture console logs and network errors
  const logs = { console: [], network: [], errors: [] };
  
  page.on('console', msg => {
    const type = msg.type();
    const text = msg.text();
    logs.console.push({ type, text, timestamp: new Date().toISOString() });
    console.log(`📝 Console [${type.toUpperCase()}]: ${text}`);
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
    email: `chat_debug_${Date.now()}@example.com`,
    password: 'ChatDebug123!',
    company: 'Chat Debug Studio'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);

    // STEP 1: Quick registration
    console.log('\n📝 STEP 1: Quick user registration...');
    await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    
    // Wait for dynamic form loading
    await page.waitForTimeout(3000);
    
    // Find and fill email
    const emailField = await page.$('input[type="email"]');
    if (emailField) {
      await emailField.type(testUser.email);
      console.log('✅ Email filled');
    }

    // Find and fill passwords
    const passwordFields = await page.$$('input[type="password"]');
    if (passwordFields.length >= 2) {
      await passwordFields[0].type(testUser.password);
      await passwordFields[1].type(testUser.password);
      console.log('✅ Passwords filled');
    }

    // Submit registration
    const submitBtn = await page.$('button[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      console.log('✅ Registration submitted');
      
      // Wait for registration processing
      await page.waitForTimeout(5000);
    }

    await page.screenshot({ path: 'test-results/chat-debug-01-registration.png', fullPage: true });

    // STEP 2: Navigate to company area
    console.log('\n🏢 STEP 2: Accessing company area...');
    
    let currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);
    
    if (!currentUrl.includes('/company')) {
      await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
      await page.waitForTimeout(3000);
    }

    // Handle company setup if needed
    const companyField = await page.$('input[name*="company"], input[id*="company"]');
    if (companyField) {
      console.log('🏢 Company setup form detected');
      await companyField.type(testUser.company);
      
      const companySubmit = await page.$('button[type="submit"]');
      if (companySubmit) {
        await companySubmit.click();
        await page.waitForTimeout(5000);
        console.log('✅ Company setup completed');
      }
    }

    await page.screenshot({ path: 'test-results/chat-debug-02-company.png', fullPage: true });

    // STEP 3: Find and analyze chat interface
    console.log('\n💬 STEP 3: Chat interface analysis...');

    currentUrl = page.url();
    console.log(`📍 Company URL: ${currentUrl}`);

    // Get all navigation and buttons
    const navigation = await page.evaluate(() => {
      const elements = Array.from(document.querySelectorAll('a, button, [role="button"]'));
      return elements.map(el => ({
        text: el.textContent?.trim(),
        href: el.href || null,
        className: el.className,
        id: el.id,
        visible: el.offsetParent !== null
      })).filter(el => el.text && el.visible);
    });

    console.log(`🔗 Found ${navigation.length} interactive elements:`);
    navigation.slice(0, 15).forEach((nav, i) => {
      console.log(`   ${i + 1}. "${nav.text}" ${nav.href ? `→ ${nav.href}` : ''}`);
    });

    // Look for chat-related navigation
    const chatNavigation = navigation.filter(nav =>
      nav.text.toLowerCase().includes('chat') ||
      nav.text.toLowerCase().includes('ai') ||
      nav.text.toLowerCase().includes('assistant') ||
      nav.href?.includes('chat') ||
      nav.href?.includes('ai')
    );

    console.log(`\n🤖 Chat-related navigation (${chatNavigation.length}):`);
    chatNavigation.forEach((nav, i) => {
      console.log(`   ${i + 1}. "${nav.text}" → ${nav.href || 'button'}`);
    });

    // STEP 4: Access chat interface
    if (chatNavigation.length > 0) {
      console.log('\n🔗 STEP 4: Accessing chat interface...');
      
      const firstChatNav = chatNavigation[0];
      if (firstChatNav.href) {
        await page.goto(firstChatNav.href, { waitUntil: 'networkidle2' });
      } else {
        // Click button
        await page.click(`text="${firstChatNav.text}"`);
        await page.waitForTimeout(3000);
      }
      
      console.log(`✅ Accessed: "${firstChatNav.text}"`);
    } else {
      console.log('❌ No chat navigation found');
      
      // Try direct URLs
      const chatUrls = [
        'http://surreal-pilot.local/company/chat',
        'http://surreal-pilot.local/company/ai',
        'http://surreal-pilot.local/company/assistant'
      ];
      
      for (const url of chatUrls) {
        try {
          console.log(`🔗 Trying: ${url}`);
          await page.goto(url, { waitUntil: 'networkidle2' });
          await page.waitForTimeout(2000);
          
          if (!page.url().includes('login') && !page.url().includes('error')) {
            console.log(`✅ Success: ${url}`);
            break;
          }
        } catch (e) {
          console.log(`❌ Failed: ${url}`);
        }
      }
    }

    await page.screenshot({ path: 'test-results/chat-debug-03-chat-interface.png', fullPage: true });

    // STEP 5: Analyze chat interface
    console.log('\n🔍 STEP 5: Chat interface component analysis...');

    const chatInterface = await page.evaluate(() => {
      // Get all form elements
      const inputs = Array.from(document.querySelectorAll('input, textarea, select'));
      const buttons = Array.from(document.querySelectorAll('button, [role="button"]'));
      const dropdowns = Array.from(document.querySelectorAll('select, [role="combobox"], [role="listbox"]'));
      
      return {
        inputs: inputs.map(el => ({
          type: el.type || el.tagName.toLowerCase(),
          placeholder: el.placeholder,
          name: el.name,
          id: el.id,
          value: el.value,
          visible: el.offsetParent !== null
        })).filter(el => el.visible),
        
        buttons: buttons.map(el => ({
          text: el.textContent?.trim(),
          type: el.type,
          className: el.className,
          disabled: el.disabled,
          visible: el.offsetParent !== null
        })).filter(el => el.visible && el.text),
        
        dropdowns: dropdowns.map(el => ({
          text: el.textContent?.trim(),
          name: el.name,
          id: el.id,
          options: Array.from(el.options || el.querySelectorAll('option')).map(opt => opt.textContent?.trim()),
          visible: el.offsetParent !== null
        })).filter(el => el.visible)
      };
    });

    console.log(`📝 Input fields (${chatInterface.inputs.length}):`);
    chatInterface.inputs.forEach((input, i) => {
      console.log(`   ${i + 1}. ${input.type}: "${input.placeholder}" (${input.name || input.id})`);
    });

    console.log(`\n🔘 Buttons (${chatInterface.buttons.length}):`);
    chatInterface.buttons.forEach((btn, i) => {
      console.log(`   ${i + 1}. "${btn.text}" (type: ${btn.type}, disabled: ${btn.disabled})`);
    });

    console.log(`\n📋 Dropdowns/Selects (${chatInterface.dropdowns.length}):`);
    chatInterface.dropdowns.forEach((dropdown, i) => {
      console.log(`   ${i + 1}. ${dropdown.name || dropdown.id}: ${dropdown.options.length} options`);
      if (dropdown.options.length <= 10) {
        dropdown.options.forEach(opt => console.log(`      - "${opt}"`));
      }
    });

    // STEP 6: Test provider selection (current UX issue)
    console.log('\n⚙️ STEP 6: Provider selection analysis...');

    const providerElements = chatInterface.dropdowns.filter(dropdown =>
      dropdown.options.some(opt => 
        opt.toLowerCase().includes('openai') ||
        opt.toLowerCase().includes('claude') ||
        opt.toLowerCase().includes('anthropic') ||
        opt.toLowerCase().includes('gemini')
      )
    );

    if (providerElements.length > 0) {
      console.log(`🔧 Provider selection found: ${providerElements.length} dropdown(s)`);
      providerElements.forEach((provider, i) => {
        console.log(`   ${i + 1}. Available providers: ${provider.options.join(', ')}`);
      });
      console.log('❌ UX ISSUE: Provider selection exposed to user in chat interface');
    } else {
      console.log('✅ No provider selection in chat interface');
    }

    // STEP 7: Workspace selection analysis
    console.log('\n🗂️ STEP 7: Workspace selection analysis...');

    const workspaceElements = chatInterface.dropdowns.filter(dropdown =>
      dropdown.options.some(opt => 
        opt.toLowerCase().includes('workspace') ||
        opt.toLowerCase().includes('project') ||
        opt.toLowerCase().includes('playcanvas') ||
        opt.toLowerCase().includes('unreal')
      )
    );

    if (workspaceElements.length > 0) {
      console.log(`📁 Workspace selection found: ${workspaceElements.length} dropdown(s)`);
      workspaceElements.forEach((workspace, i) => {
        console.log(`   ${i + 1}. Available workspaces: ${workspace.options.join(', ')}`);
      });
    } else {
      console.log('❌ UX ISSUE: No workspace selection found in chat interface');
    }

    // STEP 8: Test basic chat functionality
    console.log('\n💬 STEP 8: Basic chat functionality test...');

    const chatInput = chatInterface.inputs.find(input =>
      input.type === 'textarea' ||
      input.placeholder?.toLowerCase().includes('message') ||
      input.placeholder?.toLowerCase().includes('ask') ||
      input.placeholder?.toLowerCase().includes('prompt')
    );

    if (chatInput) {
      console.log(`✅ Chat input found: ${chatInput.type} "${chatInput.placeholder}"`);
      
      const selector = chatInput.id ? `#${chatInput.id}` : 
                      chatInput.name ? `[name="${chatInput.name}"]` : 
                      'textarea';

      try {
        await page.click(selector);
        await page.type(selector, 'Hello Claude Sonnet 4! Can you help me create a simple PlayCanvas game?');
        
        console.log('✅ Test message entered');
        
        // Try to submit
        const sendButton = chatInterface.buttons.find(btn =>
          btn.text.toLowerCase().includes('send') ||
          btn.text.toLowerCase().includes('submit')
        );

        if (sendButton) {
          await page.click(`text="${sendButton.text}"`);
          console.log(`✅ Message sent via: "${sendButton.text}"`);
          
          // Wait for response
          await page.waitForTimeout(5000);
          console.log('⏳ Waiting for AI response...');
          
        } else {
          console.log('❌ No send button found');
        }
        
      } catch (e) {
        console.log(`❌ Chat input failed: ${e.message}`);
      }
    } else {
      console.log('❌ No chat input field found');
    }

    await page.screenshot({ path: 'test-results/chat-debug-04-chat-test.png', fullPage: true });

    // STEP 9: Final analysis
    console.log('\n📊 STEP 9: UX Analysis Summary...');

    const uxIssues = [];
    const uxStrengths = [];

    // Analyze current UX
    if (providerElements.length > 0) {
      uxIssues.push('Provider selection exposed in chat (should be in settings)');
    }

    if (workspaceElements.length === 0) {
      uxIssues.push('No workspace selection in chat interface');
    }

    if (!chatInput) {
      uxIssues.push('Chat input field not clearly identified');
    }

    if (chatInterface.buttons.length > 10) {
      uxIssues.push(`Too many buttons (${chatInterface.buttons.length}) - interface cluttered`);
    }

    if (chatInterface.dropdowns.length > 3) {
      uxIssues.push(`Too many dropdowns (${chatInterface.dropdowns.length}) - confusing UX`);
    }

    // Log final results
    console.log('\n📋 CHAT INTERFACE ANALYSIS RESULTS:');
    console.log('=' * 50);
    console.log(`   💬 Chat input: ${chatInput ? '✅ Found' : '❌ Missing'}`);
    console.log(`   📁 Workspace selection: ${workspaceElements.length > 0 ? '✅ Found' : '❌ Missing'}`);
    console.log(`   ⚙️ Provider selection: ${providerElements.length > 0 ? '❌ Exposed' : '✅ Hidden'}`);
    console.log(`   🔘 Total buttons: ${chatInterface.buttons.length}`);
    console.log(`   📋 Total dropdowns: ${chatInterface.dropdowns.length}`);
    console.log(`   📝 Console errors: ${logs.console.filter(l => l.type === 'error').length}`);
    console.log(`   🌐 Network errors: ${logs.network.length}`);
    console.log(`   💥 Page errors: ${logs.errors.length}`);

    console.log('\n❌ UX ISSUES IDENTIFIED:');
    uxIssues.forEach((issue, i) => {
      console.log(`   ${i + 1}. ${issue}`);
    });

    await page.screenshot({ path: 'test-results/chat-debug-05-final.png', fullPage: true });

    return {
      success: chatInput !== null,
      uxIssues,
      logs,
      interface: chatInterface,
      hasWorkspaceSelection: workspaceElements.length > 0,
      hasProviderSelection: providerElements.length > 0,
      testUser
    };

  } catch (error) {
    console.error('\n❌ CHAT DEBUG TEST FAILED:', error.message);
    await page.screenshot({ path: 'test-results/chat-debug-99-error.png', fullPage: true });
    return { success: false, error: error.message, logs };
  } finally {
    await browser.close();
  }
}

chatDebugTest()
  .then(result => {
    console.log('\n🔍 CHAT DEBUG COMPLETE');
    
    if (result.logs) {
      console.log('\n📝 ERROR SUMMARY:');
      console.log(`   Console errors: ${result.logs.console.filter(l => l.type === 'error').length}`);
      console.log(`   Network errors: ${result.logs.network.length}`);
      console.log(`   Page errors: ${result.logs.errors.length}`);
    }
    
    if (!result.success) {
      console.log('💥 CRITICAL: Chat functionality not working');
    }
  })
  .catch(console.error);
