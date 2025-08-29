<?php

use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->testUser = User::where('email', 'alfredo@5e.cr')->first();
    
    // Create a workspace for testing
    $this->workspace = $this->testUser->workspaces()->create([
        'name' => 'Chat Test Workspace',
        'engine' => 'playcanvas',
    ]);
});

test('user can access chat interface', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate through engine and workspace selection
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->assertPathIs('/chat')
            ->assertPresent('[data-testid="chat-interface"]')
            ->assertPresent('[data-testid="message-input"]')
            ->assertPresent('[data-testid="conversation-sidebar"]');
    });
});

test('user can send a chat message', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15);
            
        // Send a message
        $browser->type('[data-testid="message-input"]', 'Hello, this is a test message')
            ->press('Send')
            ->waitFor('[data-testid="user-message"]', 10)
            ->assertSee('Hello, this is a test message');
    });
});

test('chat interface shows conversation history', function () {
    // Create a conversation with messages
    $conversation = $this->workspace->conversations()->create([
        'title' => 'Test Conversation',
        'user_id' => $this->testUser->id,
    ]);
    
    $conversation->messages()->create([
        'content' => 'Previous test message',
        'role' => 'user',
        'user_id' => $this->testUser->id,
    ]);
    
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15)
            ->assertSee('Previous test message')
            ->assertPresent('[data-testid="conversation-sidebar"]');
    });
});

test('user can open chat settings', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15);
            
        // Open settings
        $browser->click('[data-testid="chat-settings-button"]')
            ->waitFor('[data-testid="chat-settings-modal"]', 10)
            ->assertPresent('[data-testid="chat-settings-modal"]')
            ->assertSee('Chat Settings');
    });
});

test('user can create new conversation', function () {
    $this->browse(function (Browser $browser) {
        loginAsTestUser($browser);
        
        // Navigate to chat
        $browser->click('[data-testid="playcanvas-option"]')
            ->waitForLocation('/workspace-selection', 15)
            ->click('[data-testid="workspace-' . $this->workspace->id . '"]')
            ->waitForLocation('/chat', 15);
            
        // Create new conversation
        $browser->click('[data-testid="new-conversation-button"]')
            ->pause(2000) // Wait for new conversation to be created
            ->assertPresent('[data-testid="message-input"]');
    });
});