@extends('layouts.app')

@section('content')
<div class="flex h-screen bg-gray-900">
    <!-- Clean, Minimal Sidebar -->
    <div class="w-72 bg-gray-800 border-r border-gray-700 flex flex-col">

        <!-- Workspace Selection (PROMINENT) -->
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Choose Your Engine</h3>

            <div class="space-y-3">
                <!-- PlayCanvas Option -->
                <div class="workspace-option p-4 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg cursor-pointer hover:from-blue-700 hover:to-purple-700 transition-all duration-200"
                     data-workspace="playcanvas">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white font-medium">PlayCanvas</div>
                            <div class="text-blue-100 text-sm">Web & Mobile Games</div>
                        </div>
                    </div>
                </div>

                <!-- Unreal Option -->
                <div class="workspace-option p-4 bg-gradient-to-r from-orange-600 to-red-600 rounded-lg cursor-pointer hover:from-orange-700 hover:to-red-700 transition-all duration-200"
                     data-workspace="unreal">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white font-medium">Unreal Engine</div>
                            <div class="text-orange-100 text-sm">3D & VR Games</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Selection Display -->
            <div id="current-workspace" class="mt-4 p-3 bg-gray-700 rounded-lg hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-white text-sm font-medium" id="selected-workspace-name">No workspace selected</div>
                        <div class="text-gray-400 text-xs" id="selected-workspace-type">Choose an engine above</div>
                    </div>
                    <button id="change-workspace" class="text-blue-400 hover:text-blue-300 text-xs">Change</button>
                </div>
            </div>
        </div>

        <!-- Chat History (Collapsible) -->
        <div class="flex-1 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-gray-300">Recent Chats</h3>
                <button id="toggle-history" class="text-xs text-gray-400 hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
            <div id="chat-history" class="space-y-2 max-h-48 overflow-y-auto">
                <div class="text-sm text-gray-500">No previous chats</div>
            </div>
        </div>

        <!-- Credits & Quick Settings -->
        <div class="p-4 border-t border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="text-sm text-gray-300">Credits: <span id="credit-balance" class="text-green-400 font-medium">Loading...</span></div>
                    <div id="plan-info" class="text-xs text-gray-400">Loading plan...</div>
                </div>
                <button id="open-settings" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- Clean Header -->
        <div class="bg-gray-800 border-b border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h2 class="text-lg font-semibold text-white">SurrealPilot Chat</h2>
                    <div id="workspace-indicator" class="hidden px-3 py-1 bg-blue-600 text-blue-100 rounded-full text-sm">
                        <span id="workspace-indicator-text">No workspace selected</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="new-chat" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-md transition duration-200 text-sm">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Chat
                    </button>
                    <button id="export-chat" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-md transition duration-200 text-sm">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4">
            <!-- Welcome Message -->
            <div class="flex items-start space-x-3">
                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="message-bubble ai-message text-white p-4 rounded-lg bg-gray-700">
                    <div id="welcome-content">
                        <h4 class="font-medium mb-2">👋 Welcome to SurrealPilot!</h4>
                        <p class="text-gray-300 mb-3">I'm Claude Sonnet 4, ready to help you create amazing games. First, please select your game engine above:</p>
                        <ul class="text-gray-300 text-sm space-y-1">
                            <li><strong>🎯 PlayCanvas</strong> - For web and mobile HTML5 games</li>
                            <li><strong>🏗️ Unreal Engine</strong> - For 3D, VR, and high-end games</li>
                        </ul>
                        <p class="text-gray-400 text-sm mt-3">Once you choose, I'll provide engine-specific assistance!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contextual Prompt Suggestions (Based on Selected Workspace) -->
        <div id="prompt-suggestions" class="bg-gray-800 border-t border-gray-700 px-4 py-3 hidden">
            <div class="flex items-center space-x-2 text-sm">
                <span class="text-gray-400">Quick prompts:</span>
                <div id="workspace-specific-prompts" class="flex space-x-2">
                    <!-- Dynamically populated based on workspace selection -->
                </div>
            </div>
        </div>

        <!-- Simple Input Area -->
        <div class="border-t border-gray-700 p-4">
            <div class="flex space-x-3">
                <div class="flex-1">
                    <textarea id="message-input"
                              placeholder="Select a workspace above, then describe your game idea..."
                              class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none disabled:opacity-50 disabled:cursor-not-allowed"
                              rows="3"
                              disabled></textarea>
                </div>
                <div class="flex flex-col justify-end space-y-2">
                    <button id="send-button"
                            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition duration-200"
                            disabled>
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Send
                    </button>
                    <button id="clear-button"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg text-sm transition duration-200">
                        Clear
                    </button>
                </div>
            </div>

            <!-- Minimal Status Bar -->
            <div class="flex items-center justify-between mt-3 text-xs text-gray-400">
                <div class="flex items-center space-x-4">
                    <span id="typing-indicator" class="hidden flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Claude is thinking...
                    </span>
                    <span id="connection-indicator" class="flex items-center">
                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                        Ready
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <span id="token-counter">Tokens: 0</span>
                    <span id="estimated-cost">Est. cost: $0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal (Separate from main interface) -->
<div id="settings-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Chat Settings</h3>
                <button id="close-settings" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <!-- AI Provider Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">AI Provider</label>
                    <select id="provider-select" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach($providers ?? [] as $key => $provider)
                            <option value="{{ $key }}" data-requires-key="{{ $provider['requires_key'] ? 'true' : 'false' }}">
                                {{ $provider['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div id="provider-status" class="text-xs text-gray-400 mt-1">Ready</div>
                </div>

                <!-- Creative Temperature -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Creativity Level
                        <span class="text-xs text-gray-400">(Temperature)</span>
                    </label>
                    <input type="range" id="temperature-slider" min="0" max="1" step="0.1" value="0.2"
                           class="w-full h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>Focused</span>
                        <span>Creative</span>
                    </div>
                </div>

                <!-- Advanced Options -->
                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" id="stream-responses" checked class="rounded bg-gray-700 border-gray-600">
                        <span class="text-sm text-gray-300">Stream responses (real-time)</span>
                    </label>
                </div>

                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" id="save-history" checked class="rounded bg-gray-700 border-gray-600">
                        <span class="text-sm text-gray-300">Save chat history</span>
                    </label>
                </div>

                <!-- API Key Management Link -->
                <div class="pt-4 border-t border-gray-700">
                    <a href="/company/provider-settings" class="text-blue-400 hover:text-blue-300 text-sm">
                        Manage API Keys →
                    </a>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button id="save-settings" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Workspace-first chat interface with clean UX
class SimplifiedChatInterface {
    constructor() {
        this.selectedWorkspace = null;
        this.messagesContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.clearButton = document.getElementById('clear-button');
        this.currentProvider = 'anthropic'; // Default to Claude
        this.chatHistory = [];

        this.initializeEventListeners();
        this.loadCreditBalance();
        this.loadChatHistory();
    }

    initializeEventListeners() {
        // Workspace selection
        document.querySelectorAll('.workspace-option').forEach(option => {
            option.addEventListener('click', () => {
                const workspace = option.dataset.workspace;
                this.selectWorkspace(workspace);
            });
        });

        // Chat functionality
        this.sendButton.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Settings modal
        document.getElementById('open-settings').addEventListener('click', () => {
            document.getElementById('settings-modal').classList.remove('hidden');
        });

        document.getElementById('close-settings').addEventListener('click', () => {
            document.getElementById('settings-modal').classList.add('hidden');
        });

        // Other buttons
        document.getElementById('new-chat').addEventListener('click', () => this.newChat());
        document.getElementById('clear-button').addEventListener('click', () => this.clearInput());
        document.getElementById('export-chat').addEventListener('click', () => this.exportChat());

        // Token counting
        this.messageInput.addEventListener('input', () => this.updateTokenCount());
    }

    selectWorkspace(workspace) {
        this.selectedWorkspace = workspace;

        // Update UI
        const currentWorkspace = document.getElementById('current-workspace');
        const workspaceIndicator = document.getElementById('workspace-indicator');
        const workspaceIndicatorText = document.getElementById('workspace-indicator-text');
        const messageInput = this.messageInput;
        const sendButton = this.sendButton;

        // Show current selection
        currentWorkspace.classList.remove('hidden');
        workspaceIndicator.classList.remove('hidden');

        if (workspace === 'playcanvas') {
            document.getElementById('selected-workspace-name').textContent = 'PlayCanvas';
            document.getElementById('selected-workspace-type').textContent = 'Web & Mobile Games';
            workspaceIndicatorText.textContent = 'PlayCanvas';
            workspaceIndicator.className = 'px-3 py-1 bg-blue-600 text-blue-100 rounded-full text-sm';
            messageInput.placeholder = 'Describe your HTML5 game idea - like "Create a 2D platformer with jumping mechanics"...';
        } else if (workspace === 'unreal') {
            document.getElementById('selected-workspace-name').textContent = 'Unreal Engine';
            document.getElementById('selected-workspace-type').textContent = '3D & VR Games';
            workspaceIndicatorText.textContent = 'Unreal Engine';
            workspaceIndicator.className = 'px-3 py-1 bg-orange-600 text-orange-100 rounded-full text-sm';
            messageInput.placeholder = 'Describe your 3D game concept - like "Create a first-person shooter with Blueprint AI"...';
        }

        // Enable input
        messageInput.disabled = false;
        sendButton.disabled = false;

        // Show workspace-specific prompts
        this.showWorkspacePrompts(workspace);

        // Update welcome message
        this.updateWelcomeMessage(workspace);

        console.log(`Workspace selected: ${workspace}`);
    }

    showWorkspacePrompts(workspace) {
        const promptSuggestions = document.getElementById('prompt-suggestions');
        const promptsContainer = document.getElementById('workspace-specific-prompts');

        let prompts = [];

        if (workspace === 'playcanvas') {
            prompts = [
                { text: 'Create 2D Platformer', prompt: 'Create a 2D side-scrolling platformer with player movement, jumping, and basic physics' },
                { text: 'Make Racing Game', prompt: 'Create a simple 3D racing game with car controls and a track' },
                { text: 'Add Multiplayer', prompt: 'Add multiplayer functionality to my PlayCanvas game' },
                { text: 'Mobile Controls', prompt: 'Add touch controls optimized for mobile devices' }
            ];
        } else if (workspace === 'unreal') {
            prompts = [
                { text: 'FPS Blueprint', prompt: 'Create a first-person shooter with Blueprint character controller and weapon system' },
                { text: 'AI Behavior', prompt: 'Create AI enemies with behavior trees and navigation' },
                { text: 'VR Setup', prompt: 'Set up VR development environment and basic VR interactions' },
                { text: 'Landscape Tool', prompt: 'Help me create a detailed landscape with terrain and foliage' }
            ];
        }

        promptsContainer.innerHTML = prompts.map(p =>
            `<button class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-xs transition duration-200"
                     onclick="chatInterface.insertPrompt('${p.prompt}')">${p.text}</button>`
        ).join('');

        promptSuggestions.classList.remove('hidden');
    }

    updateWelcomeMessage(workspace) {
        const welcomeContent = document.getElementById('welcome-content');

        if (workspace === 'playcanvas') {
            welcomeContent.innerHTML = `
                <h4 class="font-medium mb-2">🎯 PlayCanvas Mode Activated!</h4>
                <p class="text-gray-300 mb-3">Perfect for web and mobile games! I can help you create:</p>
                <ul class="text-gray-300 text-sm space-y-1 mb-3">
                    <li>• HTML5 games that run in browsers</li>
                    <li>• Touch-optimized mobile games</li>
                    <li>• 2D and 3D web games</li>
                    <li>• Physics-based gameplay</li>
                </ul>
                <p class="text-blue-400 text-sm">Try: "Create a simple 2D platformer game"</p>
            `;
        } else if (workspace === 'unreal') {
            welcomeContent.innerHTML = `
                <h4 class="font-medium mb-2">🏗️ Unreal Engine Mode Activated!</h4>
                <p class="text-gray-300 mb-3">Ready for high-end game development! I can help you with:</p>
                <ul class="text-gray-300 text-sm space-y-1 mb-3">
                    <li>• Blueprint visual scripting</li>
                    <li>• 3D environments and lighting</li>
                    <li>• VR/AR development</li>
                    <li>• Advanced AI and animation</li>
                </ul>
                <p class="text-orange-400 text-sm">Try: "Create a third-person character controller"</p>
            `;
        }
    }

    insertPrompt(prompt) {
        this.messageInput.value = prompt;
        this.messageInput.focus();
        this.updateTokenCount();
    }

    async sendMessage() {
        if (!this.selectedWorkspace) {
            alert('Please select a workspace first!');
            return;
        }

        const message = this.messageInput.value.trim();
        if (!message) return;

        // Disable input while processing
        this.setInputState(false);

        // Add user message to chat
        this.addMessage(message, 'user');

        // Clear input
        this.messageInput.value = '';
        this.updateTokenCount();

        try {
            // Show typing indicator
            this.showTypingIndicator();

            // Send to chat API with workspace context
            const aiResponse = await this.sendStreamingRequest(message);

        } catch (error) {
            console.error('Chat error:', error);
            this.addMessage('Sorry, there was an error processing your request. Please try again.', 'ai', true);
        } finally {
            this.hideTypingIndicator();
            this.setInputState(true);
        }
    }

    async sendStreamingRequest(message) {
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify({
                messages: [
                    {
                        role: 'user',
                        content: message
                    }
                ],
                provider: this.currentProvider,
                workspace_type: this.selectedWorkspace,
                context: {
                    source: 'desktop',
                    workspace: this.selectedWorkspace,
                    timestamp: new Date().toISOString()
                }
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Create AI message container
        const messageElement = this.addMessage('', 'ai');
        const contentElement = messageElement.querySelector('.message-content');
        let fullResponse = '';

        // Process streaming response
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));

                        if (data.type === 'message') {
                            contentElement.textContent += data.content;
                            fullResponse += data.content;
                        } else if (data.type === 'complete') {
                            contentElement.textContent += data.content;
                            fullResponse += data.content;
                            break;
                        } else if (data.type === 'error') {
                            const errorMsg = `Error: ${data.message}`;
                            contentElement.textContent = errorMsg;
                            contentElement.classList.add('text-red-400');
                            fullResponse = errorMsg;
                            break;
                        }

                        this.scrollToBottom();

                    } catch (e) {
                        console.error('Failed to parse SSE data:', e);
                    }
                }
            }
        }

        return fullResponse;
    }

    addMessage(content, sender, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3';

        const isUser = sender === 'user';
        const avatarClass = isUser ? 'bg-gradient-to-r from-blue-500 to-purple-500' : 'bg-gradient-to-r from-purple-500 to-pink-500';
        const messageClass = isUser ? 'user-message bg-blue-600' : 'ai-message bg-gray-700';
        const textColor = isError ? 'text-red-300' : 'text-white';

        messageDiv.innerHTML = `
            <div class="w-10 h-10 ${avatarClass} rounded-full flex items-center justify-center flex-shrink-0">
                ${isUser ?
                    '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>' :
                    '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>'
                }
            </div>
            <div class="message-bubble ${messageClass} ${textColor} p-4 rounded-lg max-w-2xl">
                <div class="message-content">${content}</div>
                <div class="text-xs opacity-70 mt-2">${new Date().toLocaleTimeString()}</div>
            </div>
        `;

        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();

        return messageDiv;
    }

    showTypingIndicator() {
        document.getElementById('typing-indicator').classList.remove('hidden');
    }

    hideTypingIndicator() {
        document.getElementById('typing-indicator').classList.add('hidden');
    }

    setInputState(enabled) {
        this.messageInput.disabled = !enabled;
        this.sendButton.disabled = !enabled || !this.selectedWorkspace;

        if (enabled && this.selectedWorkspace) {
            this.messageInput.focus();
        }
    }

    newChat() {
        // Keep only the welcome message
        const messages = this.messagesContainer.children;
        for (let i = messages.length - 1; i > 0; i--) {
            messages[i].remove();
        }
    }

    clearInput() {
        this.messageInput.value = '';
        this.updateTokenCount();
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    updateTokenCount() {
        const text = this.messageInput.value;
        const estimatedTokens = Math.ceil(text.length / 4);
        document.getElementById('token-counter').textContent = `Tokens: ${estimatedTokens}`;

        // Simple cost estimation
        const costPer1kTokens = 0.003; // Claude pricing
        const estimatedCost = (estimatedTokens / 1000) * costPer1kTokens;
        document.getElementById('estimated-cost').textContent = `Est. cost: $${estimatedCost.toFixed(4)}`;
    }

    async loadCreditBalance() {
        try {
            const response = await fetch('/api/desktop/credits');
            const data = await response.json();
            document.getElementById('credit-balance').textContent = data.credits.toLocaleString();
            document.getElementById('plan-info').textContent = `${data.plan} plan`;
        } catch (error) {
            console.error('Failed to load credit balance:', error);
            document.getElementById('credit-balance').textContent = 'Error';
        }
    }

    loadChatHistory() {
        // Simplified history loading
        const saved = localStorage.getItem('surrealpilot_simple_chat_history');
        if (saved) {
            this.chatHistory = JSON.parse(saved);
        }
    }

    exportChat() {
        const messages = Array.from(this.messagesContainer.children).slice(1);
        const chatData = messages.map(msg => {
            const isUser = msg.querySelector('.user-message');
            const content = msg.querySelector('.message-content').textContent;
            const timestamp = msg.querySelector('.text-xs').textContent;

            return {
                sender: isUser ? 'user' : 'ai',
                content: content,
                timestamp: timestamp
            };
        });

        const exportData = {
            timestamp: new Date().toISOString(),
            workspace: this.selectedWorkspace,
            provider: this.currentProvider,
            messages: chatData
        };

        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `surrealpilot-${this.selectedWorkspace}-chat-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }
}

// Initialize simplified chat interface
document.addEventListener('DOMContentLoaded', function() {
    window.chatInterface = new SimplifiedChatInterface();
});
</script>
@endpush
