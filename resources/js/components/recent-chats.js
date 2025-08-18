/**
 * Recent Chats Component
 * 
 * A reusable component for displaying and managing recent chat conversations.
 * Supports conversation selection, deletion, and real-time updates.
 */
export class RecentChatsComponent {
    constructor(options = {}) {
        this.containerId = options.containerId || 'recent-chats-component';
        this.container = document.getElementById(this.containerId);
        
        if (!this.container) {
            console.error('Recent Chats container not found:', this.containerId);
            return;
        }

        // Configuration
        this.workspaceId = options.workspaceId || this.container.dataset.workspaceId;
        this.limit = options.limit || parseInt(this.container.dataset.limit) || 10;
        this.showWorkspaceInfo = options.showWorkspaceInfo || false;
        this.autoRefresh = options.autoRefresh || false;
        this.refreshInterval = options.refreshInterval || 30000; // 30 seconds

        // State
        this.conversations = [];
        this.selectedConversationId = null;
        this.deleteConversationId = null;
        this.isLoading = false;
        this.refreshTimer = null;

        // Event callbacks
        this.onConversationSelected = options.onConversationSelected || null;
        this.onConversationDeleted = options.onConversationDeleted || null;
        this.onError = options.onError || null;

        this.initializeElements();
        this.initializeEventListeners();
        this.loadConversations();

        if (this.autoRefresh) {
            this.startAutoRefresh();
        }
    }

    initializeElements() {
        this.loadingElement = document.getElementById('recent-chats-loading');
        this.listElement = document.getElementById('recent-chats-list');
        this.emptyElement = document.getElementById('recent-chats-empty');
        this.errorElement = document.getElementById('recent-chats-error');
        this.deleteModal = document.getElementById('delete-conversation-modal');
        this.refreshButton = document.getElementById('refresh-recent-chats');
        this.retryButton = document.getElementById('retry-recent-chats');
    }

    initializeEventListeners() {
        // Refresh button
        this.refreshButton?.addEventListener('click', () => this.refresh());
        
        // Retry button
        this.retryButton?.addEventListener('click', () => this.loadConversations());

        // Delete modal events
        document.getElementById('close-delete-modal')?.addEventListener('click', () => this.hideDeleteModal());
        document.getElementById('cancel-delete')?.addEventListener('click', () => this.hideDeleteModal());
        document.getElementById('confirm-delete')?.addEventListener('click', () => this.confirmDelete());

        // Conversation selection and deletion
        this.listElement?.addEventListener('click', (e) => {
            const conversationItem = e.target.closest('.conversation-item');
            const deleteButton = e.target.closest('.delete-conversation-btn');

            if (deleteButton) {
                e.stopPropagation();
                const conversationId = parseInt(deleteButton.dataset.conversationId);
                this.showDeleteModal(conversationId);
            } else if (conversationItem) {
                const conversationId = parseInt(conversationItem.dataset.conversationId);
                this.selectConversation(conversationId);
            }
        });

        // Close modal on outside click
        this.deleteModal?.addEventListener('click', (e) => {
            if (e.target === this.deleteModal) {
                this.hideDeleteModal();
            }
        });

        // Keyboard navigation
        this.listElement?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const focusedItem = e.target.closest('.conversation-item');
                if (focusedItem) {
                    e.preventDefault();
                    const conversationId = parseInt(focusedItem.dataset.conversationId);
                    this.selectConversation(conversationId);
                }
            }
        });
    }

    showState(state) {
        const states = ['loading', 'list', 'empty', 'error'];
        states.forEach(s => {
            const element = document.getElementById(`recent-chats-${s}`);
            if (element) {
                element.classList.toggle('hidden', s !== state);
            }
        });
    }

    async loadConversations() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showState('loading');

        try {
            const endpoint = this.workspaceId 
                ? `/api/workspaces/${this.workspaceId}/conversations`
                : `/api/conversations/recent?limit=${this.limit}`;

            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                this.conversations = data.conversations || [];
                this.renderConversations();
                
                if (this.conversations.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('list');
                }

                // Dispatch loaded event
                this.dispatchEvent('conversationsLoaded', { conversations: this.conversations });
            } else {
                throw new Error(data.message || 'Failed to load conversations');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showState('error');
            
            if (this.onError) {
                this.onError(error);
            }
        } finally {
            this.isLoading = false;
        }
    }

    renderConversations() {
        if (!this.listElement) return;

        this.listElement.innerHTML = this.conversations.map(conversation => {
            const title = conversation.title || 'Untitled Chat';
            const preview = conversation.last_message_preview || 'No messages yet';
            const messageCount = conversation.message_count || 0;
            const updatedAt = this.formatDate(conversation.updated_at);
            const workspaceInfo = conversation.workspace ? 
                `${conversation.workspace.name} (${conversation.workspace.engine_type})` : '';
            const isSelected = this.selectedConversationId === conversation.id;

            return `
                <div class="conversation-item p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group ${isSelected ? 'ring-2 ring-indigo-500 bg-gray-600' : ''}" 
                     data-conversation-id="${conversation.id}"
                     tabindex="0"
                     role="button"
                     aria-label="Select conversation: ${this.escapeHtml(title)}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="text-sm font-medium text-white truncate">
                                    ${this.escapeHtml(title)}
                                </h4>
                                <button class="delete-conversation-btn ml-2 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity focus:opacity-100"
                                        data-conversation-id="${conversation.id}"
                                        title="Delete conversation"
                                        aria-label="Delete conversation: ${this.escapeHtml(title)}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                            ${(this.showWorkspaceInfo && conversation.workspace) ? `
                                <p class="text-xs text-indigo-400 mb-1">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    ${this.escapeHtml(workspaceInfo)}
                                </p>
                            ` : ''}
                            <p class="text-xs text-gray-400 line-clamp-2 mb-2">
                                ${this.escapeHtml(preview)}
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    ${updatedAt}
                                </span>
                                <span class="text-xs text-gray-500 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    ${messageCount}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    selectConversation(conversationId) {
        if (this.selectedConversationId === conversationId) return;

        this.selectedConversationId = conversationId;
        
        // Update visual selection
        this.updateSelectionVisuals();

        const conversation = this.conversations.find(c => c.id === conversationId);
        
        // Call callback if provided
        if (this.onConversationSelected) {
            this.onConversationSelected(conversationId, conversation);
        }

        // Dispatch custom event
        this.dispatchEvent('conversationSelected', {
            conversationId: conversationId,
            conversation: conversation
        });
    }

    updateSelectionVisuals() {
        this.listElement?.querySelectorAll('.conversation-item').forEach(item => {
            const itemId = parseInt(item.dataset.conversationId);
            const isSelected = itemId === this.selectedConversationId;
            
            item.classList.toggle('ring-2', isSelected);
            item.classList.toggle('ring-indigo-500', isSelected);
            item.classList.toggle('bg-gray-600', isSelected);
            item.setAttribute('aria-selected', isSelected.toString());
        });
    }

    showDeleteModal(conversationId) {
        this.deleteConversationId = conversationId;
        const conversation = this.conversations.find(c => c.id === conversationId);
        
        if (conversation) {
            const titleElement = document.getElementById('delete-conversation-title');
            const previewElement = document.getElementById('delete-conversation-preview');
            
            if (titleElement) titleElement.textContent = conversation.title || 'Untitled Chat';
            if (previewElement) previewElement.textContent = conversation.last_message_preview || 'No messages yet';
        }

        this.deleteModal?.classList.remove('hidden');
        
        // Focus the cancel button for accessibility
        setTimeout(() => {
            document.getElementById('cancel-delete')?.focus();
        }, 100);
    }

    hideDeleteModal() {
        this.deleteModal?.classList.add('hidden');
        this.deleteConversationId = null;
    }

    async confirmDelete() {
        if (!this.deleteConversationId) return;

        const deleteButton = document.getElementById('confirm-delete');
        const originalText = deleteButton?.textContent;
        
        try {
            // Show loading state
            if (deleteButton) {
                deleteButton.textContent = 'Deleting...';
                deleteButton.disabled = true;
            }

            const response = await fetch(`/api/conversations/${this.deleteConversationId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                const deletedConversationId = this.deleteConversationId;
                
                // Remove conversation from local array
                this.conversations = this.conversations.filter(c => c.id !== deletedConversationId);
                
                // Clear selection if deleted conversation was selected
                if (this.selectedConversationId === deletedConversationId) {
                    this.selectedConversationId = null;
                }
                
                // Re-render the list
                this.renderConversations();
                
                // Show appropriate state
                if (this.conversations.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('list');
                }

                // Call callback if provided
                if (this.onConversationDeleted) {
                    this.onConversationDeleted(deletedConversationId);
                }

                // Dispatch deletion event
                this.dispatchEvent('conversationDeleted', { 
                    conversationId: deletedConversationId 
                });

                this.hideDeleteModal();
            } else {
                throw new Error(data.message || 'Failed to delete conversation');
            }
        } catch (error) {
            console.error('Error deleting conversation:', error);
            alert('Failed to delete conversation. Please try again.');
            
            if (this.onError) {
                this.onError(error);
            }
        } finally {
            // Restore button state
            if (deleteButton) {
                deleteButton.textContent = originalText;
                deleteButton.disabled = false;
            }
        }
    }

    formatDate(dateString) {
        if (!dateString) return 'Unknown';
        
        try {
            const date = new Date(dateString);
            const now = new Date();
            const diffInMinutes = (now - date) / (1000 * 60);

            if (diffInMinutes < 1) {
                return 'Just now';
            } else if (diffInMinutes < 60) {
                return `${Math.floor(diffInMinutes)}m ago`;
            } else if (diffInMinutes < 60 * 24) {
                return `${Math.floor(diffInMinutes / 60)}h ago`;
            } else if (diffInMinutes < 60 * 24 * 7) {
                return `${Math.floor(diffInMinutes / (60 * 24))}d ago`;
            } else {
                return date.toLocaleDateString();
            }
        } catch (error) {
            return 'Unknown';
        }
    }

    escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    dispatchEvent(eventName, detail) {
        const event = new CustomEvent(eventName, { detail });
        this.container.dispatchEvent(event);
    }

    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshTimer = setInterval(() => {
            if (!this.isLoading) {
                this.loadConversations();
            }
        }, this.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    // Public API methods
    refresh() {
        this.loadConversations();
    }

    getSelectedConversationId() {
        return this.selectedConversationId;
    }

    getConversations() {
        return [...this.conversations];
    }

    addConversation(conversation) {
        this.conversations.unshift(conversation);
        this.renderConversations();
        if (this.conversations.length === 1) {
            this.showState('list');
        }
    }

    updateConversation(conversationId, updates) {
        const index = this.conversations.findIndex(c => c.id === conversationId);
        if (index !== -1) {
            this.conversations[index] = { ...this.conversations[index], ...updates };
            this.renderConversations();
        }
    }

    removeConversation(conversationId) {
        this.conversations = this.conversations.filter(c => c.id !== conversationId);
        if (this.selectedConversationId === conversationId) {
            this.selectedConversationId = null;
        }
        this.renderConversations();
        
        if (this.conversations.length === 0) {
            this.showState('empty');
        }
    }

    setWorkspaceId(workspaceId) {
        this.workspaceId = workspaceId;
        this.loadConversations();
    }

    destroy() {
        this.stopAutoRefresh();
        // Remove event listeners if needed
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const recentChatsContainer = document.getElementById('recent-chats-component');
    if (recentChatsContainer && !window.recentChatsComponent) {
        window.recentChatsComponent = new RecentChatsComponent();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { RecentChatsComponent };
}