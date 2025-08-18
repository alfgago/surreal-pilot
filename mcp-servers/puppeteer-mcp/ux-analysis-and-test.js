import puppeteer from 'puppeteer';

async function uxAnalysisAndTest() {
  console.log('🔍 UX ANALYSIS & CHAT FUNCTIONALITY TEST');
  console.log('📋 Analyzing current chat interface and providing UX recommendations');
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

  try {
    console.log('\n🏠 STEP 1: Homepage and initial navigation analysis...');
    
    // Start with the homepage
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    const currentUrl = page.url();
    console.log(`📍 Homepage redirects to: ${currentUrl}`);
    
    await page.screenshot({ path: 'test-results/ux-01-homepage.png', fullPage: true });

    // Check for registration/login navigation
    const navigationElements = await page.evaluate(() => {
      const elements = Array.from(document.querySelectorAll('a, button, [role="button"]'));
      return elements.map(el => ({
        text: el.textContent?.trim(),
        href: el.href || null,
        visible: el.offsetParent !== null
      })).filter(el => el.text && el.visible);
    });

    console.log(`\n🔗 Navigation elements found: ${navigationElements.length}`);
    
    const authNavigation = navigationElements.filter(nav =>
      nav.text.toLowerCase().includes('login') ||
      nav.text.toLowerCase().includes('register') ||
      nav.text.toLowerCase().includes('sign')
    );

    console.log('🔐 Authentication navigation:');
    authNavigation.forEach((nav, i) => {
      console.log(`   ${i + 1}. "${nav.text}" → ${nav.href || 'button'}`);
    });

    // STEP 2: Try to access the chat interface directly (if authenticated)
    console.log('\n💬 STEP 2: Direct chat interface access...');

    const chatUrls = [
      'http://surreal-pilot.local/company',
      'http://surreal-pilot.local/admin',
      'http://surreal-pilot.local/chat',
      'http://surreal-pilot.local/ai'
    ];

    let chatInterfaceFound = false;
    let chatInterfaceUrl = null;

    for (const url of chatUrls) {
      try {
        console.log(`🔗 Trying: ${url}`);
        await page.goto(url, { waitUntil: 'networkidle2' });
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        const finalUrl = page.url();
        const pageContent = await page.content();
        
        console.log(`   📍 Redirected to: ${finalUrl}`);
        
        // Check if we hit a login page
        if (finalUrl.includes('login')) {
          console.log('   🔐 Redirected to login - authentication required');
          continue;
        }
        
        // Check for errors
        if (pageContent.toLowerCase().includes('exception') || 
            pageContent.toLowerCase().includes('error') ||
            pageContent.toLowerCase().includes('500')) {
          console.log('   ❌ Error page detected');
          continue;
        }
        
        // Check for chat-like interface elements
        const hasTextInput = await page.$('textarea, input[type="text"]');
        const hasButtons = await page.$$('button');
        
        if (hasTextInput && hasButtons.length > 0) {
          console.log(`   ✅ Potential chat interface found!`);
          chatInterfaceFound = true;
          chatInterfaceUrl = finalUrl;
          break;
        }
        
      } catch (e) {
        console.log(`   ❌ Failed: ${e.message}`);
      }
    }

    if (chatInterfaceFound) {
      await page.screenshot({ path: 'test-results/ux-02-chat-found.png', fullPage: true });
      
      // STEP 3: Analyze current chat interface
      console.log('\n🔍 STEP 3: Current chat interface analysis...');
      
      const interfaceAnalysis = await page.evaluate(() => {
        // Get all interactive elements
        const inputs = Array.from(document.querySelectorAll('input, textarea, select'));
        const buttons = Array.from(document.querySelectorAll('button, [role="button"]'));
        const dropdowns = Array.from(document.querySelectorAll('select, [role="combobox"]'));
        const labels = Array.from(document.querySelectorAll('label'));
        
        return {
          inputs: inputs.map(el => ({
            type: el.type || el.tagName.toLowerCase(),
            placeholder: el.placeholder,
            name: el.name,
            id: el.id,
            value: el.value,
            visible: el.offsetParent !== null,
            labels: Array.from(el.labels || []).map(l => l.textContent?.trim())
          })).filter(el => el.visible),
          
          buttons: buttons.map(el => ({
            text: el.textContent?.trim(),
            type: el.type,
            disabled: el.disabled,
            visible: el.offsetParent !== null,
            className: el.className
          })).filter(el => el.visible && el.text),
          
          dropdowns: dropdowns.map(el => ({
            text: el.textContent?.trim(),
            name: el.name,
            id: el.id,
            visible: el.offsetParent !== null,
            options: Array.from(el.options || []).map(opt => opt.textContent?.trim())
          })).filter(el => el.visible),
          
          labels: labels.map(el => el.textContent?.trim()).filter(t => t)
        };
      });

      console.log(`\n📊 CURRENT INTERFACE ANALYSIS:`);
      console.log(`   📝 Input fields: ${interfaceAnalysis.inputs.length}`);
      console.log(`   🔘 Buttons: ${interfaceAnalysis.buttons.length}`);
      console.log(`   📋 Dropdowns: ${interfaceAnalysis.dropdowns.length}`);
      console.log(`   🏷️ Labels: ${interfaceAnalysis.labels.length}`);

      // Detailed analysis
      console.log('\n📝 INPUT FIELDS:');
      interfaceAnalysis.inputs.forEach((input, i) => {
        console.log(`   ${i + 1}. ${input.type}: "${input.placeholder || input.labels.join(', ')}" (${input.name || input.id})`);
      });

      console.log('\n🔘 BUTTONS:');
      interfaceAnalysis.buttons.forEach((btn, i) => {
        console.log(`   ${i + 1}. "${btn.text}" (${btn.type}, disabled: ${btn.disabled})`);
      });

      console.log('\n📋 DROPDOWNS:');
      interfaceAnalysis.dropdowns.forEach((dropdown, i) => {
        console.log(`   ${i + 1}. "${dropdown.name || dropdown.id}": [${dropdown.options.slice(0, 5).join(', ')}${dropdown.options.length > 5 ? '...' : ''}]`);
      });

      // UX Issues Detection
      const uxIssues = [];
      const uxSuggestions = [];

      // Check for provider selection in chat
      const providerDropdowns = interfaceAnalysis.dropdowns.filter(d =>
        d.options.some(opt => 
          opt.toLowerCase().includes('openai') ||
          opt.toLowerCase().includes('claude') ||
          opt.toLowerCase().includes('anthropic') ||
          opt.toLowerCase().includes('gemini')
        )
      );

      if (providerDropdowns.length > 0) {
        uxIssues.push('AI provider selection exposed in chat interface');
        uxSuggestions.push('Move AI provider selection to settings/preferences page');
      }

      // Check for workspace selection
      const workspaceDropdowns = interfaceAnalysis.dropdowns.filter(d =>
        d.options.some(opt => 
          opt.toLowerCase().includes('workspace') ||
          opt.toLowerCase().includes('project') ||
          opt.toLowerCase().includes('playcanvas') ||
          opt.toLowerCase().includes('unreal')
        ) || 
        d.name?.toLowerCase().includes('workspace') ||
        d.id?.toLowerCase().includes('workspace')
      );

      if (workspaceDropdowns.length === 0) {
        uxIssues.push('No workspace selection in chat interface');
        uxSuggestions.push('Add prominent workspace selection at top of chat interface');
      } else {
        console.log(`✅ Workspace selection found: ${workspaceDropdowns.length} dropdown(s)`);
      }

      // Check for too many options
      if (interfaceAnalysis.dropdowns.length > 3) {
        uxIssues.push(`Too many dropdowns (${interfaceAnalysis.dropdowns.length}) - interface cluttered`);
        uxSuggestions.push('Consolidate or move advanced options to settings');
      }

      if (interfaceAnalysis.buttons.length > 10) {
        uxIssues.push(`Too many buttons (${interfaceAnalysis.buttons.length}) - interface overwhelming`);
        uxSuggestions.push('Simplify to essential actions: Send, Clear, Settings');
      }

      // Chat input assessment
      const chatInputs = interfaceAnalysis.inputs.filter(i =>
        i.type === 'textarea' ||
        i.placeholder?.toLowerCase().includes('message') ||
        i.placeholder?.toLowerCase().includes('ask') ||
        i.placeholder?.toLowerCase().includes('prompt')
      );

      if (chatInputs.length === 0) {
        uxIssues.push('No clear chat input field identified');
        uxSuggestions.push('Add prominent textarea with placeholder like "Ask Claude to create a game..."');
      }

      await page.screenshot({ path: 'test-results/ux-03-interface-analyzed.png', fullPage: true });

      // STEP 4: UX Recommendations
      console.log('\n🎨 STEP 4: UX REDESIGN RECOMMENDATIONS');
      console.log('=' * 60);

      console.log('\n❌ CURRENT UX ISSUES:');
      uxIssues.forEach((issue, i) => {
        console.log(`   ${i + 1}. ${issue}`);
      });

      console.log('\n💡 RECOMMENDED IMPROVEMENTS:');
      uxSuggestions.forEach((suggestion, i) => {
        console.log(`   ${i + 1}. ${suggestion}`);
      });

    } else {
      console.log('\n❌ No accessible chat interface found');
    }

    // FINAL UX RECOMMENDATIONS
    console.log('\n\n🎯 COMPREHENSIVE UX REDESIGN PROPOSAL');
    console.log('=' * 80);

    const uxProposal = {
      overview: 'Simplified chat interface with workspace-first approach',
      mainIssues: [
        'Provider selection should not be in chat interface',
        'Workspace selection must be prominent and first',
        'Too many configuration options in main chat view',
        'Interface lacks clear visual hierarchy'
      ],
      proposedFlow: [
        '1. User selects workspace type (PlayCanvas/Unreal) prominently at top',
        '2. Simple, clean chat input with clear placeholder',
        '3. Minimal action buttons: Send, Clear, New Chat',
        '4. All technical settings moved to separate Settings page',
        '5. Chat history sidebar (collapsible)',
        '6. Workspace context always visible'
      ],
      technicalChanges: [
        'Move AI provider selection to user/company settings',
        'Add workspace selector as primary interface element',
        'Simplify chat UI to focus on conversation',
        'Implement clean, modern design with proper spacing',
        'Add contextual help and workspace-specific prompts'
      ]
    };

    console.log('\n📋 MAIN ISSUES TO FIX:');
    uxProposal.mainIssues.forEach((issue, i) => {
      console.log(`   ${i + 1}. ${issue}`);
    });

    console.log('\n🔄 PROPOSED USER FLOW:');
    uxProposal.proposedFlow.forEach((step, i) => {
      console.log(`   ${step}`);
    });

    console.log('\n🔧 TECHNICAL CHANGES NEEDED:');
    uxProposal.technicalChanges.forEach((change, i) => {
      console.log(`   ${i + 1}. ${change}`);
    });

    await page.screenshot({ path: 'test-results/ux-04-final-analysis.png', fullPage: true });

    return {
      success: chatInterfaceFound,
      chatUrl: chatInterfaceUrl,
      logs,
      uxIssues,
      uxProposal,
      interfaceFound: chatInterfaceFound
    };

  } catch (error) {
    console.error('\n❌ UX ANALYSIS FAILED:', error.message);
    await page.screenshot({ path: 'test-results/ux-99-error.png', fullPage: true });
    return { success: false, error: error.message, logs };
  } finally {
    await browser.close();
  }
}

uxAnalysisAndTest()
  .then(result => {
    console.log('\n\n🏁 UX ANALYSIS COMPLETE');
    console.log('=' * 50);
    
    if (result.logs) {
      console.log(`📊 ERRORS DETECTED:`);
      console.log(`   💥 Console errors: ${result.logs.console.filter(l => l.type === 'error').length}`);
      console.log(`   🌐 Network errors: ${result.logs.network.length}`);
      console.log(`   🚨 Page errors: ${result.logs.errors.length}`);
    }
    
    if (result.success) {
      console.log(`✅ Chat interface accessible at: ${result.chatUrl}`);
      console.log('🎨 UX recommendations generated');
    } else {
      console.log('❌ Chat interface not accessible');
    }

    console.log('\n📸 Screenshots saved to test-results/ux-*.png');
    console.log('🔍 Review screenshots for visual UX analysis');
  })
  .catch(console.error);
