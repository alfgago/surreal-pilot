// Simple test of SurrealPilot flow
import puppeteer from 'puppeteer';
import { promises as fs } from 'fs';

async function testSurrealPilotFlow() {
    console.log('🎮 Testing SurrealPilot Flow with Claude Sonnet 4');

    let browser;
    let results = [];

    try {
        // Launch browser
        console.log('📱 Launching browser...');
        browser = await puppeteer.launch({
            headless: false, // Show browser for demo
            defaultViewport: { width: 1280, height: 720 },
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });

        const page = await browser.newPage();

        // Navigate to SurrealPilot
        console.log('🌐 Navigating to SurrealPilot...');
        await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });

        // Take screenshot of homepage
        await page.screenshot({ path: 'test-results/01-homepage.png', fullPage: true });
        console.log('✅ Homepage screenshot saved');

        // Check page title
        const title = await page.title();
        console.log(`📄 Page title: "${title}"`);
        results.push({ step: 'homepage', status: 'loaded', title });

        // Look for authentication elements
        console.log('🔍 Looking for auth elements...');

        // Try to find login/register buttons or forms
        const authElements = await page.evaluate(() => {
            const loginButtons = Array.from(document.querySelectorAll('a, button')).filter(el =>
                el.textContent && el.textContent.toLowerCase().includes('login')
            );
            const registerButtons = Array.from(document.querySelectorAll('a, button')).filter(el =>
                el.textContent && el.textContent.toLowerCase().includes('register')
            );
            const companyElements = Array.from(document.querySelectorAll('a')).filter(el =>
                el.href && el.href.includes('/company')
            );

            return {
                loginButtons: loginButtons.length,
                registerButtons: registerButtons.length,
                companyElements: companyElements.length,
                hasFilamentPanel: !!document.querySelector('[data-filament-panel]'),
                bodyText: document.body.textContent.slice(0, 500)
            };
        });

        console.log('🔐 Auth elements found:', authElements);
        results.push({ step: 'auth_detection', ...authElements });

        // Try to access Filament admin panel directly
        console.log('🏢 Attempting to access Filament company panel...');
        await page.goto('http://surreal-pilot.local/company', { waitUntil: 'networkidle2' });

        // Take screenshot of admin panel
        await page.screenshot({ path: 'test-results/02-company-panel.png', fullPage: true });

        // Check if we're redirected to login
        const currentUrl = page.url();
        console.log(`📍 Current URL: ${currentUrl}`);

        if (currentUrl.includes('login')) {
            console.log('🔓 Redirected to login - trying to log in...');

            // Try to log in with test credentials
            const emailField = await page.$('input[name="email"], input[type="email"]');
            const passwordField = await page.$('input[name="password"], input[type="password"]');

            if (emailField && passwordField) {
                await emailField.type('test@example.com');
                await passwordField.type('password123');

                // Look for submit button
                const submitButton = await page.$('button[type="submit"], input[type="submit"]');
                if (submitButton) {
                    await submitButton.click();
                    await page.waitForTimeout(3000); // Wait for login

                    await page.screenshot({ path: 'test-results/03-after-login.png', fullPage: true });
                    console.log('✅ Login attempted');
                }
            }
        }

        // Look for chat/AI interface
        console.log('🤖 Looking for AI chat interface...');

        const chatElements = await page.evaluate(() => {
            const textareas = Array.from(document.querySelectorAll('textarea'));
            const chatInputs = textareas.filter(el =>
                el.placeholder && (
                    el.placeholder.toLowerCase().includes('message') ||
                    el.placeholder.toLowerCase().includes('chat') ||
                    el.placeholder.toLowerCase().includes('prompt')
                )
            );

            const assistButtons = Array.from(document.querySelectorAll('a, button')).filter(el =>
                el.textContent && (
                    el.textContent.toLowerCase().includes('assist') ||
                    el.textContent.toLowerCase().includes('ai') ||
                    el.textContent.toLowerCase().includes('chat')
                )
            );

            return {
                textareas: textareas.length,
                chatInputs: chatInputs.length,
                assistButtons: assistButtons.length,
                hasAnyInput: chatInputs.length > 0 || assistButtons.length > 0
            };
        });

        console.log('💬 Chat elements found:', chatElements);
        results.push({ step: 'chat_detection', ...chatElements });

        // If we found chat elements, try to use them
        if (chatElements.hasAnyInput) {
            console.log('🎯 Attempting to generate a game...');

            // Try to find and use chat input
            const chatInput = await page.$('textarea[placeholder*="message"], textarea[placeholder*="chat"]');
            if (chatInput) {
                const gamePrompt = 'Create a simple HTML5 platformer game with a red player character that can jump with spacebar and move with arrow keys. Add green platforms to jump on and blue coins to collect for points.';

                await chatInput.type(gamePrompt);
                console.log('✅ Game prompt entered');

                // Look for send button
                const sendButton = await page.$('button[type="submit"], button:contains("Send")');
                if (sendButton) {
                    await sendButton.click();
                    console.log('🚀 Send button clicked');

                    // Wait for response
                    console.log('⏳ Waiting for AI response...');
                    await page.waitForTimeout(15000);

                    await page.screenshot({ path: 'test-results/04-ai-response.png', fullPage: true });
                    console.log('✅ AI response screenshot saved');
                }
            }
        }

        // Check storage for generated files
        console.log('📁 Checking storage for generated files...');
        try {
            const storageContents = await fs.readdir('./storage', { recursive: true });
            const buildDirs = storageContents.filter(item =>
                item.includes('build') || item.includes('game') || item.includes('test_')
            );

            console.log('🎮 Found potential game files:', buildDirs);
            results.push({ step: 'storage_check', buildDirs });

        } catch (error) {
            console.log('❌ Storage check failed:', error.message);
        }

        // Final screenshot
        await page.screenshot({ path: 'test-results/05-final.png', fullPage: true });

        console.log('\n🎉 Test completed successfully!');

    } catch (error) {
        console.error('💥 Test failed:', error);
        results.push({ step: 'error', error: error.message });
    } finally {
        if (browser) {
            await browser.close();
            console.log('🔒 Browser closed');
        }

        // Save results
        await fs.writeFile('test-results/flow-test-results.json', JSON.stringify(results, null, 2));
        console.log('📊 Results saved to test-results/flow-test-results.json');

        // Print summary
        console.log('\n📋 TEST SUMMARY:');
        results.forEach((result, index) => {
            console.log(`${index + 1}. ${result.step}: ${result.status || 'completed'}`);
        });
    }
}

// Run the test
testSurrealPilotFlow().catch(console.error);
