// SurrealPilot Flow Test with Puppeteer
import puppeteer from 'puppeteer';
import { promises as fs } from 'fs';
import path from 'path';

async function testSurrealPilotFlow() {
    console.log('🎮 Testing SurrealPilot Flow with Claude Sonnet 4');

    let browser;
    let results = [];

    try {
        // Ensure test results directory exists
        const resultsDir = '../../test-results';
        try {
            await fs.access(resultsDir);
        } catch {
            await fs.mkdir(resultsDir, { recursive: true });
        }

        // Launch browser
        console.log('📱 Launching browser...');
        browser = await puppeteer.launch({
            headless: false, // Show browser for demo
            defaultViewport: { width: 1280, height: 720 },
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });

        const page = await browser.newPage();

        // Step 1: Navigate to SurrealPilot
        console.log('🌐 Navigating to SurrealPilot...');
        await page.goto('http://surreal-pilot.local/', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Take screenshot of homepage
        await page.screenshot({ path: path.join(resultsDir, '01-homepage.png'), fullPage: true });
        console.log('✅ Homepage screenshot saved');

        // Check page title and content
        const title = await page.title();
        console.log(`📄 Page title: "${title}"`);
        results.push({ step: 'homepage', status: 'loaded', title });

        // Step 2: Try to access Filament admin panel
        console.log('🏢 Accessing Filament company panel...');
        await page.goto('http://surreal-pilot.local/company', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Take screenshot of current page
        await page.screenshot({ path: path.join(resultsDir, '02-company-access.png'), fullPage: true });

        const currentUrl = page.url();
        console.log(`📍 Current URL: ${currentUrl}`);

        // Step 3: Handle authentication
        if (currentUrl.includes('login') || currentUrl.includes('register')) {
            console.log('🔓 Authentication required...');

            // Look for register link first
            const registerLink = await page.$('a[href*="register"]');
            if (registerLink) {
                console.log('📝 Found register link - attempting registration...');
                await registerLink.click();
                await page.waitForTimeout(2000);

                // Fill registration form
                const nameField = await page.$('input[name="name"]');
                const emailField = await page.$('input[name="email"]');
                const passwordField = await page.$('input[name="password"]');
                const confirmPasswordField = await page.$('input[name="password_confirmation"]');

                if (nameField && emailField && passwordField && confirmPasswordField) {
                    await nameField.type('Test Game Developer');
                    await emailField.type('gamedev@test.com');
                    await passwordField.type('password123');
                    await confirmPasswordField.type('password123');

                    console.log('✅ Registration form filled');

                    // Submit registration
                    const submitButton = await page.$('button[type="submit"]');
                    if (submitButton) {
                        await submitButton.click();
                        await page.waitForTimeout(5000); // Wait for registration

                        await page.screenshot({ path: path.join(resultsDir, '03-after-registration.png'), fullPage: true });
                        console.log('✅ Registration attempted');
                        results.push({ step: 'registration', status: 'attempted' });
                    }
                }
            } else {
                // Try login with existing credentials
                console.log('🔑 Attempting login...');
                const emailField = await page.$('input[name="email"], input[type="email"]');
                const passwordField = await page.$('input[name="password"], input[type="password"]');

                if (emailField && passwordField) {
                    await emailField.type('test@example.com');
                    await passwordField.type('password123');

                    const submitButton = await page.$('button[type="submit"]');
                    if (submitButton) {
                        await submitButton.click();
                        await page.waitForTimeout(5000);

                        await page.screenshot({ path: path.join(resultsDir, '03-after-login.png'), fullPage: true });
                        console.log('✅ Login attempted');
                        results.push({ step: 'login', status: 'attempted' });
                    }
                }
            }
        }

        // Step 4: Look for AI/Chat interface
        console.log('🤖 Looking for AI chat interface...');

        // Check current page for chat elements
        const chatElements = await page.evaluate(() => {
            // Look for various chat interface patterns
            const textareas = Array.from(document.querySelectorAll('textarea'));
            const chatInputs = textareas.filter(el =>
                el.placeholder && (
                    el.placeholder.toLowerCase().includes('message') ||
                    el.placeholder.toLowerCase().includes('chat') ||
                    el.placeholder.toLowerCase().includes('prompt') ||
                    el.placeholder.toLowerCase().includes('ask')
                )
            );

            const assistButtons = Array.from(document.querySelectorAll('a, button, [role="button"]')).filter(el =>
                el.textContent && (
                    el.textContent.toLowerCase().includes('assist') ||
                    el.textContent.toLowerCase().includes('ai') ||
                    el.textContent.toLowerCase().includes('chat') ||
                    el.textContent.toLowerCase().includes('generate')
                )
            );

            // Look for navigation items that might lead to chat
            const navItems = Array.from(document.querySelectorAll('nav a, .navigation a, [role="navigation"] a')).map(el => ({
                text: el.textContent.trim(),
                href: el.href
            }));

            return {
                textareas: textareas.length,
                chatInputs: chatInputs.length,
                assistButtons: assistButtons.length,
                navItems: navItems.slice(0, 10), // First 10 nav items
                pageText: document.body.textContent.slice(0, 1000) // First 1000 chars
            };
        });

        console.log('💬 Chat elements found:', {
            textareas: chatElements.textareas,
            chatInputs: chatElements.chatInputs,
            assistButtons: chatElements.assistButtons,
            navItemsCount: chatElements.navItems.length
        });

        results.push({ step: 'chat_detection', ...chatElements });

        // Step 5: Try to find and use chat interface
        let gameGenerationAttempted = false;

        // First try to find chat input on current page
        let chatInput = await page.$('textarea[placeholder*="message"], textarea[placeholder*="chat"], textarea[placeholder*="prompt"]');

        if (!chatInput) {
            // Try to find chat/assist navigation
            const assistNav = await page.$('a[href*="chat"], a[href*="assist"], a[href*="ai"]');
            if (assistNav) {
                console.log('🔍 Found assist navigation - clicking...');
                await assistNav.click();
                await page.waitForTimeout(3000);

                // Look again for chat input
                chatInput = await page.$('textarea[placeholder*="message"], textarea[placeholder*="chat"], textarea[placeholder*="prompt"]');
            }
        }

        if (chatInput) {
            console.log('🎯 Found chat input - attempting game generation...');

            const gamePrompt = 'Create a simple HTML5 platformer game with:\n- A red square player character\n- Arrow key movement (left/right)\n- Spacebar to jump\n- Green rectangular platforms to jump on\n- Blue circular coins to collect\n- Score counter\n- Simple physics and collision detection\n\nMake it playable and fun!';

            // Clear any existing text and type our prompt
            await chatInput.click();
            await page.keyboard.key('ControlOrMeta+A'); // Select all
            await chatInput.type(gamePrompt);
            console.log('✅ Game prompt entered');

            await page.screenshot({ path: path.join(resultsDir, '04-prompt-entered.png'), fullPage: true });

            // Look for send button
            const sendButton = await page.$('button[type="submit"], button:has-text("Send"), .send-button, [data-testid="send"]');
            if (sendButton) {
                await sendButton.click();
                console.log('🚀 Send button clicked - waiting for AI response...');

                // Wait for AI response (give it time to process)
                await page.waitForTimeout(20000); // 20 seconds for AI processing

                await page.screenshot({ path: path.join(resultsDir, '05-ai-response.png'), fullPage: true });
                console.log('✅ AI response screenshot saved');

                gameGenerationAttempted = true;
                results.push({ step: 'game_generation', status: 'attempted', prompt: gamePrompt });
            } else {
                console.log('❌ No send button found');
            }
        } else {
            console.log('❌ No chat input found on the page');
        }

        // Step 6: Check for generated content or download links
        if (gameGenerationAttempted) {
            console.log('🔍 Looking for generated game content...');

            const generatedContent = await page.evaluate(() => {
                // Look for game-related content, canvas elements, download links
                const canvasElements = document.querySelectorAll('canvas').length;
                const downloadLinks = Array.from(document.querySelectorAll('a[download], a[href*="download"]')).length;
                const codeBlocks = document.querySelectorAll('pre, code').length;
                const gameKeywords = ['html', 'canvas', 'game', 'player', 'platform', 'score'].filter(keyword =>
                    document.body.textContent.toLowerCase().includes(keyword)
                ).length;

                return {
                    canvasElements,
                    downloadLinks,
                    codeBlocks,
                    gameKeywords,
                    hasGeneratedContent: canvasElements > 0 || downloadLinks > 0 || codeBlocks > 0
                };
            });

            console.log('🎮 Generated content check:', generatedContent);
            results.push({ step: 'content_analysis', ...generatedContent });
        }

        // Step 7: Check storage for generated files
        console.log('📁 Checking storage for generated files...');
        try {
            const storageDir = '../../storage';
            const storageContents = await fs.readdir(storageDir, { recursive: true });

            const gameFiles = storageContents.filter(item =>
                item.includes('build') ||
                item.includes('game') ||
                item.includes('test_') ||
                item.includes('.html') ||
                item.includes('.js')
            );

            console.log('🎮 Found potential game files:', gameFiles.slice(0, 10));
            results.push({ step: 'storage_check', fileCount: gameFiles.length, files: gameFiles.slice(0, 10) });

            // If we found HTML files, try to access one
            const htmlFiles = gameFiles.filter(f => f.endsWith('.html'));
            if (htmlFiles.length > 0) {
                console.log(`🌐 Found HTML file: ${htmlFiles[0]} - attempting to view...`);

                // Try to navigate to the generated game
                const gameUrl = `http://surreal-pilot.local/storage/${htmlFiles[0]}`;
                await page.goto(gameUrl, { waitUntil: 'networkidle2', timeout: 10000 });
                await page.screenshot({ path: path.join(resultsDir, '06-generated-game.png'), fullPage: true });

                results.push({ step: 'game_preview', url: gameUrl, status: 'accessed' });
            }

        } catch (error) {
            console.log('❌ Storage check failed:', error.message);
            results.push({ step: 'storage_check', error: error.message });
        }

        // Final screenshot
        await page.screenshot({ path: path.join(resultsDir, '07-final.png'), fullPage: true });

        console.log('\n🎉 Flow test completed!');

    } catch (error) {
        console.error('💥 Test failed:', error);
        results.push({ step: 'error', error: error.message });
    } finally {
        if (browser) {
            await browser.close();
            console.log('🔒 Browser closed');
        }

        // Save detailed results
        const finalResults = {
            timestamp: new Date().toISOString(),
            testName: 'SurrealPilot Complete Flow Test with Claude Sonnet 4',
            results: results,
            summary: {
                total_steps: results.length,
                successful_steps: results.filter(r => r.status !== 'error').length,
                errors: results.filter(r => r.step === 'error').length
            }
        };

        await fs.writeFile('../../test-results/flow-test-results.json', JSON.stringify(finalResults, null, 2));
        console.log('📊 Results saved to test-results/flow-test-results.json');

        // Print summary
        console.log('\n📋 TEST SUMMARY:');
        console.log(`🔹 Steps completed: ${finalResults.summary.total_steps}`);
        console.log(`✅ Successful steps: ${finalResults.summary.successful_steps}`);
        console.log(`❌ Errors: ${finalResults.summary.errors}`);

        if (finalResults.summary.total_steps > 0) {
            const successRate = (finalResults.summary.successful_steps / finalResults.summary.total_steps * 100).toFixed(1);
            console.log(`🎯 Success rate: ${successRate}%`);
        }

        console.log('\n📸 Screenshots saved:');
        console.log('   - 01-homepage.png');
        console.log('   - 02-company-access.png');
        console.log('   - 03-after-auth.png');
        console.log('   - 04-prompt-entered.png');
        console.log('   - 05-ai-response.png');
        console.log('   - 06-generated-game.png (if applicable)');
        console.log('   - 07-final.png');
    }
}

// Run the test
testSurrealPilotFlow().catch(console.error);
