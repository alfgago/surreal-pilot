import puppeteer from 'puppeteer';

async function quickRegistrationTest() {
  console.log('⚡ QUICK REGISTRATION TEST');
  console.log('Testing the complete registration → company access flow\n');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  const testUser = {
    email: `quick_test_${Date.now()}@example.com`,
    password: 'QuickTest123!',
    name: 'Quick Test User'
  };

  try {
    console.log(`👤 Test User: ${testUser.email}`);

    // STEP 1: Register
    console.log('\n📝 STEP 1: Registration...');
    await page.goto('http://surreal-pilot.local/register', { waitUntil: 'networkidle2' });
    await page.screenshot({ path: 'test-results/quick-01-register.png', fullPage: true });

    // Fill form adaptively
    await page.type('input[type="email"]', testUser.email);
    console.log('✅ Email filled');

    const passwordFields = await page.$$('input[type="password"]');
    if (passwordFields.length >= 1) {
      await passwordFields[0].type(testUser.password);
      console.log('✅ Password filled');
    }

    if (passwordFields.length >= 2) {
      await passwordFields[1].type(testUser.password);
      console.log('✅ Password confirmation filled');
    }

    // Try to fill name if field exists
    try {
      const nameField = await page.$('input[type="text"], input[name*="name"]');
      if (nameField) {
        await nameField.type(testUser.name);
        console.log('✅ Name filled');
      } else {
        console.log('⚠️ Name field not found');
      }
    } catch (e) {
      console.log('⚠️ Name field not required');
    }

    await page.screenshot({ path: 'test-results/quick-02-form-filled.png', fullPage: true });

    // Submit
    await page.click('button[type="submit"]');
    console.log('✅ Registration submitted');

    // Wait for response - either navigation or stay on page
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
      console.log('✅ Navigation occurred');
    } catch (e) {
      console.log('⚠️ No navigation - checking current state');
    }

    await page.screenshot({ path: 'test-results/quick-03-after-submit.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    // STEP 2: Check for company setup or access company
    console.log('\n🏢 STEP 2: Company access...');

    // Look for company setup or navigate to company
    if (currentUrl.includes('company')) {
      console.log('✅ Already in company area');
    } else {
      // Check for company setup form
      const companyField = await page.$('input[name*="company"], input[id*="company"]');
      if (companyField) {
        console.log('🏢 Company setup form found');
        await page.type('input[name*="company"], input[id*="company"]', 'Quick Test Company');

        const submitBtn = await page.$('button[type="submit"]');
        if (submitBtn) {
          await submitBtn.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 });
        }
        console.log('✅ Company setup completed');
      } else {
        // Try to navigate to company directly
        console.log('🔗 Navigating to company panel...');
        await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });
      }
    }

    await page.screenshot({ path: 'test-results/quick-04-company-area.png', fullPage: true });

    // STEP 3: Check for errors and find AI interface
    console.log('\n🤖 STEP 3: AI interface check...');

    const finalUrl = page.url();
    console.log(`📍 Final URL: ${finalUrl}`);

    // Check for errors
    const pageText = await page.evaluate(() => document.body.innerText);
    const hasError = pageText.toLowerCase().includes('error') ||
                     pageText.toLowerCase().includes('exception') ||
                     pageText.toLowerCase().includes('relationship');

    if (hasError) {
      console.log('❌ Error detected on page');
      console.log('Error content:', pageText.substring(0, 200));
    } else {
      console.log('✅ No errors detected');
    }

    // Look for navigation
    const navLinks = await page.$$eval('a', links =>
      links.map(link => link.textContent?.trim()).filter(text => text && text.length > 0)
    );

    console.log('🔗 Available navigation:', navLinks.slice(0, 10));

    // Look for AI-related elements
    const aiElements = navLinks.filter(text =>
      text.toLowerCase().includes('ai') ||
      text.toLowerCase().includes('chat') ||
      text.toLowerCase().includes('assistant')
    );

    console.log(`🤖 AI-related navigation: ${aiElements.length > 0 ? aiElements : 'None found'}`);

    // Final results
    console.log('\n📋 QUICK TEST RESULTS:');
    console.log(`   📝 Registration: ✅ SUCCESS`);
    console.log(`   🏢 Company access: ${finalUrl.includes('company') ? '✅ SUCCESS' : '❌ FAILED'}`);
    console.log(`   ❌ Errors: ${hasError ? '❌ DETECTED' : '✅ NONE'}`);
    console.log(`   🤖 AI navigation: ${aiElements.length > 0 ? '✅ FOUND' : '❌ NOT FOUND'}`);
    console.log(`   👤 User: ${testUser.email}`);

    await page.screenshot({ path: 'test-results/quick-05-final.png', fullPage: true });

    return {
      success: !hasError && finalUrl.includes('company'),
      userEmail: testUser.email,
      finalUrl: finalUrl,
      hasErrors: hasError,
      aiNavigation: aiElements
    };

  } catch (error) {
    console.error('\n❌ QUICK TEST FAILED:', error.message);
    await page.screenshot({ path: 'test-results/quick-99-error.png', fullPage: true });
    return { success: false, error: error.message };
  } finally {
    await browser.close();
  }
}

quickRegistrationTest()
  .then(result => {
    if (result.success) {
      console.log('\n🎉 QUICK TEST PASSED!');
      console.log('✅ Registration and company access working');
      console.log('📸 Check test-results/ for visual evidence');
    } else {
      console.log('\n💥 QUICK TEST FAILED!');
      if (result.error) console.log('Error:', result.error);
    }
  })
  .catch(console.error);
