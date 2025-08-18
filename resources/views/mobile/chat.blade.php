@extends('mobile.layout')

@section('content')
<div class="mobile-chat-container flex flex-col bg-gray-900">
    <!-- Chat Messages Area -->
    <div id="mobile-chat-messages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4 landscape-compact">
        <!-- Welcome Message -->
        <div class="flex items-start space-x-3">
            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
            </div>
            <div class="mobile-message-bubble ai-message bg-gradient-to-r from-purple-600 to-pink-600 text-white p-4 rounded-2xl rounded-tl-md">
                <p class="mb-2">Welcome to SurrealPilot Mobile! 🎮</p>
                <p class="mb-3">I'm ready to help you create PlayCanvas games with simple chat commands:</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 bg-white rounded-full"></span>
                        <span>Choose from demo templates</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 bg-white rounded-full"></span>
                        <span>Modify games with natural language</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 bg-white rounded-full"></span>
                        <span>Publish and share instantly</span>
                    </div>
                </div>
                <p class="mt-3 text-sm opacity-90">Tap "Demo Templates" to get started!</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions Toolbar -->
    <div class="bg-gray-800 border-t border-gray-700 px-4 py-2 landscape-compact">
        <div class="flex items-center space-x-2 overflow-x-auto">
            <span class="text-xs text-gray-400 whitespace-nowrap">Quick:</span>
            <button class="mobile-quick-action px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-full text-xs whitespace-nowrap touch-target haptic-feedback mobile-transition"
                    onclick="insertMobilePrompt('double the jump height')">
                Jump Higher
            </button>
            <button class="mobile-quick-action px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-full text-xs whitespace-nowrap touch-target haptic-feedback mobile-transition"
                    onclick="insertMobilePrompt('make enemies faster')">
                Faster Enemies
            </button>
            <button class="mobile-quick-action px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-full text-xs whitespace-nowrap touch-target haptic-feedback mobile-transition"
                    onclick="insertMobilePrompt('add more particles')">
                More Effects
            </button>
            <button class="mobile-quick-action px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-full text-xs whitespace-nowrap touch-target haptic-feedback mobile-transition"
                    onclick="insertMobilePrompt('change the lighting')">
                New Lighting
            </button>
        </div>
    </div>

    <!-- Workspace Actions (shown when workspace is active) -->
    <div id="mobile-workspace-actions" class="bg-gray-800 border-t border-gray-700 px-4 py-3 hidden landscape-compact">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                <span class="text-sm text-gray-300" id="mobile-workspace-name">My Game</span>
            </div>
            <div class="flex items-center space-x-2">
                <button id="mobile-preview-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium touch-target haptic-feedback mobile-transition">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Preview
                </button>
                <button id="mobile-publish-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium touch-target haptic-feedback mobile-transition">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Publish
                </button>
            </div>
        </div>
    </div>

    <!-- Input Area -->
    <div class="bg-gray-800 border-t border-gray-700 px-4 py-4 safe-area-bottom landscape-compact">
        <!-- Smart Suggestions (shown when typing) -->
        <div id="mobile-suggestions" class="mb-3 hidden">
            <div class="flex items-center space-x-2 overflow-x-auto pb-2">
                <span class="text-xs text-gray-400 whitespace-nowrap">Suggestions:</span>
                <div id="mobile-suggestions-list" class="flex space-x-2">
                    <!-- Suggestions will be populated dynamically -->
                </div>
            </div>
        </div>

        <div class="flex items-end space-x-3">
            <!-- Message Input -->
            <div class="flex-1 relative">
                <textarea id="mobile-message-input" 
                          placeholder="Type your game modification..." 
                          class="w-full bg-gray-700 border border-gray-600 rounded-2xl px-4 py-3 text-white placeholder-gray-400 mobile-input resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          rows="1"
                          maxlength="500"></textarea>
                
                <!-- Character Counter -->
                <div class="absolute bottom-2 right-3 text-xs text-gray-500">
                    <span id="mobile-char-counter">0</span>/500
                </div>
            </div>
            
            <!-- Send Button -->
            <button id="mobile-send-btn" 
                    class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full touch-target haptic-feedback mobile-transition disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
            </button>
        </div>
        
        <!-- Typing Indicator -->
        <div id="mobile-typing-indicator" class="hidden flex items-center mt-3 text-sm text-gray-400">
            <div class="flex space-x-1 mr-2">
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
            <span>AI is thinking...</span>
        </div>
    </div>
</div>

<!-- Demo Chooser Modal -->
<div id="mobile-demo-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden mobile-transition">
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 mobile-modal mobile-transition transform translate-y-full" id="mobile-demo-panel">
        <div class="p-4 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Choose Demo Template</h2>
                <button id="mobile-demo-close" class="touch-target p-2 text-gray-300 hover:text-white haptic-feedback">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-400 mt-1">Select a template to start prototyping</p>
        </div>
        
        <div class="p-4 max-h-96 overflow-y-auto">
            <div id="mobile-demo-list" class="space-y-3">
                <!-- Demo templates will be loaded here -->
                <div class="text-center py-8">
                    <div class="mobile-loading w-8 h-8 bg-gray-600 rounded-full mx-auto mb-2"></div>
                    <p class="text-sm text-gray-400">Loading templates...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Workspace Preview Modal -->
<div id="mobile-preview-modal" class="fixed inset-0 bg-black z-50 hidden">
    <div class="flex flex-col h-full">
        <div class="bg-gray-800 p-4 flex items-center justify-between safe-area-top">
            <h2 class="text-lg font-semibold text-white">Game Preview</h2>
            <button id="mobile-preview-close" class="touch-target p-2 text-gray-300 hover:text-white haptic-feedback">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="flex-1">
            <iframe id="mobile-preview-frame" class="w-full h-full" frameborder="0"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
class MobileChatInterface {
    constructor() {
        this.messagesContainer = document.getElementById('mobile-chat-messages');
        this.messageInput = document.getElementById('mobile-message-input');
        this.sendButton = document.getElementById('mobile-send-btn');
        this.typingIndicator = document.getElementById('mobile-typing-indicator');
        this.charCounter = document.getElementById('mobile-char-counter');
        this.suggestions = document.getElementById('mobile-suggestions');
        this.suggestionsList = document.getElementById('mobile-suggestions-list');
        this.workspaceActions = document.getElementById('mobile-workspace-actions');
        this.workspaceName = document.getElementById('mobile-workspace-name');
        this.previewBtn = document.getElementById('mobile-preview-btn');
        this.publishBtn = document.getElementById('mobile-publish-btn');
        
        // Current state
        this.currentWorkspace = null;
        this.isTyping = false;
        
        // PlayCanvas command suggestions
        this.playcanvasSuggestions = [
            'double the jump height',
            'make enemies faster',
            'change the lighting to sunset',
            'add more particles',
            'increase player speed',
            'make the world bigger',
            'add sound effects',
            'change the camera angle',
            'add more obstacles',
            'make it more colorful'
        ];
        
        this.initializeEventListeners();
        this.setupAutoResize();
        this.loadWorkspaceState();
    }
    
    initializeEventListeners() {
        // Send message
        this.sendButton.addEventListener('click', () => this.sendMessage());
        
        // Enter to send (mobile-friendly)
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Character counter and suggestions
        this.messageInput.addEventListener('input', () => {
            this.updateCharCounter();
            this.showSmartSuggestions();
        });
        
        // Focus handling for mobile
        this.messageInput.addEventListener('focus', () => {
            setTimeout(() => {
                this.scrollToBottom();
            }, 300); // Wait for keyboard animation
        });
        
        // Demo modal
        document.getElementById('mobile-demos-btn').addEventListener('click', () => this.openDemoModal());
        document.getElementById('mobile-demo-close').addEventListener('click', () => this.closeDemoModal());
        
        // Preview modal
        this.previewBtn.addEventListener('click', () => this.openPreview());
        document.getElementById('mobile-preview-close').addEventListener('click', () => this.closePreview());
        
        // Publish action
        this.publishBtn.addEventListener('click', () => this.publishWorkspace());
    }
    
    setupAutoResize() {
        // Auto-resize textarea
        this.messageInput.addEventListener('input', () => {
            this.messageInput.style.height = 'auto';
            this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 120) + 'px';
        });
    }
    
    updateCharCounter() {
        const length = this.messageInput.value.length;
        this.charCounter.textContent = length;
        
        // Change color based on limit
        if (length > 450) {
            this.charCounter.className = 'text-red-400';
        } else if (length > 400) {
            this.charCounter.className = 'text-yellow-400';
        } else {
            this.charCounter.className = 'text-gray-500';
        }
    }
    
    showSmartSuggestions() {
        const input = this.messageInput.value.toLowerCase();
        
        if (input.length < 2) {
            this.suggestions.classList.add('hidden');
            return;
        }
        
        // Filter suggestions based on input
        const matchingSuggestions = this.playcanvasSuggestions.filter(suggestion =>
            suggestion.toLowerCase().includes(input) || 
            input.split(' ').some(word => suggestion.toLowerCase().includes(word))
        ).slice(0, 3);
        
        if (matchingSuggestions.length > 0) {
            this.suggestionsList.innerHTML = matchingSuggestions.map(suggestion => `
                <button class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-xs whitespace-nowrap touch-target haptic-feedback mobile-transition"
                        onclick="mobileChatInterface.applySuggestion('${suggestion}')">
                    ${suggestion}
                </button>
            `).join('');
            this.suggestions.classList.remove('hidden');
        } else {
            this.suggestions.classList.add('hidden');
        }
    }
    
    applySuggestion(suggestion) {
        this.messageInput.value = suggestion;
        this.suggestions.classList.add('hidden');
        this.updateCharCounter();
        this.messageInput.focus();
        
        // Haptic feedback
        if ('vibrate' in navigator) {
            navigator.vibrate(10);
        }
    }
    
    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        // Disable input
        this.setInputState(false);
        
        // Add user message
        this.addMessage(message, 'user');
        
        // Clear input
        this.messageInput.value = '';
        this.messageInput.style.height = 'auto';
        this.updateCharCounter();
        this.suggestions.classList.add('hidden');
        
        try {
            // Show typing indicator
            this.showTypingIndicator();
            
            // Send to API
            const response = await this.sendToAPI(message);
            
            // Add AI response
            this.addMessage(response, 'ai');
            
            // Update workspace state if needed
            this.checkWorkspaceUpdate();
            
        } catch (error) {
            console.error('Chat error:', error);
            this.addMessage('Sorry, there was an error. Please try again.', 'ai', true);
        } finally {
            this.hideTypingIndicator();
            this.setInputState(true);
        }
    }
    
    async sendToAPI(message) {
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
            },
            body: JSON.stringify({
                messages: [{
                    role: 'user',
                    content: message
                }],
                context: {
                    source: 'mobile',
                    workspace_id: this.currentWorkspace?.id,
                    engine_type: 'playcanvas'
                }
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        return data.response || data.message || 'Response received';
    }
    
    addMessage(content, sender, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3';
        
        const isUser = sender === 'user';
        const avatarClass = isUser ? 'bg-gradient-to-r from-blue-500 to-purple-500' : 'bg-gradient-to-r from-purple-500 to-pink-500';
        const messageClass = isUser ? 'user-message bg-gradient-to-r from-blue-600 to-purple-600' : 'ai-message bg-gradient-to-r from-purple-600 to-pink-600';
        const textColor = isError ? 'text-red-300' : 'text-white';
        const alignment = isUser ? 'ml-auto flex-row-reverse' : '';
        const borderRadius = isUser ? 'rounded-2xl rounded-tr-md' : 'rounded-2xl rounded-tl-md';
        
        messageDiv.className = `flex items-start space-x-3 ${alignment}`;
        
        messageDiv.innerHTML = `
            <div class="w-8 h-8 ${avatarClass} rounded-full flex items-center justify-center flex-shrink-0">
                ${isUser ? 
                    '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>' :
                    '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>'
                }
            </div>
            <div class="mobile-message-bubble ${messageClass} ${textColor} p-4 ${borderRadius}">
                <div class="message-content">${content}</div>
                <div class="text-xs opacity-70 mt-2">${new Date().toLocaleTimeString()}</div>
            </div>
        `;
        
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
        
        return messageDiv;
    }
    
    showTypingIndicator() {
        this.typingIndicator.classList.remove('hidden');
        this.scrollToBottom();
    }
    
    hideTypingIndicator() {
        this.typingIndicator.classList.add('hidden');
    }
    
    setInputState(enabled) {
        this.messageInput.disabled = !enabled;
        this.sendButton.disabled = !enabled;
        
        if (enabled) {
            this.messageInput.focus();
        }
    }
    
    scrollToBottom() {
        setTimeout(() => {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }, 100);
    }
    
    async openDemoModal() {
        const modal = document.getElementById('mobile-demo-modal');
        const panel = document.getElementById('mobile-demo-panel');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            panel.classList.remove('translate-y-full');
        }, 10);
        
        // Load demo templates
        await this.loadDemoTemplates();
    }
    
    closeDemoModal() {
        const modal = document.getElementById('mobile-demo-modal');
        const panel = document.getElementById('mobile-demo-panel');
        
        panel.classList.add('translate-y-full');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    async loadDemoTemplates() {
        try {
            const response = await fetch('/api/demos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                },
                body: JSON.stringify({ engine_type: 'playcanvas' })
            });
            
            const data = await response.json();
            const demoList = document.getElementById('mobile-demo-list');
            
            if (data.demos && data.demos.length > 0) {
                demoList.innerHTML = data.demos.map(demo => `
                    <div class="bg-gray-700 rounded-lg p-4 touch-target haptic-feedback mobile-transition hover:bg-gray-600"
                         onclick="mobileChatInterface.selectDemo('${demo.id}')">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-white font-medium">${demo.name}</h3>
                                <p class="text-sm text-gray-400">${demo.description}</p>
                                <div class="flex items-center mt-1 space-x-2">
                                    <span class="text-xs bg-blue-900 text-blue-300 px-2 py-1 rounded">${demo.difficulty_level}</span>
                                    <span class="text-xs text-gray-500">${demo.estimated_setup_time}s setup</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                demoList.innerHTML = '<div class="text-center py-8 text-gray-400">No templates available</div>';
            }
        } catch (error) {
            console.error('Failed to load demos:', error);
            document.getElementById('mobile-demo-list').innerHTML = '<div class="text-center py-8 text-red-400">Failed to load templates</div>';
        }
    }
    
    async selectDemo(demoId) {
        try {
            // Show loading state
            this.closeDemoModal();
            this.addMessage(`Creating workspace from template...`, 'ai');
            
            const response = await fetch('/api/prototype', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                },
                body: JSON.stringify({
                    template_id: demoId,
                    engine_type: 'playcanvas'
                })
            });
            
            const data = await response.json();
            
            if (data.workspace_id) {
                this.currentWorkspace = {
                    id: data.workspace_id,
                    name: data.name || 'My Game',
                    preview_url: data.preview_url
                };
                
                this.updateWorkspaceUI();
                this.addMessage(`✅ Workspace created! You can now modify your game with chat commands.`, 'ai');
                
                // Save workspace state
                localStorage.setItem('mobile_current_workspace', JSON.stringify(this.currentWorkspace));
            } else {
                throw new Error('Failed to create workspace');
            }
        } catch (error) {
            console.error('Failed to create workspace:', error);
            this.addMessage('❌ Failed to create workspace. Please try again.', 'ai', true);
        }
    }
    
    updateWorkspaceUI() {
        if (this.currentWorkspace) {
            this.workspaceName.textContent = this.currentWorkspace.name;
            this.workspaceActions.classList.remove('hidden');
            
            // Update preview button state
            if (this.currentWorkspace.preview_url) {
                this.previewBtn.disabled = false;
                this.previewBtn.classList.remove('opacity-50');
            }
        } else {
            this.workspaceActions.classList.add('hidden');
        }
    }
    
    loadWorkspaceState() {
        const saved = localStorage.getItem('mobile_current_workspace');
        if (saved) {
            try {
                this.currentWorkspace = JSON.parse(saved);
                this.updateWorkspaceUI();
            } catch (error) {
                console.error('Failed to load workspace state:', error);
            }
        }
    }
    
    async checkWorkspaceUpdate() {
        if (!this.currentWorkspace) return;
        
        try {
            const response = await fetch(`/api/workspace/${this.currentWorkspace.id}/status`);
            const data = await response.json();
            
            if (data.preview_url && data.preview_url !== this.currentWorkspace.preview_url) {
                this.currentWorkspace.preview_url = data.preview_url;
                this.updateWorkspaceUI();
                localStorage.setItem('mobile_current_workspace', JSON.stringify(this.currentWorkspace));
            }
        } catch (error) {
            console.error('Failed to check workspace status:', error);
        }
    }
    
    openPreview() {
        if (!this.currentWorkspace?.preview_url) return;
        
        const modal = document.getElementById('mobile-preview-modal');
        const frame = document.getElementById('mobile-preview-frame');
        
        frame.src = this.currentWorkspace.preview_url;
        modal.classList.remove('hidden');
    }
    
    closePreview() {
        const modal = document.getElementById('mobile-preview-modal');
        const frame = document.getElementById('mobile-preview-frame');
        
        frame.src = '';
        modal.classList.add('hidden');
    }
    
    async publishWorkspace() {
        if (!this.currentWorkspace) return;
        
        try {
            this.publishBtn.disabled = true;
            this.publishBtn.innerHTML = `
                <svg class="w-4 h-4 inline mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Publishing...
            `;
            
            const response = await fetch('/api/workspace/publish', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                },
                body: JSON.stringify({
                    workspace_id: this.currentWorkspace.id
                })
            });
            
            const data = await response.json();
            
            if (data.public_url) {
                this.addMessage(`🎉 Game published! Share this link: ${data.public_url}`, 'ai');
                
                // Copy to clipboard if available
                if (navigator.clipboard) {
                    await navigator.clipboard.writeText(data.public_url);
                    this.addMessage(`📋 Link copied to clipboard!`, 'ai');
                }
            } else {
                throw new Error('Failed to publish');
            }
        } catch (error) {
            console.error('Failed to publish:', error);
            this.addMessage('❌ Failed to publish. Please try again.', 'ai', true);
        } finally {
            this.publishBtn.disabled = false;
            this.publishBtn.innerHTML = `
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Publish
            `;
        }
    }
}

// Quick prompt insertion
function insertMobilePrompt(prompt) {
    const input = document.getElementById('mobile-message-input');
    input.value = prompt;
    input.focus();
    mobileChatInterface.updateCharCounter();
    
    // Haptic feedback
    if ('vibrate' in navigator) {
        navigator.vibrate(10);
    }
}

// Initialize mobile chat interface
let mobileChatInterface;
document.addEventListener('DOMContentLoaded', function() {
    mobileChatInterface = new MobileChatInterface();
});
</script>
@endpush