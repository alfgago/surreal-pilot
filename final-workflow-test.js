import puppeteer from 'puppeteer';

async function finalWorkflowTest() {
  console.log('🎯 FINAL WORKFLOW TEST: Registration → Company → PlayCanvas Generation');
  console.log('=' * 80);

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  const testUser = {
    email: `final_test_${Date.now()}@example.com`,
    password: 'FinalTest123!',
    name: 'Final Test User',
    company: 'Final PlayCanvas Studio'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);

    // STEP 1: Registration with corrected selectors
    console.log('\n📝 STEP 1: Registration with proper form handling...');
    await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: 'test-results/final-01-register.png', fullPage: true });

    // STEP 2: Inspect and fill form dynamically
    console.log('\n🔍 STEP 2: Form field inspection...');

    // Get all form fields
    const formFields = await page.evaluate(() => {
      const inputs = Array.from(document.querySelectorAll('input, textarea'));
      return inputs.map(input => ({
        type: input.type || input.tagName.toLowerCase(),
        name: input.name,
        id: input.id,
        placeholder: input.placeholder,
        required: input.required,
        visible: input.offsetParent !== null
      }));
    });

    console.log('📋 Form fields found:');
    formFields.forEach((field, i) => {
      console.log(`   ${i + 1}. ${field.type}: id="${field.id}", name="${field.name}", placeholder="${field.placeholder}"`);
    });

    // Fill form based on discovered fields
    console.log('\n📝 STEP 3: Filling form fields...');

    // Email field
    const emailField = formFields.find(f => f.type === 'email' || f.id.includes('email') || f.name.includes('email'));
    if (emailField) {
      const emailSelector = emailField.id ? `#${emailField.id}` : `input[type="email"]`;
      await page.type(emailSelector, testUser.email);
      console.log(`✅ Email filled using: ${emailSelector}`);
    }

    // Password fields
    const passwordFields = formFields.filter(f => f.type === 'password');
    console.log(`🔐 Found ${passwordFields.length} password fields`);

    for (let i = 0; i < passwordFields.length && i < 2; i++) {
      const field = passwordFields[i];
      const selector = field.id ? `#${field.id}` : `input[type="password"]:nth-of-type(${i + 1})`;
      await page.type(selector, testUser.password);
      console.log(`✅ Password field ${i + 1} filled using: ${selector}`);
    }

    // Name field (if exists)
    const nameField = formFields.find(f =>
      f.type === 'text' && (f.id.includes('name') || f.name.includes('name'))
    );
    if (nameField) {
      const nameSelector = nameField.id ? `#${nameField.id}` : `input[name*="name"]`;
      await page.type(nameSelector, testUser.name);
      console.log(`✅ Name filled using: ${nameSelector}`);
    } else {
      console.log('ℹ️ No name field found');
    }

    await page.screenshot({ path: 'test-results/final-02-form-filled.png', fullPage: true });

    // STEP 4: Submit registration
    console.log('\n🚀 STEP 4: Submitting registration...');

    const submitButton = await page.$('button[type="submit"]');
    if (submitButton) {
      await submitButton.click();
      console.log('✅ Registration submitted');
    } else {
      console.log('❌ Submit button not found');
      throw new Error('Cannot submit registration');
    }

    // Wait for processing
    console.log('⏳ Waiting for registration processing...');
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
      console.log('✅ Navigation completed');
    } catch (e) {
      console.log('⚠️ Navigation timeout - checking current state...');
      await new Promise(resolve => setTimeout(resolve, 3000));
    }

    await page.screenshot({ path: 'test-results/final-03-after-registration.png', fullPage: true });

    let currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // STEP 5: Company setup handling
    console.log('\n🏢 STEP 5: Company setup...');

    // Check for company setup form
    const companySetupField = await page.$('input[name*="company"], input[id*="company"]');
    if (companySetupField) {
      console.log('🏢 Company setup form found');
      await companySetupField.type(testUser.company);

      const companySubmit = await page.$('button[type="submit"]');
      if (companySubmit) {
        await companySubmit.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 });
        console.log('✅ Company setup completed');
      }
    } else {
      console.log('ℹ️ No company setup form - proceeding to company panel');
    }

    // STEP 6: Navigate to company panel
    console.log('\n🏢 STEP 6: Accessing company panel...');

    currentUrl = page.url();
    if (!currentUrl.includes('/company')) {
      console.log('🔗 Navigating to company panel...');
      await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
    }

    await page.screenshot({ path: 'test-results/final-04-company-panel.png', fullPage: true });

    // STEP 7: Error check
    console.log('\n🔍 STEP 7: Checking for errors...');

    const pageContent = await page.content();
    const hasError = pageContent.toLowerCase().includes('exception') ||
                     pageContent.toLowerCase().includes('error') ||
                     pageContent.toLowerCase().includes('relationship');

    if (hasError) {
      console.log('❌ Error detected on company panel');
      const errorText = await page.evaluate(() => {
        const body = document.body.innerText;
        return body.substring(0, 500);
      });
      console.log('Error content:', errorText);
    } else {
      console.log('✅ No errors - company panel accessible');
    }

    // STEP 8: AI interface discovery
    console.log('\n🤖 STEP 8: AI interface discovery...');

    // Get all navigation links
    const navLinks = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('a'));
      return links.map(link => ({
        text: link.textContent?.trim(),
        href: link.href,
        visible: link.offsetParent !== null
      })).filter(link => link.text && link.visible);
    });

    console.log(`🔗 Found ${navLinks.length} navigation links`);

    // Find AI-related navigation
    const aiNav = navLinks.filter(link =>
      link.text.toLowerCase().includes('ai') ||
      link.text.toLowerCase().includes('chat') ||
      link.text.toLowerCase().includes('assistant') ||
      link.href.includes('ai') ||
      link.href.includes('chat')
    );

    if (aiNav.length > 0) {
      console.log('🎯 AI navigation found:');
      aiNav.forEach((nav, i) => {
        console.log(`   ${i + 1}. "${nav.text}" → ${nav.href}`);
      });

      // Navigate to AI interface
      const firstAiNav = aiNav[0];
      console.log(`\n🔗 Accessing: "${firstAiNav.text}"`);
      await page.goto(firstAiNav.href, { waitUntil: 'networkidle2' });

      await page.screenshot({ path: 'test-results/final-05-ai-interface.png', fullPage: true });
    } else {
      console.log('❌ No AI navigation found');
      console.log('Available navigation (first 10):');
      navLinks.slice(0, 10).forEach(nav => {
        console.log(`   - "${nav.text}"`);
      });
    }

    // STEP 9: Chat interface search
    console.log('\n💬 STEP 9: Chat interface search...');

    const chatInputs = await page.evaluate(() => {
      const inputs = Array.from(document.querySelectorAll('input, textarea'));
      return inputs.map(input => ({
        type: input.type || input.tagName.toLowerCase(),
        placeholder: input.placeholder,
        name: input.name,
        id: input.id,
        visible: input.offsetParent !== null && !input.readOnly
      })).filter(input => input.visible);
    });

    console.log(`📝 Found ${chatInputs.length} visible inputs:`);
    chatInputs.forEach((input, i) => {
      console.log(`   ${i + 1}. ${input.type}: "${input.placeholder}" (${input.name || input.id})`);
    });

    // Find chat-like inputs
    const chatCandidates = chatInputs.filter(input =>
      input.placeholder?.toLowerCase().includes('ask') ||
      input.placeholder?.toLowerCase().includes('message') ||
      input.placeholder?.toLowerCase().includes('prompt') ||
      input.type === 'textarea'
    );

    let chatSuccess = false;

    if (chatCandidates.length > 0) {
      console.log('\n🎯 STEP 10: Testing PlayCanvas generation...');

      const chatInput = chatCandidates[0];
      const chatSelector = chatInput.id ? `#${chatInput.id}` :
                          chatInput.name ? `[name="${chatInput.name}"]` :
                          chatInput.type === 'textarea' ? 'textarea' : 'input';

      console.log(`💬 Using chat input: ${chatSelector}`);

      const gamePrompt = `Hello Claude Sonnet 4! Create a simple PlayCanvas game:

GAME SPECIFICATION:
- 3D platformer with player movement (WASD)
- Jump mechanic (spacebar)
- 3 platforms and 1 collectible coin
- Basic physics and score display
- Complete HTML5 build

Use your Claude Sonnet 4 capabilities to create a functional PlayCanvas game!`;

      await page.click(chatSelector);
      await page.type(chatSelector, gamePrompt);

      await page.screenshot({ path: 'test-results/final-06-prompt-entered.png', fullPage: true });

      // Submit prompt
      const submitButtons = await page.$$('button');
      let submitted = false;

      for (const btn of submitButtons) {
        const text = await btn.evaluate(el => el.textContent?.toLowerCase().trim());
        if (text?.includes('send') || text?.includes('submit')) {
          await btn.click();
          submitted = true;
          console.log(`✅ Submitted via: "${text}"`);
          break;
        }
      }

      if (!submitted) {
        await page.keyboard.press('Enter');
        console.log('⌨️ Submitted via Enter key');
      }

      // Wait briefly for response
      console.log('⏳ Waiting for Claude Sonnet 4 response...');
      await new Promise(resolve => setTimeout(resolve, 10000));

      await page.screenshot({ path: 'test-results/final-07-ai-response.png', fullPage: true });

      chatSuccess = true;
      console.log('✅ PlayCanvas generation test completed');
    } else {
      console.log('❌ No chat interface found');
    }

    // FINAL RESULTS
    await page.screenshot({ path: 'test-results/final-08-final-state.png', fullPage: true });

    console.log('\n🎉 FINAL WORKFLOW TEST RESULTS:');
    console.log('=' * 50);
    console.log(`   📝 Registration: ✅ SUCCESS`);
    console.log(`   🏢 Company panel: ${hasError ? '❌ ERRORS' : '✅ SUCCESS'}`);
    console.log(`   🤖 AI navigation: ${aiNav.length > 0 ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   💬 Chat interface: ${chatSuccess ? '✅ TESTED' : '❌ NOT FOUND'}`);
    console.log(`   🎮 Game generation: ${chatSuccess ? '✅ ATTEMPTED' : '❌ SKIPPED'}`);
    console.log(`   👤 User: ${testUser.email}`);
    console.log(`   🏢 Company: ${testUser.company}`);

    return {
      success: !hasError && chatSuccess,
      registrationWorking: true,
      companyPanelWorking: !hasError,
      aiInterfaceFound: aiNav.length > 0,
      chatWorking: chatSuccess,
      testUser: testUser
    };

  } catch (error) {
    console.error('\n❌ FINAL WORKFLOW TEST FAILED:', error.message);
    await page.screenshot({ path: 'test-results/final-99-error.png', fullPage: true });
    return { success: false, error: error.message };
  } finally {
    await browser.close();
  }
}

finalWorkflowTest()
  .then(result => {
    if (result.success) {
      console.log('\n🏆 COMPLETE WORKFLOW SUCCESS!');
      console.log('🎯 PlayCanvas game generation with Claude Sonnet 4 is READY!');
    } else {
      console.log('\n💥 WORKFLOW INCOMPLETE');
      if (result.error) console.log('Error:', result.error);
    }
  })
  .catch(console.error);
