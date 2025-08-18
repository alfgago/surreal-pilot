@props([
    'workspaceId' => null,
    'showWorkspaceInfo' => false,
    'limit' => 10,
    'containerClass' => 'bg-gray-800 rounded-lg',
    'headerClass' => 'p-4 border-b border-gray-700',
    'listClass' => 'p-4 space-y-2',
    'itemClass' => 'p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group',
    'emptyStateClass' => 'text-center py-8'
])

<div class="{{ $containerClass }}" id="recent-chats-component" data-workspace-id="{{ $workspaceId }}" data-limit="{{ $limit }}">
    <!-- Header -->
    <div class="{{ $headerClass }}">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Recent Chats</h3>
            <button id="refresh-recent-chats" class="text-gray-400 hover:text-white transition-colors" title="Refresh">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="recent-chats-loading" class="p-8 text-center hidden">
        <div class="inline-flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-400">Loading conversations...</span>
        </div>
    </div>

    <!-- Conversations List -->
    <div id="recent-chats-list" class="{{ $listClass }}">
        <!-- Conversations will be populated here -->
    </div>

    <!-- Empty State -->
    <div id="recent-chats-empty" class="{{ $emptyStateClass }} hidden">
        <svg class="h-12 w-12 text-gray-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
        <p class="text-gray-500 text-sm">No conversations yet</p>
        <p class="text-gray-600 text-xs mt-1">Start a new chat to begin</p>
    </div>

    <!-- Error State -->
    <div id="recent-chats-error" class="p-8 text-center hidden">
        <svg class="h-12 w-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-400 text-sm">Failed to load conversations</p>
        <button id="retry-recent-chats" class="mt-2 text-indigo-400 hover:text-indigo-300 text-sm">
            Try again
        </button>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-conversation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Delete Conversation</h3>
                <button id="close-delete-modal" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="mb-6">
                <p class="text-gray-300">Are you sure you want to delete this conversation?</p>
                <p class="text-gray-400 text-sm mt-2">This action cannot be undone.</p>
                <div id="delete-conversation-info" class="mt-3 p-3 bg-gray-700 rounded-lg">
                    <p class="text-white font-medium" id="delete-conversation-title">Conversation Title</p>
                    <p class="text-gray-400 text-sm" id="delete-conversation-preview">Last message preview...</p>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button id="cancel-delete" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                    Cancel
                </button>
                <button id="confirm-delete" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
class RecentChatsComponent {
    constructor(containerId = 'recent-chats-component') {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Recent Chats container not found:', containerId);
            return;
        }

        this.workspaceId = this.container.dataset.workspaceId;
        this.limit = parseInt(this.container.dataset.limit) || 10;
        this.conversations = [];
        this.selectedConversationId = null;
        this.deleteConversationId = null;

        this.initializeElements();
        this.initializeEventListeners();
        this.loadConversations();
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
        this.refreshButton?.addEventListener('click', () => this.loadConversations());
        
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
        this.showState('loading');

        try {
            const endpoint = this.workspaceId 
                ? `/api/workspaces/${this.workspaceId}/conversations`
                : `/api/conversations/recent?limit=${this.limit}`;

            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.conversations = data.conversations || [];
                this.renderConversations();
                
                if (this.conversations.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('list');
                }
            } else {
                throw new Error(data.message || 'Failed to load conversations');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showState('error');
        }
    }

    renderConversations() {
        if (!this.listElement) return;

        this.listElement.innerHTML = this.conversations.map(conversation => {
            const title = conversation.title || 'Untitled Chat';
            const preview = conversation.last_message_preview || 'No messages yet';
            const messageCount = conversation.message_count || 0;
            const updatedAt = this.formatDate(conversation.updated_at);
            const workspaceInfo = conversation.workspace ? `${conversation.workspace.name} (${conversation.workspace.engine_type})` : '';

            return `
                <div class="conversation-item p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group" 
                     data-conversation-id="${conversation.id}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="text-sm font-medium text-white truncate">
                                    ${this.escapeHtml(title)}
                                </h4>
                                <button class="delete-conversation-btn ml-2 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                                        data-conversation-id="${conversation.id}"
                                        title="Delete conversation">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                            ${conversation.workspace ? `
                                <p class="text-xs text-indigo-400 mb-1">
                                    ${this.escapeHtml(workspaceInfo)}
                                </p>
                            ` : ''}
                            <p class="text-xs text-gray-400 line-clamp-2 mb-2">
                                ${this.escapeHtml(preview)}
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">
                                    ${updatedAt}
                                </span>
                                <span class="text-xs text-gray-500">
                                    ${messageCount} message${messageCount !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    selectConversation(conversationId) {
        this.selectedConversationId = conversationId;
        
        // Update visual selection
        this.listElement?.querySelectorAll('.conversation-item').forEach(item => {
            const itemId = parseInt(item.dataset.conversationId);
            item.classList.toggle('ring-2', itemId === conversationId);
            item.classList.toggle('ring-indigo-500', itemId === conversationId);
            item.classList.toggle('bg-gray-600', itemId === conversationId);
        });

        // Dispatch custom event for parent components to listen to
        const event = new CustomEvent('conversationSelected', {
            detail: {
                conversationId: conversationId,
                conversation: this.conversations.find(c => c.id === conversationId)
            }
        });
        this.container.dispatchEvent(event);
    }

    showDeleteModal(conversationId) {
        this.deleteConversationId = conversationId;
        const conversation = this.conversations.find(c => c.id === conversationId);
        
        if (conversation) {
            document.getElementById('delete-conversation-title').textContent = conversation.title || 'Untitled Chat';
            document.getElementById('delete-conversation-preview').textContent = conversation.last_message_preview || 'No messages yet';
        }

        this.deleteModal?.classList.remove('hidden');
    }

    hideDeleteModal() {
        this.deleteModal?.classList.add('hidden');
        this.deleteConversationId = null;
    }

    async confirmDelete() {
        if (!this.deleteConversationId) return;

        try {
            const response = await fetch(`/api/conversations/${this.deleteConversationId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                // Remove conversation from local array
                this.conversations = this.conversations.filter(c => c.id !== this.deleteConversationId);
                
                // Re-render the list
                this.renderConversations();
                
                // Show appropriate state
                if (this.conversations.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('list');
                }

                // Dispatch deletion event
                const event = new CustomEvent('conversationDeleted', {
                    detail: { conversationId: this.deleteConversationId }
                });
                this.container.dispatchEvent(event);

                this.hideDeleteModal();
            } else {
                throw new Error(data.message || 'Failed to delete conversation');
            }
        } catch (error) {
            console.error('Error deleting conversation:', error);
            alert('Failed to delete conversation. Please try again.');
        }
    }

    formatDate(dateString) {
        if (!dateString) return 'Unknown';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffInHours = (now - date) / (1000 * 60 * 60);

        if (diffInHours < 1) {
            return 'Just now';
        } else if (diffInHours < 24) {
            return `${Math.floor(diffInHours)}h ago`;
        } else if (diffInHours < 24 * 7) {
            return `${Math.floor(diffInHours / 24)}d ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public methods for external control
    refresh() {
        this.loadConversations();
    }

    getSelectedConversationId() {
        return this.selectedConversationId;
    }

    getConversations() {
        return this.conversations;
    }

    addConversation(conversation) {
        this.conversations.unshift(conversation);
        this.renderConversations();
        this.showState('list');
    }

    updateConversation(conversationId, updates) {
        const index = this.conversations.findIndex(c => c.id === conversationId);
        if (index !== -1) {
            this.conversations[index] = { ...this.conversations[index], ...updates };
            this.renderConversations();
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const recentChatsContainer = document.getElementById('recent-chats-component');
    if (recentChatsContainer) {
        window.recentChatsComponent = new RecentChatsComponent();
    }
});
</script>
@endpush