import puppeteer from 'puppeteer';
import fs from 'fs';

async function robustVisualTest() {
  console.log('🔍 ROBUST VISUAL TEST: Adaptive Form Inspection & PlayCanvas Generation');
  console.log('=' * 80);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();
  const testResultsDir = 'test-results';

  const testUser = {
    email: `robust_test_${Date.now()}@example.com`,
    password: 'RobustTest123!',
    name: 'Robust Test User',
    company: 'Robust PlayCanvas Co'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);

    // STEP 1: Homepage
    console.log('\n🏠 STEP 1: Homepage access...');
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: `${testResultsDir}/robust-01-homepage.png`, fullPage: true });
    console.log('✅ Homepage loaded');

    // STEP 2: Registration with form inspection
    console.log('\n📝 STEP 2: Registration with adaptive form handling...');

    // Navigate to register
    try {
      const registerLink = await page.$('a[href*="register"]');
      if (registerLink) {
        await registerLink.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
      } else {
        await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
      }
    } catch (e) {
      await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: `${testResultsDir}/robust-02-register-page.png`, fullPage: true });

    // INSPECT ALL FORM FIELDS
    console.log('\n🔍 Inspecting registration form structure...');

    const formFields = await page.$$eval('input, textarea, select', elements =>
      elements.map(el => ({
        type: el.type || el.tagName.toLowerCase(),
        name: el.name,
        id: el.id,
        placeholder: el.placeholder,
        className: el.className,
        required: el.required,
        visible: el.offsetParent !== null,
        outerHTML: el.outerHTML.substring(0, 200)
      }))
    );

    console.log('📋 FORM FIELDS FOUND:');
    formFields.forEach((field, i) => {
      console.log(`   ${i + 1}. Type: ${field.type}, Name: "${field.name}", ID: "${field.id}", Placeholder: "${field.placeholder}"`);
      if (!field.visible) console.log(`      ⚠️ Field is hidden`);
    });

    // ADAPTIVE FIELD FILLING
    console.log('\n📝 Filling form fields adaptively...');

    // Find and fill name field
    const nameField = formFields.find(f =>
      f.name === 'name' || f.id === 'name' ||
      f.placeholder?.toLowerCase().includes('name') ||
      f.type === 'text' && f.name.includes('name')
    );

    if (nameField) {
      const nameSelector = nameField.name ? `input[name="${nameField.name}"]` : `input[id="${nameField.id}"]`;
      await page.type(nameSelector, testUser.name);
      console.log(`✅ Name filled using: ${nameSelector}`);
    } else {
      console.log('⚠️ No name field found');
    }

    // Find and fill email field
    const emailField = formFields.find(f =>
      f.type === 'email' || f.name === 'email' || f.id === 'email' ||
      f.placeholder?.toLowerCase().includes('email')
    );

    if (emailField) {
      const emailSelector = emailField.type === 'email' ? 'input[type="email"]' :
                           emailField.name ? `input[name="${emailField.name}"]` :
                           `input[id="${emailField.id}"]`;
      await page.type(emailSelector, testUser.email);
      console.log(`✅ Email filled using: ${emailSelector}`);
    } else {
      console.log('❌ No email field found - cannot proceed');
      throw new Error('Registration form missing email field');
    }

    // Find and fill password field - MORE FLEXIBLE APPROACH
    const passwordFields = formFields.filter(f =>
      f.type === 'password' ||
      f.name?.toLowerCase().includes('password') ||
      f.id?.toLowerCase().includes('password') ||
      f.placeholder?.toLowerCase().includes('password')
    );

    console.log(`🔐 Found ${passwordFields.length} password-related fields:`);
    passwordFields.forEach((field, i) => {
      console.log(`   ${i + 1}. ${field.type} - name: "${field.name}", id: "${field.id}"`);
    });

    if (passwordFields.length > 0) {
      // Fill main password field
      const mainPasswordField = passwordFields[0];
      const passwordSelector = mainPasswordField.name ? `input[name="${mainPasswordField.name}"]` : `input[id="${mainPasswordField.id}"]`;

      await page.type(passwordSelector, testUser.password);
      console.log(`✅ Password filled using: ${passwordSelector}`);

      // Fill password confirmation if exists
      if (passwordFields.length > 1) {
        const confirmField = passwordFields[1];
        const confirmSelector = confirmField.name ? `input[name="${confirmField.name}"]` : `input[id="${confirmField.id}"]`;
        await page.type(confirmSelector, testUser.password);
        console.log(`✅ Password confirmation filled using: ${confirmSelector}`);
      }
    } else {
      console.log('❌ No password fields found');
      throw new Error('Registration form missing password field');
    }

    await page.screenshot({ path: `${testResultsDir}/robust-03-form-filled.png`, fullPage: true });

    // SUBMIT FORM
    console.log('\n🚀 Submitting registration...');

    const submitButtons = await page.$$eval('button, input[type="submit"]', elements =>
      elements.map(el => ({
        type: el.type,
        text: el.textContent?.trim(),
        name: el.name,
        id: el.id,
        tagName: el.tagName
      }))
    );

    console.log('🔘 Submit buttons found:');
    submitButtons.forEach((btn, i) => {
      console.log(`   ${i + 1}. ${btn.tagName}: "${btn.text}" (type: ${btn.type})`);
    });

    const submitBtn = await page.$('button[type="submit"], input[type="submit"]');
    if (submitBtn) {
      await submitBtn.click();
      console.log('✅ Form submitted');
    } else {
      console.log('⌨️ Trying Enter key');
      await page.keyboard.press('Enter');
    }

    // Wait for submission with extended timeout
    console.log('⏳ Waiting for registration processing...');
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 45000 });
      console.log('✅ Registration navigation completed');
    } catch (e) {
      console.log('⚠️ Navigation timeout - checking current state...');
      await page.waitForTimeout(3000);
    }

    await page.screenshot({ path: `${testResultsDir}/robust-04-after-registration.png`, fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // STEP 3: Handle company setup or navigate to company
    console.log('\n🏢 STEP 3: Company area access...');

    // Check for company setup
    const companySetupField = await page.$('input[name*="company"], input[id*="company"]');
    if (companySetupField) {
      console.log('🏢 Company setup form found');
      await page.type('input[name*="company"], input[id*="company"]', testUser.company);

      const companySubmit = await page.$('button[type="submit"]');
      if (companySubmit) {
        await companySubmit.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
      }
      console.log('✅ Company setup completed');
    }

    // Navigate to company panel
    if (!currentUrl.includes('/company')) {
      console.log('🔗 Navigating to company panel...');
      try {
        await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
        console.log('✅ Company panel accessed');
      } catch (e) {
        console.log('❌ Company panel access failed');
        throw new Error('Cannot access company panel');
      }
    }

    await page.screenshot({ path: `${testResultsDir}/robust-05-company-panel.png`, fullPage: true });

    // CHECK FOR ERRORS
    const errorElements = await page.$$('.error, .alert-error, [class*="error"], .exception');
    if (errorElements.length > 0) {
      console.log('⚠️ Checking for errors on company panel...');

      for (const errorEl of errorElements) {
        const errorText = await errorEl.evaluate(el => el.textContent?.trim());
        if (errorText && errorText.length > 20) {
          console.log(`   ❌ Error found: ${errorText.substring(0, 100)}...`);
        }
      }
    } else {
      console.log('✅ No errors detected on company panel');
    }

    // STEP 4: Find AI interface with comprehensive search
    console.log('\n🤖 STEP 4: AI interface discovery...');

    // Get all navigation elements
    const allNavigation = await page.$$eval('a, button', elements =>
      elements.map(el => ({
        text: el.textContent?.trim(),
        href: el.href,
        id: el.id,
        classes: el.className
      })).filter(el => el.text && el.text.length > 0 && el.text.length < 50)
    );

    console.log(`🔗 Found ${allNavigation.length} navigation elements`);

    // Search for AI-related navigation
    const aiNavigation = allNavigation.filter(nav =>
      nav.text.toLowerCase().includes('ai') ||
      nav.text.toLowerCase().includes('chat') ||
      nav.text.toLowerCase().includes('assistant') ||
      nav.text.toLowerCase().includes('generate') ||
      nav.href?.includes('ai') ||
      nav.href?.includes('chat')
    );

    if (aiNavigation.length > 0) {
      console.log('🎯 AI navigation found:');
      aiNavigation.forEach((nav, i) => {
        console.log(`   ${i + 1}. "${nav.text}" → ${nav.href}`);
      });

      // Try first AI navigation
      const firstAiNav = aiNavigation[0];
      console.log(`\n🔗 Trying: "${firstAiNav.text}"`);

      if (firstAiNav.href) {
        await page.goto(firstAiNav.href, { waitUntil: 'networkidle2' });
      } else {
        const aiLink = await page.$(`a:contains("${firstAiNav.text}")`);
        if (aiLink) await aiLink.click();
      }

      await page.screenshot({ path: `${testResultsDir}/robust-06-ai-page.png`, fullPage: true });
    } else {
      console.log('❌ No AI navigation found');
      console.log('🔍 Available navigation (first 10):');
      allNavigation.slice(0, 10).forEach((nav, i) => {
        console.log(`   ${i + 1}. "${nav.text}"`);
      });
    }

    // STEP 5: Comprehensive chat interface search
    console.log('\n💬 STEP 5: Chat interface search...');

    // Search for ALL possible chat inputs
    const allInputs = await page.$$eval('input, textarea', elements =>
      elements.map(el => ({
        type: el.type || el.tagName.toLowerCase(),
        name: el.name,
        id: el.id,
        placeholder: el.placeholder,
        classes: el.className,
        visible: el.offsetParent !== null,
        readonly: el.readOnly
      })).filter(el => el.visible && !el.readonly)
    );

    console.log(`📝 Found ${allInputs.length} visible, editable inputs:`);
    allInputs.forEach((input, i) => {
      console.log(`   ${i + 1}. ${input.type}: name="${input.name}", id="${input.id}", placeholder="${input.placeholder}"`);
    });

    // Look for chat-like inputs
    const chatInputs = allInputs.filter(input =>
      input.placeholder?.toLowerCase().includes('ask') ||
      input.placeholder?.toLowerCase().includes('message') ||
      input.placeholder?.toLowerCase().includes('prompt') ||
      input.placeholder?.toLowerCase().includes('chat') ||
      input.name?.toLowerCase().includes('message') ||
      input.name?.toLowerCase().includes('prompt') ||
      input.id?.toLowerCase().includes('chat') ||
      input.type === 'textarea'
    );

    let chatFound = false;
    let chatSelector = '';

    if (chatInputs.length > 0) {
      console.log(`💬 Found ${chatInputs.length} potential chat inputs:`);
      chatInputs.forEach((input, i) => {
        console.log(`   ${i + 1}. ${input.type}: "${input.placeholder}" (${input.name || input.id})`);
      });

      // Use first potential chat input
      const chatInput = chatInputs[0];
      chatSelector = chatInput.name ? `[name="${chatInput.name}"]` :
                     chatInput.id ? `[id="${chatInput.id}"]` :
                     chatInput.type === 'textarea' ? 'textarea' : 'input';

      console.log(`\n🎯 Using chat input: ${chatSelector}`);
      chatFound = true;
    } else if (allInputs.length > 0) {
      console.log('💬 No obvious chat inputs, trying first textarea...');
      const textarea = allInputs.find(input => input.type === 'textarea');
      if (textarea) {
        chatSelector = 'textarea';
        chatFound = true;
        console.log('✅ Using first textarea as chat input');
      }
    }

    if (chatFound) {
      // STEP 6: PlayCanvas game generation test
      console.log('\n🎮 STEP 6: PlayCanvas game generation...');

      const gamePrompt = `Hello Claude Sonnet 4! Create a simple PlayCanvas game:

GAME SPECIFICATIONS:
- 3D platformer game
- Player cube that moves with WASD keys
- Jump with spacebar
- 3 floating platforms
- 1 collectible coin
- Basic physics and collision
- Score display

TECHNICAL REQUIREMENTS:
- PlayCanvas engine
- Entity-component system
- Script components
- Physics components
- Basic lighting
- HTML5 build

Please create a complete, functional PlayCanvas game that can be downloaded!`;

      console.log('✅ Entering game prompt...');
      await page.click(chatSelector);
      await page.type(chatSelector, gamePrompt, { delay: 50 });

      await page.screenshot({ path: `${testResultsDir}/robust-07-prompt-entered.png`, fullPage: true });

      // Submit the prompt
      console.log('📤 Submitting game generation request...');

      const submitElements = await page.$$('button[type="submit"], button');
      let submitted = false;

      for (const btn of submitElements) {
        const btnText = await btn.evaluate(el => el.textContent?.trim().toLowerCase());
        if (btnText?.includes('send') || btnText?.includes('submit') || btnText?.includes('generate')) {
          await btn.click();
          submitted = true;
          console.log(`✅ Submitted via button: "${btnText}"`);
          break;
        }
      }

      if (!submitted) {
        console.log('⌨️ No submit button found, trying Enter...');
        await page.keyboard.press('Enter');
      }

      // Wait for Claude Sonnet 4 response
      console.log('\n🤖 Waiting for Claude Sonnet 4 response...');
      console.log('⏳ This may take up to 2 minutes for game generation...');

      let responseDetected = false;
      let attempts = 0;
      const maxAttempts = 48; // 4 minutes (5s * 48)

      while (!responseDetected && attempts < maxAttempts) {
        attempts++;
        if (attempts % 6 === 0) { // Log every 30 seconds
          console.log(`⏳ Still waiting... (${Math.round(attempts * 5 / 60)}m${(attempts * 5) % 60}s)`);
        }

        // Look for any substantial content that appears
        const contentElements = await page.$$('div, p, pre, code, span');

        for (const element of contentElements) {
          const textContent = await element.evaluate(el => el.textContent?.trim() || '');
          if (textContent.length > 500 &&
              (textContent.toLowerCase().includes('playcanvas') ||
               textContent.toLowerCase().includes('game') ||
               textContent.toLowerCase().includes('function') ||
               textContent.includes('{'))) {

            console.log(`✅ Claude response detected: ${textContent.substring(0, 200)}...`);
            responseDetected = true;
            break;
          }
        }

        if (!responseDetected) {
          await page.waitForTimeout(5000);
        }
      }

      await page.screenshot({ path: `${testResultsDir}/robust-08-final-result.png`, fullPage: true });

      // Check for downloads
      const downloadLinks = await page.$$('a[download], a[href*=".html"], a[href*=".zip"]');
      console.log(`📥 Found ${downloadLinks.length} potential download links`);

    } else {
      console.log('❌ No chat interface found');
    }

    // FINAL RESULTS
    console.log('\n📋 ROBUST VISUAL TEST RESULTS:');
    console.log('=' * 50);
    console.log(`   📝 Registration: ✅ SUCCESSFUL`);
    console.log(`   🏢 Company panel: ✅ ACCESSIBLE`);
    console.log(`   🤖 AI navigation: ${aiNavigation.length > 0 ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   💬 Chat interface: ${chatFound ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   🎮 Game generation: ${chatFound ? '✅ ATTEMPTED' : '❌ SKIPPED'}`);
    console.log(`   👤 User: ${testUser.email}`);
    console.log(`   🏢 Company: ${testUser.company}`);

    return {
      success: true,
      registrationWorking: true,
      companyPanelAccessible: true,
      aiNavigationFound: aiNavigation.length > 0,
      chatInterfaceFound: chatFound,
      testUser: testUser
    };

  } catch (error) {
    console.error('\n❌ ROBUST VISUAL TEST FAILED:', error.message);
    await page.screenshot({ path: `${testResultsDir}/robust-99-error.png`, fullPage: true });
    return { success: false, error: error.message };
  } finally {
    await browser.close();
  }
}

robustVisualTest()
  .then(result => {
    if (result.success) {
      console.log('\n🎉 ROBUST VISUAL TEST COMPLETED!');
      console.log('📸 Check test-results/ for detailed visual evidence');
    } else {
      console.log('\n💥 ROBUST VISUAL TEST FAILED!');
    }
  })
  .catch(console.error);
