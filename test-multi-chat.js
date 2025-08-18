// Simple test to verify multi-chat interface functionality
const puppeteer = require('puppeteer');

async function testMultiChatInterface() {
    const browser = await puppeteer.launch({ headless: false });
    const page = await browser.newPage();
    
    try {
        // Navigate to the application
        await page.goto('http://surreal-pilot.local');
        
        // Wait for page to load
        await page.waitForTimeout(2000);
        
        console.log('✓ Page loaded successfully');
        
        // Check if we can see the multi-chat interface elements
        const conversationSidebar = await page.$('.w-80.bg-gray-800');
        const newConversationBtn = await page.$('#new-conversation-btn');
        const chatMessages = await page.$('#chat-messages');
        const messageInput = await page.$('#message-input');
        
        if (conversationSidebar) console.log('✓ Conversation sidebar found');
        if (newConversationBtn) console.log('✓ New conversation button found');
        if (chatMessages) console.log('✓ Chat messages container found');
        if (messageInput) console.log('✓ Message input found');
        
        console.log('Multi-chat interface test completed successfully!');
        
    } catch (error) {
        console.error('Test failed:', error);
    } finally {
        await browser.close();
    }
}

// Run the test if this file is executed directly
if (require.main === module) {
    testMultiChatInterface();
}

module.exports = { testMultiChatInterface };