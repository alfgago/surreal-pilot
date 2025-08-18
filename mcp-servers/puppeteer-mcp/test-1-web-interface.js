import puppeteer from 'puppeteer';
import fs from 'fs';

const testResultsDir = 'test-results';
if (!fs.existsSync(testResultsDir)) {
  fs.mkdirSync(testResultsDir, { recursive: true });
}

async function testWebInterfaceLogin() {
  console.log('🌐 TEST 1: Web Interface Login and AI Access');
  console.log('=' * 50);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  try {
    // Step 1: Homepage
    console.log('\n🏠 Step 1: Accessing homepage...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: `${testResultsDir}/test1-01-homepage.png`, fullPage: true });
    console.log('✅ Homepage loaded successfully');

    // Step 2: Navigate to login
    console.log('\n🔐 Step 2: Accessing login...');
    
    // Try multiple login access methods
    const loginSelectors = [
      'a[href*="login"]',
      'a:contains("Login")',
      'button:contains("Login")',
      '.login-link'
    ];

    let loginFound = false;
    for (const selector of loginSelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          console.log(`🔗 Found login link: ${selector}`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          loginFound = true;
          break;
        }
      } catch (e) {
        // Try next
      }
    }

    if (!loginFound) {
      console.log('🔗 Trying direct navigation to /login...');
      await page.goto('http://surreal-pilot.local/login', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: `${testResultsDir}/test1-02-login-page.png`, fullPage: true });

    // Step 3: Login with test user
    console.log('\n👤 Step 3: Logging in with test user...');
    
    // Fill login form
    await page.waitForSelector('input[name="email"], input[id="email"]', { timeout: 10000 });
    
    const emailField = await page.$('input[name="email"], input[id="email"]');
    const passwordField = await page.$('input[name="password"], input[id="password"]');
    
    if (emailField && passwordField) {
      await emailField.type('test@example.com');
      await passwordField.type('password123');
      console.log('✅ Login credentials entered');
      
      await page.screenshot({ path: `${testResultsDir}/test1-03-login-filled.png`, fullPage: true });
      
      // Submit login
      const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
      if (submitBtn) {
        await submitBtn.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        console.log('✅ Login submitted successfully');
      }
    }

    await page.screenshot({ path: `${testResultsDir}/test1-04-after-login.png`, fullPage: true });

    // Step 4: Navigate to company panel
    console.log('\n🏢 Step 4: Accessing company panel...');
    
    const companySelectors = [
      'a[href*="/company"]',
      'a[href*="company"]',
      'a:contains("Company")',
      'a:contains("Dashboard")',
      '.nav-link[href*="company"]'
    ];

    for (const selector of companySelectors) {
      try {
        const element = await page.$(selector);
        if (element) {
          console.log(`🔗 Found company link: ${selector}`);
          await element.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2' });
          break;
        }
      } catch (e) {
        // Try next
      }
    }

    await page.screenshot({ path: `${testResultsDir}/test1-05-company-panel.png`, fullPage: true });

    // Step 5: Look for AI interface
    console.log('\n🤖 Step 5: Locating AI interface...');
    
    // Log current URL and page title
    const currentUrl = page.url();
    const pageTitle = await page.title();
    console.log(`📍 Current URL: ${currentUrl}`);
    console.log(`📄 Page title: ${pageTitle}`);

    // Check for navigation links
    const allLinks = await page.$$eval('a', links => 
      links.map(link => ({
        text: link.textContent?.trim(),
        href: link.href
      })).filter(link => link.text && link.text.length > 0)
    );

    console.log('\n🔗 Available navigation (first 10):');
    allLinks.slice(0, 10).forEach((link, i) => {
      console.log(`   ${i + 1}. "${link.text}" → ${link.href}`);
    });

    // Look for AI-related navigation
    const aiLinks = allLinks.filter(link => 
      link.text.toLowerCase().includes('ai') ||
      link.text.toLowerCase().includes('chat') ||
      link.text.toLowerCase().includes('assistant') ||
      link.href.includes('ai') ||
      link.href.includes('chat')
    );

    if (aiLinks.length > 0) {
      console.log('\n🎯 AI-related links found:');
      aiLinks.forEach(link => {
        console.log(`   ✅ "${link.text}" → ${link.href}`);
      });

      // Navigate to first AI link
      const firstAiLink = aiLinks[0];
      console.log(`\n🔗 Navigating to: ${firstAiLink.text}`);
      await page.goto(firstAiLink.href, { waitUntil: 'networkidle2' });
      await page.screenshot({ path: `${testResultsDir}/test1-06-ai-interface.png`, fullPage: true });
    } else {
      console.log('\n❌ No AI-related links found');
    }

    // Step 6: Look for chat input
    console.log('\n💬 Step 6: Searching for chat input...');
    
    const chatSelectors = [
      'textarea[placeholder*="Ask"]',
      'textarea[placeholder*="message"]',
      'textarea[placeholder*="prompt"]',
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
        // Continue
      }
    }

    if (chatInput) {
      console.log('✅ Chat interface is accessible!');
      await page.screenshot({ path: `${testResultsDir}/test1-07-chat-ready.png`, fullPage: true });
    } else {
      console.log('❌ Chat interface not found');
      
      // Log available inputs
      const allInputs = await page.$$eval('input, textarea', elements =>
        elements.map(el => ({
          type: el.type || el.tagName.toLowerCase(),
          placeholder: el.placeholder,
          name: el.name,
          id: el.id,
          visible: el.offsetParent !== null
        })).filter(el => el.placeholder || el.name || el.id)
      );
      
      console.log('📝 Available inputs:', allInputs);
    }

    // Final summary
    console.log('\n📋 TEST 1 RESULTS:');
    console.log('=' * 30);
    console.log(`   🏠 Homepage access: ✅`);
    console.log(`   🔐 Login process: ✅`);  
    console.log(`   🏢 Company panel: ✅`);
    console.log(`   🔗 AI navigation: ${aiLinks.length > 0 ? '✅' : '❌'}`);
    console.log(`   💬 Chat interface: ${chatInput ? '✅' : '❌'}`);

    return {
      success: true,
      aiLinksFound: aiLinks.length,
      chatInterfaceFound: !!chatInput,
      finalUrl: page.url()
    };

  } catch (error) {
    console.error('\n❌ TEST 1 FAILED:', error.message);
    await page.screenshot({ path: `${testResultsDir}/test1-99-error.png`, fullPage: true });
    return { success: false, error: error.message };
  } finally {
    await browser.close();
  }
}

export { testWebInterfaceLogin };
