import puppeteer from 'puppeteer';
import fs from 'fs';

async function debugInterface() {
  console.log('🔍 Debug: Finding AI Chat Interface in Filament...');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
    defaultViewport: { width: 1280, height: 720 }
  });

  const page = await browser.newPage();

  try {
    // Navigate to homepage
    await page.goto('http://surreal-pilot.local/', { waitUntil: 'networkidle2' });
    
    // Check if already authenticated
    const companyLink = await page.$('a[href*="/company"], a[href*="company"]');
    if (companyLink) {
      console.log('✅ Found company link, navigating...');
      await companyLink.click();
      await page.waitForNavigation({ waitUntil: 'networkidle2' });
    }

    // Take screenshot of current page
    await page.screenshot({ path: 'test-results/debug-current-page.png', fullPage: true });

    // Log all navigation links
    console.log('\n📋 Available Navigation Links:');
    const navLinks = await page.$$eval('a', links => 
      links.map(link => ({
        text: link.textContent?.trim(),
        href: link.href,
        classes: link.className
      })).filter(link => link.text && link.text.length > 0)
    );
    
    navLinks.slice(0, 20).forEach((link, i) => {
      console.log(`${i + 1}. "${link.text}" → ${link.href}`);
    });

    // Check for sidebar navigation
    console.log('\n🔗 Checking for AI/Chat related links...');
    const aiRelatedLinks = navLinks.filter(link => 
      link.text.toLowerCase().includes('ai') ||
      link.text.toLowerCase().includes('chat') ||
      link.text.toLowerCase().includes('assistant') ||
      link.text.toLowerCase().includes('generate') ||
      link.href.includes('ai') ||
      link.href.includes('chat')
    );

    if (aiRelatedLinks.length > 0) {
      console.log('🎯 Found AI-related links:');
      aiRelatedLinks.forEach(link => {
        console.log(`   - "${link.text}" → ${link.href}`);
      });

      // Try clicking the first AI-related link
      const firstAiLink = aiRelatedLinks[0];
      console.log(`\n🔗 Trying to navigate to: ${firstAiLink.text}`);
      
      const linkElement = await page.$(`a[href="${firstAiLink.href}"]`);
      if (linkElement) {
        await linkElement.click();
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        await page.screenshot({ path: 'test-results/debug-ai-page.png', fullPage: true });
      }
    } else {
      console.log('❌ No AI-related links found in navigation');
    }

    // Look for any forms or input elements
    console.log('\n📝 Looking for input elements...');
    const inputs = await page.$$eval('input, textarea', elements =>
      elements.map(el => ({
        type: el.type || el.tagName,
        placeholder: el.placeholder,
        name: el.name,
        id: el.id,
        classes: el.className
      })).filter(el => el.placeholder || el.name || el.id)
    );

    if (inputs.length > 0) {
      console.log('🎯 Found input elements:');
      inputs.forEach(input => {
        console.log(`   - ${input.type}: ${input.placeholder || input.name || input.id}`);
      });
    }

    // Check for Livewire components (common in Filament)
    console.log('\n⚡ Checking for Livewire components...');
    const livewireElements = await page.$$eval('[wire\\:model], [x-data], [livewire\\:load]', elements =>
      elements.map(el => ({
        tag: el.tagName,
        wireModel: el.getAttribute('wire:model'),
        xData: el.getAttribute('x-data'),
        classes: el.className
      }))
    );

    if (livewireElements.length > 0) {
      console.log('🔧 Found Livewire/Alpine components:');
      livewireElements.slice(0, 10).forEach(comp => {
        console.log(`   - ${comp.tag}: ${comp.wireModel || comp.xData || comp.classes}`);
      });
    }

    // Check page title and URL
    const title = await page.title();
    const url = await page.url();
    console.log(`\n📄 Current Page: "${title}" at ${url}`);

    // Look for any modal or hidden elements that might contain chat
    console.log('\n🔍 Checking for hidden elements...');
    const hiddenElements = await page.$$eval('[style*="display: none"], [hidden], .hidden', elements =>
      elements.map(el => ({
        tag: el.tagName,
        classes: el.className,
        id: el.id,
        content: el.textContent?.substring(0, 50)
      })).filter(el => el.content && el.content.trim().length > 0)
    );

    if (hiddenElements.length > 0) {
      console.log('👻 Found hidden elements with content:');
      hiddenElements.slice(0, 5).forEach(el => {
        console.log(`   - ${el.tag}: ${el.content}...`);
      });
    }

    console.log('\n✅ Debug completed! Check screenshots:');
    console.log('   - test-results/debug-current-page.png');
    console.log('   - test-results/debug-ai-page.png (if AI link found)');

  } catch (error) {
    console.error('❌ Debug failed:', error.message);
    await page.screenshot({ path: 'test-results/debug-error.png', fullPage: true });
  } finally {
    await browser.close();
  }
}

debugInterface().catch(console.error);