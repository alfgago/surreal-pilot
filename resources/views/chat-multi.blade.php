@extends('layouts.app')

@section('title', 'SurrealPilot Chat - {{ $workspace->name }}')

@section('content')
<div class="flex h-screen bg-gray-900">
    <!-- Conversation Sidebar -->
    <div class="w-80 bg-gray-800 border-r border-gray-700 flex flex-col">
        <!-- Workspace Header -->
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    @if($workspace->engine_type === 'playcanvas')
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    @else
                        <div class="w-10 h-10 bg-gradient-to-r from-orange-600 to-red-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                        </div>
                    @endif
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ $workspace->name }}</h2>
                        <p class="text-sm text-gray-400">{{ ucfirst($workspace->engine_type) }}</p>
                    </div>
                </div>
                <a href="{{ route('workspace.selection') }}" class="text-gray-400 hover:text-white text-sm">
                    Change
                </a>
            </div>

            <!-- New Conversation Button -->
            <button id="new-conversation-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>New Chat</span>
            </button>
        </div>

        <!-- Recent Chats Component -->
        <div class="flex-1 overflow-y-auto">
            <x-recent-chats 
                :workspace-id="$workspace->id"
                :limit="20"
                container-class="bg-transparent"
                header-class="p-4 border-b border-gray-700"
                list-class="p-4 space-y-2 max-h-full overflow-y-auto"
                item-class="conversation-item p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group"
                empty-state-class="text-center py-8"
            />
        </div>

        <!-- Credits & Settings -->
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
        <!-- Chat Header -->
        <div class="bg-gray-800 border-b border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h2 class="text-lg font-semibold text-white" id="current-chat-title">Select a conversation</h2>
                    <div id="conversation-indicator" class="hidden px-3 py-1 bg-indigo-600 text-indigo-100 rounded-full text-sm">
                        <span id="conversation-indicator-text">No conversation selected</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="rename-conversation-btn" class="hidden px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-md transition duration-200 text-sm">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Rename
                    </button>
                    <button id="export-chat" class="hidden px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-md transition duration-200 text-sm">
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
            <div class="flex items-start space-x-3" id="welcome-message">
                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="message-bubble ai-message text-white p-4 rounded-lg bg-gray-700">
                    <div id="welcome-content">
                        <h4 class="font-medium mb-2">👋 Welcome to {{ $workspace->name }}!</h4>
                        <p class="text-gray-300 mb-3">I'm ready to help you create amazing {{ ucfirst($workspace->engine_type) }} games. You can:</p>
                        <ul class="text-gray-300 text-sm space-y-1">
                            @if($workspace->engine_type === 'playcanvas')
                                <li><strong>🎯 Create Games</strong> - Build web and mobile HTML5 games</li>
                                <li><strong>📱 Mobile Optimize</strong> - Add touch controls and responsive design</li>
                                <li><strong>🎮 Add Features</strong> - Physics, animations, and gameplay mechanics</li>
                            @else
                                <li><strong>🏗️ Build Worlds</strong> - Create 3D environments and levels</li>
                                <li><strong>🤖 AI Systems</strong> - Implement behavior trees and navigation</li>
                                <li><strong>🎮 VR/AR</strong> - Develop immersive experiences</li>
                            @endif
                        </ul>
                        <p class="text-gray-400 text-sm mt-3">Start a new conversation or select an existing one to continue!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="border-t border-gray-700 p-4">
            <div class="flex space-x-3">
                <div class="flex-1">
                    <textarea id="message-input"
                              placeholder="Start a new conversation or select an existing one to continue chatting..."
                              class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none disabled:opacity-50 disabled:cursor-not-allowed"
                              rows="3"
                              disabled></textarea>
                </div>
                <div class="flex flex-col justify-end space-y-2">
                    <button id="send-button"
                            class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition duration-200"
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

            <!-- Status Bar -->
            <div class="flex items-center justify-between mt-3 text-xs text-gray-400">
                <div class="flex items-center space-x-4">
                    <span id="typing-indicator" class="hidden flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        AI is thinking...
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

<!-- New Conversation Modal -->
<div id="new-conversation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">New Conversation</h3>
                <button id="close-new-conversation-modal" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="new-conversation-form">
                <div class="space-y-4">
                    <div>
                        <label for="conversation-title" class="block text-sm font-medium text-gray-300 mb-2">
                            Title (optional)
                        </label>
                        <input type="text" id="conversation-title" name="title" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="e.g., 2D Platformer Game">
                    </div>
                    <div>
                        <label for="conversation-description" class="block text-sm font-medium text-gray-300 mb-2">
                            Description (optional)
                        </label>
                        <textarea id="conversation-description" name="description" rows="3"
                                  class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                  placeholder="Brief description of what you want to build..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end mt-6 space-x-3">
                    <button type="button" id="cancel-new-conversation" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-200">
                        Create Conversation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rename Conversation Modal -->
<div id="rename-conversation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Rename Conversation</h3>
                <button id="close-rename-modal" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="rename-conversation-form">
                <div class="space-y-4">
                    <div>
                        <label for="rename-title" class="block text-sm font-medium text-gray-300 mb-2">
                            Title
                        </label>
                        <input type="text" id="rename-title" name="title" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="Enter conversation title">
                    </div>
                    <div>
                        <label for="rename-description" class="block text-sm font-medium text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="rename-description" name="description" rows="3"
                                  class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                  placeholder="Brief description..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end mt-6 space-x-3">
                    <button type="button" id="cancel-rename" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Settings Modal -->
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
                    <select id="provider-select" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                    <a href="/company/provider-settings" class="text-indigo-400 hover:text-indigo-300 text-sm">
                        Manage API Keys →
                    </a>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button id="save-settings" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-200">
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Multi-chat interface with conversation management
class MultiChatInterface {
    constructor() {
        this.workspaceId = {{ $workspace->id }};
        this.currentConversationId = null;
        this.conversations = @json($conversations->toArray());
        this.messagesContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.clearButton = document.getElementById('clear-button');
        this.currentProvider = 'anthropic';
        this.currentMessages = [];

        this.initializeEventListeners();
        this.loadCreditBalance();
    }

    initializeEventListeners() {
        // Conversation management
        document.getElementById('new-conversation-btn').addEventListener('click', () => this.showNewConversationModal());
        document.getElementById('new-conversation-form').addEventListener('submit', (e) => this.createNewConversation(e));
        document.getElementById('close-new-conversation-modal').addEventListener('click', () => this.hideNewConversationModal());
        document.getElementById('cancel-new-conversation').addEventListener('click', () => this.hideNewConversationModal());

        // Rename conversation
        document.getElementById('rename-conversation-btn').addEventListener('click', () => this.showRenameModal());
        document.getElementById('rename-conversation-form').addEventListener('submit', (e) => this.renameConversation(e));
        document.getElementById('close-rename-modal').addEventListener('click', () => this.hideRenameModal());
        document.getElementById('cancel-rename').addEventListener('click', () => this.hideRenameModal());

        // Conversation selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.conversation-item')) {
                const conversationId = parseInt(e.target.closest('.conversation-item').dataset.conversationId);
                this.selectConversation(conversationId);
            }
        });

        // Delete conversation
        document.addEventListener('click', (e) => {
            if (e.target.closest('.delete-conversation-btn')) {
                e.stopPropagation();
                const conversationId = parseInt(e.target.closest('.delete-conversation-btn').dataset.conversationId);
                this.deleteConversation(conversationId);
            }
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
        document.getElementById('clear-button').addEventListener('click', () => this.clearInput());
        document.getElementById('export-chat').addEventListener('click', () => this.exportChat());

        // Token counting
        this.messageInput.addEventListener('input', () => this.updateTokenCount());
    }

    showNewConversationModal() {
        document.getElementById('new-conversation-modal').classList.remove('hidden');
        document.getElementById('conversation-title').focus();
    }

    hideNewConversationModal() {
        document.getElementById('new-conversation-modal').classList.add('hidden');
        document.getElementById('new-conversation-form').reset();
    }

    async createNewConversation(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const title = formData.get('title');
        const description = formData.get('description');

        try {
            const response = await fetch(`/api/workspaces/${this.workspaceId}/conversations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    title: title || null,
                    description: description || null,
                })
            });

            const data = await response.json();

            if (data.success) {
                // Add new conversation to the list
                this.conversations.unshift(data.conversation);
                this.refreshConversationsList();
                this.selectConversation(data.conversation.id);
                this.hideNewConversationModal();
            } else {
                alert('Failed to create conversation: ' + data.message);
            }
        } catch (error) {
            console.error('Error creating conversation:', error);
            alert('Failed to create conversation. Please try again.');
        }
    }

    async selectConversation(conversationId) {
        if (this.currentConversationId === conversationId) return;

        try {
            // Load conversation messages
            const response = await fetch(`/api/conversations/${conversationId}/messages`, {
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                }
            });

            const data = await response.json();

            if (data.success) {
                this.currentConversationId = conversationId;
                this.currentMessages = data.messages;
                
                // Update UI
                this.updateConversationUI(data.conversation);
                this.displayMessages(data.messages);
                this.enableInput();
                this.highlightSelectedConversation(conversationId);
            } else {
                alert('Failed to load conversation: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading conversation:', error);
            alert('Failed to load conversation. Please try again.');
        }
    }

    updateConversationUI(conversation) {
        const titleElement = document.getElementById('current-chat-title');
        const indicatorElement = document.getElementById('conversation-indicator');
        const indicatorTextElement = document.getElementById('conversation-indicator-text');
        
        titleElement.textContent = conversation.title || 'Untitled Chat';
        indicatorElement.classList.remove('hidden');
        indicatorTextElement.textContent = `${this.currentMessages.length} messages`;
        
        // Show conversation controls
        document.getElementById('rename-conversation-btn').classList.remove('hidden');
        document.getElementById('export-chat').classList.remove('hidden');
    }

    displayMessages(messages) {
        // Clear current messages except welcome message
        const welcomeMessage = document.getElementById('welcome-message');
        this.messagesContainer.innerHTML = '';
        
        if (messages.length === 0) {
            this.messagesContainer.appendChild(welcomeMessage);
            return;
        }

        // Display all messages
        messages.forEach(message => {
            this.addMessageToUI(message.content, message.role, false, message.created_at);
        });

        this.scrollToBottom();
    }

    highlightSelectedConversation(conversationId) {
        // Remove previous selection
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('bg-indigo-600', 'bg-gray-700');
            item.classList.add('bg-gray-700');
        });

        // Highlight selected conversation
        const selectedItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
        if (selectedItem) {
            selectedItem.classList.remove('bg-gray-700');
            selectedItem.classList.add('bg-indigo-600');
        }
    }

    enableInput() {
        this.messageInput.disabled = false;
        this.sendButton.disabled = false;
        this.messageInput.placeholder = "Type your message here...";
    }

    async sendMessage() {
        if (!this.currentConversationId) {
            alert('Please select a conversation first!');
            return;
        }

        const message = this.messageInput.value.trim();
        if (!message) return;

        // Disable input while processing
        this.setInputState(false);

        // Add user message to UI and conversation
        this.addMessageToUI(message, 'user');
        await this.saveMessageToConversation(message, 'user');

        // Clear input
        this.messageInput.value = '';
        this.updateTokenCount();

        try {
            // Show typing indicator
            this.showTypingIndicator();

            // Send to chat API with conversation context
            await this.sendStreamingRequest(message);

        } catch (error) {
            console.error('Chat error:', error);
            this.addMessageToUI('Sorry, there was an error processing your request. Please try again.', 'assistant', true);
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
                conversation_id: this.currentConversationId,
                context: {
                    source: 'web',
                    workspace_id: this.workspaceId,
                    timestamp: new Date().toISOString()
                }
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Create AI message container
        const messageElement = this.addMessageToUI('', 'assistant');
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

        // Save AI response to conversation
        if (fullResponse) {
            await this.saveMessageToConversation(fullResponse, 'assistant');
        }

        return fullResponse;
    }

    async saveMessageToConversation(content, role) {
        try {
            await fetch(`/api/conversations/${this.currentConversationId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    role: role,
                    content: content,
                })
            });
        } catch (error) {
            console.error('Error saving message:', error);
        }
    }

    addMessageToUI(content, sender, isError = false, timestamp = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3';

        const isUser = sender === 'user';
        const avatarClass = isUser ? 'bg-gradient-to-r from-blue-500 to-purple-500' : 'bg-gradient-to-r from-purple-500 to-pink-500';
        const messageClass = isUser ? 'user-message bg-blue-600' : 'ai-message bg-gray-700';
        const textColor = isError ? 'text-red-300' : 'text-white';
        const displayTime = timestamp ? new Date(timestamp).toLocaleTimeString() : new Date().toLocaleTimeString();

        messageDiv.innerHTML = `
            <div class="w-10 h-10 ${avatarClass} rounded-full flex items-center justify-center flex-shrink-0">
                ${isUser ?
                    '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>' :
                    '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>'
                }
            </div>
            <div class="message-bubble ${messageClass} ${textColor} p-4 rounded-lg max-w-2xl">
                <div class="message-content">${content}</div>
                <div class="text-xs opacity-70 mt-2">${displayTime}</div>
            </div>
        `;

        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();

        return messageDiv;
    }

    refreshConversationsList() {
        const conversationsList = document.getElementById('conversations-list');
        
        if (this.conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="text-center py-8">
                    <svg class="h-12 w-12 text-gray-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-gray-500 text-sm">No conversations yet</p>
                    <p class="text-gray-600 text-xs mt-1">Start a new chat to begin</p>
                </div>
            `;
            return;
        }

        conversationsList.innerHTML = this.conversations.map(conversation => `
            <div class="conversation-item p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group" 
                 data-conversation-id="${conversation.id}">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-white truncate">
                            ${conversation.title || 'Untitled Chat'}
                        </h4>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2">
                            ${conversation.last_message_preview || 'No messages yet'}
                        </p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-500">
                                ${this.formatRelativeTime(conversation.updated_at)}
                            </span>
                            <span class="text-xs text-gray-500">
                                ${conversation.message_count || 0} messages
                            </span>
                        </div>
                    </div>
                    <button class="delete-conversation-btn ml-2 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                            data-conversation-id="${conversation.id}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    async deleteConversation(conversationId) {
        if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/api/conversations/${conversationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                }
            });

            const data = await response.json();

            if (data.success) {
                // Remove from conversations list
                this.conversations = this.conversations.filter(c => c.id !== conversationId);
                this.refreshConversationsList();

                // If this was the current conversation, clear the chat
                if (this.currentConversationId === conversationId) {
                    this.currentConversationId = null;
                    this.clearChat();
                }
            } else {
                alert('Failed to delete conversation: ' + data.message);
            }
        } catch (error) {
            console.error('Error deleting conversation:', error);
            alert('Failed to delete conversation. Please try again.');
        }
    }

    showRenameModal() {
        if (!this.currentConversationId) return;

        const conversation = this.conversations.find(c => c.id === this.currentConversationId);
        if (!conversation) return;

        document.getElementById('rename-title').value = conversation.title || '';
        document.getElementById('rename-description').value = conversation.description || '';
        document.getElementById('rename-conversation-modal').classList.remove('hidden');
        document.getElementById('rename-title').focus();
    }

    hideRenameModal() {
        document.getElementById('rename-conversation-modal').classList.add('hidden');
        document.getElementById('rename-conversation-form').reset();
    }

    async renameConversation(e) {
        e.preventDefault();
        
        if (!this.currentConversationId) return;

        const formData = new FormData(e.target);
        const title = formData.get('title');
        const description = formData.get('description');

        try {
            const response = await fetch(`/api/conversations/${this.currentConversationId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    title: title || null,
                    description: description || null,
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update conversation in list
                const conversationIndex = this.conversations.findIndex(c => c.id === this.currentConversationId);
                if (conversationIndex !== -1) {
                    this.conversations[conversationIndex].title = data.conversation.title;
                    this.conversations[conversationIndex].description = data.conversation.description;
                }

                // Update UI
                this.refreshConversationsList();
                this.updateConversationUI(data.conversation);
                this.hideRenameModal();
            } else {
                alert('Failed to rename conversation: ' + data.message);
            }
        } catch (error) {
            console.error('Error renaming conversation:', error);
            alert('Failed to rename conversation. Please try again.');
        }
    }

    clearChat() {
        this.messagesContainer.innerHTML = '';
        this.messagesContainer.appendChild(document.getElementById('welcome-message'));
        
        document.getElementById('current-chat-title').textContent = 'Select a conversation';
        document.getElementById('conversation-indicator').classList.add('hidden');
        document.getElementById('rename-conversation-btn').classList.add('hidden');
        document.getElementById('export-chat').classList.add('hidden');
        
        this.messageInput.disabled = true;
        this.sendButton.disabled = true;
        this.messageInput.placeholder = "Start a new conversation or select an existing one to continue chatting...";
    }

    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        
        return date.toLocaleDateString();
    }

    showTypingIndicator() {
        document.getElementById('typing-indicator').classList.remove('hidden');
    }

    hideTypingIndicator() {
        document.getElementById('typing-indicator').classList.add('hidden');
    }

    setInputState(enabled) {
        this.messageInput.disabled = !enabled;
        this.sendButton.disabled = !enabled;
    }

    clearInput() {
        this.messageInput.value = '';
        this.updateTokenCount();
    }

    updateTokenCount() {
        // Simple token estimation (rough approximation)
        const text = this.messageInput.value;
        const tokens = Math.ceil(text.length / 4);
        document.getElementById('token-counter').textContent = `Tokens: ${tokens}`;
        
        // Rough cost estimation (example rates)
        const estimatedCost = (tokens * 0.00003).toFixed(4);
        document.getElementById('estimated-cost').textContent = `Est. cost: $${estimatedCost}`;
    }

    async loadCreditBalance() {
        try {
            const response = await fetch('/api/credits/balance', {
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                document.getElementById('credit-balance').textContent = data.balance || '0';
                document.getElementById('plan-info').textContent = data.plan || 'Free Plan';
            }
        } catch (error) {
            console.error('Error loading credit balance:', error);
        }
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    exportChat() {
        if (!this.currentConversationId || this.currentMessages.length === 0) {
            alert('No conversation selected or no messages to export.');
            return;
        }

        const conversation = this.conversations.find(c => c.id === this.currentConversationId);
        const title = conversation?.title || 'Untitled Chat';
        
        let exportText = `# ${title}\n\n`;
        exportText += `Exported on: ${new Date().toLocaleString()}\n`;
        exportText += `Messages: ${this.currentMessages.length}\n\n`;
        exportText += '---\n\n';

        this.currentMessages.forEach(message => {
            const role = message.role === 'user' ? 'You' : 'Assistant';
            const timestamp = new Date(message.created_at).toLocaleString();
            exportText += `**${role}** (${timestamp}):\n${message.content}\n\n`;
        });

        // Create and download file
        const blob = new Blob([exportText], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_chat_export.md`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
}

// Initialize the interface when the page loads
document.addEventListener('DOMContentLoaded', function() {
    window.chatInterface = new MultiChatInterface();
    
    // Set CSRF token for AJAX requests
    window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
});
</script>
@endpush